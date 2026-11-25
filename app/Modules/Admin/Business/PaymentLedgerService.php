<?php

namespace App\Modules\Admin\Business;

use App\Events\OrderRealtimeChanged;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\OrderRefund;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentLedgerService
{
    private const PAYMENT_STATUSES = ['paid', 'failed', 'canceled'];
    private const REFUND_STATUSES = ['refunded', 'failed', 'canceled'];

    public function createAttempt(int $tenantId, int $orderId, array $data): OrderPayment
    {
        return DB::transaction(function () use ($tenantId, $orderId, $data): OrderPayment {
            $order = Order::query()
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->findOrFail($orderId);

            $existing = OrderPayment::query()
                ->where('tenant_id', $tenantId)
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();

            if ($existing) {
                abort_if($existing->order_id !== $orderId, 409, 'Chave de operação já utilizada.');

                return $existing;
            }

            if ($order->status === 'canceled') {
                throw ValidationException::withMessages([
                    'order' => 'Não é possível adicionar pagamento a um pedido cancelado.',
                ]);
            }

            $pendingAttemptExists = OrderPayment::query()
                ->where('tenant_id', $tenantId)
                ->where('order_id', $order->id)
                ->where('status', 'pending')
                ->exists();

            if ($pendingAttemptExists) {
                throw ValidationException::withMessages([
                    'payment' => 'Conclua ou cancele a tentativa pendente antes de criar outra.',
                ]);
            }

            $paidCents = $this->paidCentsForOrder($tenantId, $order->id);
            $remainingCents = max($this->toCents($order->total) - $paidCents, 0);
            $amountCents = $this->toCents($data['amount']);

            if ($amountCents <= 0 || $amountCents > $remainingCents) {
                throw ValidationException::withMessages([
                    'amount' => 'O valor deve ser positivo e não pode ultrapassar o saldo do pedido.',
                ]);
            }

            $createdAt = $this->tenantNow($tenantId);
            $payment = $order->payments()->create([
                'tenant_id' => $tenantId,
                'amount' => $this->toDecimal($amountCents),
                'method' => trim($data['method'] ?? '') ?: $order->payment_method,
                'status' => 'pending',
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'idempotency_key' => $data['idempotency_key'],
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $this->synchronizeOrderStatus($tenantId, $order);
            $this->dispatchPaymentChanged($tenantId, $order);

            return $payment;
        });
    }

    public function updatePaymentStatus(int $tenantId, int $paymentId, string $status): OrderPayment
    {
        abort_unless(in_array($status, self::PAYMENT_STATUSES, true), 422, 'Status de pagamento inválido.');

        return DB::transaction(function () use ($tenantId, $paymentId, $status): OrderPayment {
            $paymentReference = OrderPayment::query()
                ->where('tenant_id', $tenantId)
                ->findOrFail($paymentId);
            $order = Order::query()
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->findOrFail($paymentReference->order_id);
            $payment = OrderPayment::query()
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->findOrFail($paymentId);

            if ($payment->status === $status) {
                return $payment;
            }

            if ($payment->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'Uma tentativa finalizada não pode ser alterada.',
                ]);
            }

            if ($status === 'paid') {
                $otherPaidCents = $this->paidCentsForOrder($tenantId, $order->id, $payment->id);

                if ($otherPaidCents + $this->toCents($payment->amount) > $this->toCents($order->total)) {
                    throw ValidationException::withMessages([
                        'status' => 'O recebimento ultrapassaria o valor total do pedido.',
                    ]);
                }
            }

            $payment->update([
                'status' => $status,
                'paid_at' => $status === 'paid' ? $this->tenantNow($tenantId) : null,
            ]);

            $this->synchronizeOrderStatus($tenantId, $order);
            $this->dispatchPaymentChanged($tenantId, $order);

            return $payment->refresh();
        });
    }

    public function createRefund(int $tenantId, int $paymentId, array $data): OrderRefund
    {
        return DB::transaction(function () use ($tenantId, $paymentId, $data): OrderRefund {
            $paymentReference = OrderPayment::query()
                ->where('tenant_id', $tenantId)
                ->findOrFail($paymentId);
            $order = Order::query()
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->findOrFail($paymentReference->order_id);

            $existing = OrderRefund::query()
                ->where('tenant_id', $tenantId)
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();

            if ($existing) {
                abort_if($existing->order_payment_id !== $paymentId, 409, 'Chave de operação já utilizada.');

                return $existing;
            }
            $payment = OrderPayment::query()
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->findOrFail($paymentId);

            if ($payment->status !== 'paid') {
                throw ValidationException::withMessages([
                    'payment' => 'Só é possível reembolsar um pagamento confirmado.',
                ]);
            }

            $reservedRefundCents = $this->toCents((string) (DB::table('order_refunds')
                ->where('tenant_id', $tenantId)
                ->where('order_payment_id', $payment->id)
                ->whereNotIn('status', ['failed', 'canceled'])
                ->sum('amount') ?: '0.00'));
            $availableCents = max($this->toCents($payment->amount) - $reservedRefundCents, 0);
            $amountCents = $this->toCents($data['amount']);

            if ($amountCents <= 0 || $amountCents > $availableCents) {
                throw ValidationException::withMessages([
                    'amount' => 'O reembolso deve ser positivo e não pode ultrapassar o saldo reembolsável.',
                ]);
            }

            $createdAt = $this->tenantNow($tenantId);
            $refund = $payment->refunds()->create([
                'tenant_id' => $tenantId,
                'amount' => $this->toDecimal($amountCents),
                'status' => 'pending',
                'reason' => $data['reason'] ?? null,
                'idempotency_key' => $data['idempotency_key'],
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $this->synchronizeOrderStatus($tenantId, $order);
            $this->dispatchPaymentChanged($tenantId, $order);

            return $refund;
        });
    }

    public function updateRefundStatus(int $tenantId, int $refundId, string $status): OrderRefund
    {
        abort_unless(in_array($status, self::REFUND_STATUSES, true), 422, 'Status de reembolso inválido.');

        return DB::transaction(function () use ($tenantId, $refundId, $status): OrderRefund {
            $refundReference = OrderRefund::query()
                ->where('tenant_id', $tenantId)
                ->findOrFail($refundId);
            $paymentReference = OrderPayment::query()
                ->where('tenant_id', $tenantId)
                ->findOrFail($refundReference->order_payment_id);
            $order = Order::query()
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->findOrFail($paymentReference->order_id);
            $refund = OrderRefund::query()
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->findOrFail($refundId);

            if ($refund->status === $status) {
                return $refund;
            }

            if ($refund->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'Um reembolso finalizado não pode ser alterado.',
                ]);
            }

            $refund->update([
                'status' => $status,
                'processed_at' => $status === 'refunded'
                    ? $this->tenantNow($tenantId)
                    : null,
            ]);

            $this->synchronizeOrderStatus($tenantId, $order);
            $this->dispatchPaymentChanged($tenantId, $order);

            return $refund->refresh();
        });
    }

    public function cancelPendingForOrder(int $tenantId, Order $order): void
    {
        OrderPayment::query()
            ->where('tenant_id', $tenantId)
            ->where('order_id', $order->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'canceled',
                'updated_at' => now(),
            ]);

        $this->synchronizeOrderStatus($tenantId, $order);
    }

    public function synchronizeOrderStatus(int $tenantId, Order $order): void
    {
        $payments = DB::table('order_payments')
            ->where('tenant_id', $tenantId)
            ->where('order_id', $order->id);

        $paidCents = $this->toCents((string) ((clone $payments)
            ->where('status', 'paid')
            ->sum('amount') ?: '0.00'));
        $refundedCents = $this->toCents((string) (DB::table('order_refunds')
            ->join('order_payments', 'order_payments.id', '=', 'order_refunds.order_payment_id')
            ->where('order_refunds.tenant_id', $tenantId)
            ->where('order_payments.tenant_id', $tenantId)
            ->where('order_payments.order_id', $order->id)
            ->where('order_refunds.status', 'refunded')
            ->sum('order_refunds.amount') ?: '0.00'));
        $statuses = (clone $payments)->pluck('status');

        $hasPendingAttempt = $statuses->contains('pending');

        if ($paidCents > 0 && $refundedCents >= $paidCents && ! $hasPendingAttempt) {
            $status = 'refunded';
        } elseif ($refundedCents > 0 && ! $hasPendingAttempt) {
            $status = 'partially_refunded';
        } elseif ($paidCents >= $this->toCents($order->total) && $paidCents > 0) {
            $status = 'paid';
        } elseif ($paidCents > 0) {
            $status = 'partially_paid';
        } elseif ($hasPendingAttempt) {
            $status = 'pending';
        } elseif ($statuses->contains('failed')) {
            $status = 'failed';
        } elseif ($statuses->contains('canceled') || $order->status === 'canceled') {
            $status = 'canceled';
        } else {
            $status = 'untracked';
        }

        if ($order->payment_status !== $status) {
            $order->update(['payment_status' => $status]);
        }
    }

    private function paidCentsForOrder(int $tenantId, int $orderId, ?int $exceptPaymentId = null): int
    {
        $query = DB::table('order_payments')
            ->where('tenant_id', $tenantId)
            ->where('order_id', $orderId)
            ->where('status', 'paid');

        if ($exceptPaymentId) {
            $query->where('id', '<>', $exceptPaymentId);
        }

        return $this->toCents((string) ($query->sum('amount') ?: '0.00'));
    }

    private function toCents(string|int|float $amount): int
    {
        $value = trim((string) $amount);

        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw ValidationException::withMessages([
                'amount' => 'Informe um valor monetário com no máximo duas casas decimais.',
            ]);
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function toDecimal(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function tenantNow(int $tenantId): CarbonImmutable
    {
        $tenant = Tenant::current();

        if ($tenant && (int) $tenant->id !== $tenantId) {
            throw new \LogicException('O estabelecimento atual não corresponde ao lançamento financeiro.');
        }

        if (! $tenant) {
            $tenant = Tenant::query()->find($tenantId);
        }

        if (! $tenant) {
            throw new \LogicException('Não foi possível determinar o fuso horário do estabelecimento.');
        }

        return CarbonImmutable::now($tenant->timezone ?? config('app.timezone'));
    }

    private function dispatchPaymentChanged(int $tenantId, Order $order): void
    {
        OrderRealtimeChanged::dispatch(
            $tenantId,
            (int) $order->id,
            $order->status,
            'payment_status',
            $order->fresh()->updated_at?->toISOString()
        );
    }
}
