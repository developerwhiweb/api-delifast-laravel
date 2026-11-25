<?php

namespace App\Modules\Admin\Business;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FinanceAnalyticsService
{
    public const PAYMENT_STATUSES = [
        'pending',
        'paid',
        'partially_paid',
        'failed',
        'canceled',
        'refunded',
        'partially_refunded',
        'untracked',
    ];

    public function resolveRange(array $filters, string $timezone): array
    {
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $period = $filters['period'] ?? '30';

        [$start, $end] = match ($period) {
            'today' => [$today, $today],
            'yesterday' => [$today->subDay(), $today->subDay()],
            '7' => [$today->subDays(6), $today],
            '30' => [$today->subDays(29), $today],
            '90' => [$today->subDays(89), $today],
            'this_month' => [$today->startOfMonth(), $today],
            'last_month' => [$today->subMonth()->startOfMonth(), $today->subMonth()->endOfMonth()],
            'custom' => [
                CarbonImmutable::parse($filters['start_date'], $timezone)->startOfDay(),
                CarbonImmutable::parse($filters['end_date'], $timezone)->endOfDay(),
            ],
            default => throw new \InvalidArgumentException('Período financeiro inválido.'),
        };

        $start = $start->startOfDay();
        $end = $end->endOfDay();
        $days = $start->diffInDays($end->startOfDay()) + 1;
        $previousStart = $start->subDays($days);

        return [
            'period' => $period,
            'start' => $start,
            'end' => $end,
            'previous_start' => $previousStart,
            'previous_end' => $start->subSecond(),
            'timezone' => $timezone,
            'granularity' => in_array($period, ['today', 'yesterday'], true)
                ? 'hour'
                : ($days > 120 ? 'month' : 'day'),
        ];
    }

    public function getOverview(int $tenantId, array $range, array $filters = []): array
    {
        $orders = $this->filteredOrdersQuery($tenantId, $range, $filters);
        $totals = (clone $orders)->selectRaw(
            'COUNT(*) as order_count,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as canceled_orders,
            COALESCE(SUM(CASE WHEN status <> ? THEN subtotal ELSE 0 END), 0) as product_subtotal,
            COALESCE(SUM(CASE WHEN status <> ? THEN delivery_fee ELSE 0 END), 0) as delivery_fees,
            COALESCE(SUM(CASE WHEN status <> ? THEN discount ELSE 0 END), 0) as discounts,
            COALESCE(SUM(CASE WHEN status <> ? THEN total ELSE 0 END), 0) as sales_total,
            SUM(CASE WHEN finance_status = ? THEN 1 ELSE 0 END) as paid_orders,
            SUM(CASE WHEN finance_status IN (?, ?) AND status <> ? THEN 1 ELSE 0 END) as pending_orders,
            SUM(CASE WHEN finance_status = ? THEN 1 ELSE 0 END) as untracked_orders,
            COALESCE(SUM(CASE WHEN finance_status = ? AND status <> ? THEN
                CASE WHEN total > paid_amount THEN total - paid_amount ELSE 0 END
                ELSE 0 END), 0) as untracked_total,
            COALESCE(SUM(CASE WHEN finance_status IN (?, ?) AND status <> ? THEN
                CASE WHEN total > paid_amount THEN total - paid_amount ELSE 0 END
                ELSE 0 END), 0) as pending_total,
            SUM(CASE WHEN status <> ? AND delivery_fee > 0 THEN 1 ELSE 0 END) as fee_orders,
            SUM(CASE WHEN status <> ? AND discount > 0 THEN 1 ELSE 0 END) as discounted_orders',
            [
                'canceled',
                'canceled',
                'canceled',
                'canceled',
                'canceled',
                'paid',
                'pending',
                'partially_paid',
                'canceled',
                'untracked',
                'untracked',
                'canceled',
                'pending',
                'partially_paid',
                'canceled',
                'canceled',
                'canceled',
            ]
        )->first();

        $received = $this->paymentTotals($tenantId, $range, $filters);
        $refunds = $this->refundTotals($tenantId, $range, $filters);
        $salesTotal = (string) $totals->sales_total;
        $previousRevenue = $this->revenueForRange(
            $tenantId,
            $range['previous_start'],
            $range['previous_end'],
            $filters
        );
        $salesCents = $this->toCents($salesTotal);
        $previousCents = $this->toCents($previousRevenue);
        $feeOrderCount = (int) $totals->fee_orders;
        $discountedOrderCount = (int) $totals->discounted_orders;
        $productSubtotalCents = $this->toCents((string) $totals->product_subtotal);
        $deliveryFeeCents = $this->toCents((string) $totals->delivery_fees);
        $discountCents = $this->toCents((string) $totals->discounts);

        return [
            'metrics' => [
                'sales_total' => $this->money($salesCents),
                'received_total' => $received['amount'],
                'pending_total' => $this->money($this->toCents((string) $totals->pending_total)),
                'refunded_total' => $refunds['amount'],
                'net_received' => $this->money($this->toCents($received['amount']) - $this->toCents($refunds['amount'])),
                'order_count' => (int) $totals->order_count,
                'paid_orders' => (int) $totals->paid_orders,
                'pending_orders' => (int) $totals->pending_orders,
                'canceled_orders' => (int) $totals->canceled_orders,
                'refunded_orders' => $refunds['order_count'],
                'untracked_orders' => (int) $totals->untracked_orders,
                'untracked_total' => $this->money($this->toCents((string) $totals->untracked_total)),
                'delivery_fees' => $this->money($deliveryFeeCents),
                'delivery_fee_orders' => $feeOrderCount,
                'average_delivery_fee' => $feeOrderCount > 0
                    ? $this->money((int) round($deliveryFeeCents / $feeOrderCount))
                    : '0.00',
                'discounts' => $this->money($discountCents),
                'discounted_orders' => $discountedOrderCount,
                'average_discount' => $discountedOrderCount > 0
                    ? $this->money((int) round($discountCents / $discountedOrderCount))
                    : '0.00',
                'discount_share' => $productSubtotalCents > 0
                    ? ($discountCents / $productSubtotalCents) * 100
                    : null,
                'revenue_change' => $previousCents > 0
                    ? (($salesCents - $previousCents) / $previousCents) * 100
                    : null,
            ],
            'sales_chart' => $this->getSalesChart($tenantId, $range, $filters),
            'delivery_chart' => $this->getDeliveryChart($tenantId, $range, $filters),
            'payment_methods' => $this->getPaymentMethods($tenantId, $range, $filters),
            'payment_statuses' => $this->getPaymentStatuses($tenantId, $range, $filters),
            'refund_statuses' => $this->getRefundStatuses($tenantId, $range, $filters),
            'order_summary' => $this->getOrderSummary($tenantId, $range, $filters),
        ];
    }

    public function ordersQuery(int $tenantId, array $range, array $filters = []): Builder
    {
        return $this->filteredOrdersQuery($tenantId, $range, $filters)
            ->orderByDesc('placed_at')
            ->orderByDesc('id');
    }

    public function paymentAttemptsQuery(int $tenantId, array $range, array $filters = []): Builder
    {
        $query = DB::table('order_payments')
            ->joinSub($this->baseOrdersQuery($tenantId), 'finance_orders', 'finance_orders.id', '=', 'order_payments.order_id')
            ->where('order_payments.tenant_id', $tenantId)
            ->whereBetween('order_payments.created_at', [$range['start'], $range['end']]);

        if (! empty($filters['payment_method'])) {
            $query->where('order_payments.method', $filters['payment_method']);
        }

        if (! empty($filters['payment_status'])) {
            $query->where('finance_orders.finance_status', $filters['payment_status']);
        }

        return $query->select([
            'order_payments.id',
            'order_payments.order_id',
            'order_payments.amount',
            'order_payments.method',
            'order_payments.status',
            'order_payments.reference',
            'order_payments.paid_at',
            'order_payments.created_at',
            'finance_orders.number',
            'finance_orders.customer_name',
            'finance_orders.status as order_status',
            'finance_orders.total as order_total',
            'finance_orders.finance_status',
        ])->orderByDesc('order_payments.created_at');
    }

    public function availablePaymentMethods(int $tenantId): array
    {
        return DB::table('order_payments')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('method')
            ->where('method', '<>', '')
            ->distinct()
            ->orderBy('method')
            ->pluck('method')
            ->all();
    }

    private function filteredOrdersQuery(int $tenantId, array $range, array $filters): Builder
    {
        $query = DB::query()
            ->fromSub($this->baseOrdersQuery($tenantId), 'finance_orders')
            ->whereBetween('finance_orders.placed_at', [$range['start'], $range['end']]);

        if (! empty($filters['payment_status'])) {
            $query->where('finance_orders.finance_status', $filters['payment_status']);
        }

        if (! empty($filters['payment_method'])) {
            $method = $filters['payment_method'];
            $query->whereExists(function (Builder $subquery) use ($tenantId, $method) {
                $subquery->selectRaw('1')
                    ->from('order_payments')
                    ->whereColumn('order_payments.order_id', 'finance_orders.id')
                    ->where('order_payments.tenant_id', $tenantId)
                    ->where('order_payments.method', $method);
            });
        }

        return $query;
    }

    private function baseOrdersQuery(int $tenantId): Builder
    {
        $payments = DB::table('order_payments')
            ->where('tenant_id', $tenantId)
            ->groupBy('order_id')
            ->select('order_id')
            ->selectRaw('
                COUNT(*) as attempt_count,
                SUM(CASE WHEN status = ? THEN amount ELSE 0 END) as paid_amount,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as canceled_count,
                GROUP_CONCAT(DISTINCT method) as payment_methods
            ', ['paid', 'pending', 'failed', 'canceled']);

        $refunds = DB::table('order_refunds')
            ->join('order_payments', 'order_payments.id', '=', 'order_refunds.order_payment_id')
            ->where('order_refunds.tenant_id', $tenantId)
            ->where('order_payments.tenant_id', $tenantId)
            ->groupBy('order_payments.order_id')
            ->select('order_payments.order_id')
            ->selectRaw('
                SUM(CASE WHEN order_refunds.status = ? THEN order_refunds.amount ELSE 0 END) as refunded_amount,
                SUM(CASE WHEN order_refunds.status = ? THEN 1 ELSE 0 END) as pending_refunds
            ', ['refunded', 'pending']);

        $paidAmount = 'COALESCE(payment_summary.paid_amount, 0)';
        $refundedAmount = 'COALESCE(refund_summary.refunded_amount, 0)';
        $financeStatus = "
            CASE
                WHEN {$paidAmount} > 0 AND {$refundedAmount} >= {$paidAmount}
                    AND COALESCE(payment_summary.pending_count, 0) = 0 THEN 'refunded'
                WHEN {$refundedAmount} > 0
                    AND COALESCE(payment_summary.pending_count, 0) = 0 THEN 'partially_refunded'
                WHEN {$paidAmount} >= orders.total AND {$paidAmount} > 0 THEN 'paid'
                WHEN {$paidAmount} > 0 THEN 'partially_paid'
                WHEN orders.status = 'canceled' THEN 'canceled'
                WHEN COALESCE(payment_summary.pending_count, 0) > 0 THEN 'pending'
                WHEN COALESCE(payment_summary.failed_count, 0) > 0 THEN 'failed'
                WHEN COALESCE(payment_summary.canceled_count, 0) > 0 THEN 'canceled'
                ELSE 'untracked'
            END
        ";

        return DB::table('orders')
            ->leftJoinSub($payments, 'payment_summary', 'payment_summary.order_id', '=', 'orders.id')
            ->leftJoinSub($refunds, 'refund_summary', 'refund_summary.order_id', '=', 'orders.id')
            ->where('orders.tenant_id', $tenantId)
            ->select([
                'orders.id',
                'orders.tenant_id',
                'orders.number',
                'orders.customer_name',
                'orders.status',
                'orders.placed_at',
                'orders.payment_method as original_payment_method',
                'orders.subtotal',
                'orders.delivery_fee',
                'orders.discount',
                'orders.total',
            ])
            ->selectRaw("
                {$paidAmount} as paid_amount,
                {$refundedAmount} as refunded_amount,
                COALESCE(payment_summary.attempt_count, 0) as attempt_count,
                COALESCE(refund_summary.pending_refunds, 0) as pending_refunds,
                COALESCE(NULLIF(payment_summary.payment_methods, ''), orders.payment_method, '') as payment_methods,
                {$financeStatus} as finance_status
            ");
    }

    private function revenueForRange(int $tenantId, CarbonImmutable $start, CarbonImmutable $end, array $filters): string
    {
        $query = $this->filteredOrdersQuery($tenantId, [
            'start' => $start,
            'end' => $end,
        ], $filters);

        return (string) ($query->where('finance_orders.status', '<>', 'canceled')->sum('finance_orders.total') ?: '0.00');
    }

    private function paymentTotals(int $tenantId, array $range, array $filters): array
    {
        $totals = $this->paidPaymentsQuery($tenantId, $range, $filters)
            ->selectRaw('
                COALESCE(SUM(order_payments.amount), 0) as amount,
                COUNT(DISTINCT order_payments.order_id) as order_count
            ')
            ->first();

        return [
            'amount' => $this->money($this->toCents((string) $totals->amount)),
            'order_count' => (int) $totals->order_count,
        ];
    }

    private function refundTotals(int $tenantId, array $range, array $filters): array
    {
        $totals = $this->completedRefundsQuery($tenantId, $range, $filters)
            ->selectRaw('
                COALESCE(SUM(order_refunds.amount), 0) as amount,
                COUNT(DISTINCT order_payments.order_id) as order_count
            ')
            ->first();

        return [
            'amount' => $this->money($this->toCents((string) $totals->amount)),
            'order_count' => (int) $totals->order_count,
        ];
    }

    private function paidPaymentsQuery(int $tenantId, array $range, array $filters): Builder
    {
        $query = DB::table('order_payments')
            ->joinSub($this->baseOrdersQuery($tenantId), 'finance_orders', 'finance_orders.id', '=', 'order_payments.order_id')
            ->where('order_payments.tenant_id', $tenantId)
            ->where('order_payments.status', 'paid')
            ->whereBetween('order_payments.paid_at', [$range['start'], $range['end']]);

        if (! empty($filters['payment_method'])) {
            $query->where('order_payments.method', $filters['payment_method']);
        }

        if (! empty($filters['payment_status'])) {
            $query->where('finance_orders.finance_status', $filters['payment_status']);
        }

        return $query;
    }

    private function completedRefundsQuery(int $tenantId, array $range, array $filters): Builder
    {
        $query = DB::table('order_refunds')
            ->join('order_payments', 'order_payments.id', '=', 'order_refunds.order_payment_id')
            ->joinSub($this->baseOrdersQuery($tenantId), 'finance_orders', 'finance_orders.id', '=', 'order_payments.order_id')
            ->where('order_refunds.tenant_id', $tenantId)
            ->where('order_payments.tenant_id', $tenantId)
            ->where('order_refunds.status', 'refunded')
            ->whereBetween('order_refunds.processed_at', [$range['start'], $range['end']]);

        if (! empty($filters['payment_method'])) {
            $query->where('order_payments.method', $filters['payment_method']);
        }

        if (! empty($filters['payment_status'])) {
            $query->where('finance_orders.finance_status', $filters['payment_status']);
        }

        return $query;
    }

    private function getSalesChart(int $tenantId, array $range, array $filters): array
    {
        $buckets = $this->chartBuckets($range);
        $expression = $this->bucketExpression($range['granularity']);
        $sales = $this->filteredOrdersQuery($tenantId, $range, $filters)
            ->where('finance_orders.status', '<>', 'canceled')
            ->selectRaw("{$expression} as bucket, SUM(finance_orders.total) as amount")
            ->groupBy('bucket')
            ->pluck('amount', 'bucket');

        return $this->formatChart($buckets, $sales);
    }

    private function getDeliveryChart(int $tenantId, array $range, array $filters): array
    {
        $buckets = $this->chartBuckets($range);
        $expression = $this->bucketExpression($range['granularity']);
        $fees = $this->filteredOrdersQuery($tenantId, $range, $filters)
            ->where('finance_orders.status', '<>', 'canceled')
            ->selectRaw("{$expression} as bucket, SUM(finance_orders.delivery_fee) as amount")
            ->groupBy('bucket')
            ->pluck('amount', 'bucket');

        return $this->formatChart($buckets, $fees);
    }

    private function getPaymentMethods(int $tenantId, array $range, array $filters): array
    {
        $totalCents = $this->toCents($this->paymentTotals($tenantId, $range, $filters)['amount']);

        return $this->paidPaymentsQuery($tenantId, $range, $filters)
            ->selectRaw("
                COALESCE(NULLIF(order_payments.method, ''), 'Não informado') as method,
                COUNT(DISTINCT order_payments.order_id) as orders,
                SUM(order_payments.amount) as amount
            ")
            ->groupBy('order_payments.method')
            ->orderByDesc('amount')
            ->get()
            ->map(function ($row) use ($totalCents) {
                return [
                    'method' => $row->method,
                    'orders' => (int) $row->orders,
                    'amount' => (float) $row->amount,
                    'share' => $totalCents > 0
                        ? ($this->toCents((string) $row->amount) / $totalCents) * 100
                        : 0,
                ];
            })
            ->all();
    }

    private function getPaymentStatuses(int $tenantId, array $range, array $filters): array
    {
        return DB::table('order_payments')
            ->joinSub($this->baseOrdersQuery($tenantId), 'finance_orders', 'finance_orders.id', '=', 'order_payments.order_id')
            ->where('order_payments.tenant_id', $tenantId)
            ->whereBetween('order_payments.created_at', [$range['start'], $range['end']])
            ->when(! empty($filters['payment_method']), fn ($query) => $query->where('order_payments.method', $filters['payment_method']))
            ->when(! empty($filters['payment_status']), fn ($query) => $query->where('finance_orders.finance_status', $filters['payment_status']))
            ->selectRaw('order_payments.status, COUNT(*) as attempts, SUM(order_payments.amount) as amount')
            ->groupBy('order_payments.status')
            ->orderBy('order_payments.status')
            ->get()
            ->all();
    }

    private function getRefundStatuses(int $tenantId, array $range, array $filters): array
    {
        return DB::table('order_refunds')
            ->join('order_payments', 'order_payments.id', '=', 'order_refunds.order_payment_id')
            ->joinSub($this->baseOrdersQuery($tenantId), 'finance_orders', 'finance_orders.id', '=', 'order_payments.order_id')
            ->where('order_refunds.tenant_id', $tenantId)
            ->where('order_payments.tenant_id', $tenantId)
            ->whereBetween('order_refunds.created_at', [$range['start'], $range['end']])
            ->when(! empty($filters['payment_method']), fn ($query) => $query->where('order_payments.method', $filters['payment_method']))
            ->when(! empty($filters['payment_status']), fn ($query) => $query->where('finance_orders.finance_status', $filters['payment_status']))
            ->selectRaw('order_refunds.status, COUNT(*) as refunds, SUM(order_refunds.amount) as amount')
            ->groupBy('order_refunds.status')
            ->orderBy('order_refunds.status')
            ->get()
            ->all();
    }

    private function getOrderSummary(int $tenantId, array $range, array $filters): object
    {
        return (clone $this->filteredOrdersQuery($tenantId, $range, $filters))
            ->where('finance_orders.status', '<>', 'canceled')
            ->selectRaw('
                COALESCE(SUM(finance_orders.subtotal), 0) as product_subtotal,
                COALESCE(SUM(finance_orders.discount), 0) as discounts,
                COALESCE(SUM(finance_orders.delivery_fee), 0) as delivery_fees,
                COALESCE(SUM(finance_orders.total), 0) as order_total,
                COALESCE(SUM(finance_orders.paid_amount), 0) as paid_amount,
                COALESCE(SUM(finance_orders.refunded_amount), 0) as refunded_amount
            ')
            ->first();
    }

    private function bucketExpression(string $granularity): string
    {
        $driver = DB::connection()->getDriverName();

        if ($granularity === 'hour') {
            return $driver === 'sqlite'
                ? "strftime('%Y-%m-%d %H:00:00', finance_orders.placed_at)"
                : "DATE_FORMAT(finance_orders.placed_at, '%Y-%m-%d %H:00:00')";
        }

        if ($granularity === 'month') {
            return $driver === 'sqlite'
                ? "strftime('%Y-%m', finance_orders.placed_at)"
                : "DATE_FORMAT(finance_orders.placed_at, '%Y-%m')";
        }

        return $driver === 'sqlite'
            ? 'DATE(finance_orders.placed_at)'
            : 'DATE(finance_orders.placed_at)';
    }

    private function chartBuckets(array $range): array
    {
        $buckets = [];

        if ($range['granularity'] === 'hour') {
            for ($hour = 0; $hour < 24; $hour++) {
                $bucket = $range['start']->setTime($hour, 0)->format('Y-m-d H:00:00');
                $buckets[$bucket] = sprintf('%02dh', $hour);
            }

            return $buckets;
        }

        $cursor = $range['start']->startOfDay();
        $lastDay = $range['end']->startOfDay();

        while ($cursor <= $lastDay) {
            $key = $range['granularity'] === 'month'
                ? $cursor->startOfMonth()->format('Y-m')
                : $cursor->format('Y-m-d');
            $buckets[$key] = $range['granularity'] === 'month'
                ? $cursor->format('m/Y')
                : $cursor->format('d/m');
            $cursor = $range['granularity'] === 'month'
                ? $cursor->startOfMonth()->addMonth()
                : $cursor->addDay();
        }

        return $buckets;
    }

    private function formatChart(array $buckets, \Illuminate\Support\Collection $values): array
    {
        $labels = [];
        $amounts = [];

        foreach ($buckets as $key => $label) {
            $labels[] = $label;
            $amounts[] = (float) ($values[$key] ?? 0);
        }

        return ['labels' => $labels, 'values' => $amounts];
    }

    private function toCents(string $amount): int
    {
        $value = trim($amount);

        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw new \LogicException('O banco retornou um valor monetário inválido.');
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function money(int $cents): string
    {
        $negative = $cents < 0;
        $cents = abs($cents);
        $value = intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);

        return $negative ? '-'.$value : $value;
    }
}
