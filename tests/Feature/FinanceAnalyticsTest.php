<?php

namespace Tests\Feature;

use App\Events\OrderRealtimeChanged;
use App\Models\Tenant;
use App\Modules\Admin\Business\Exports\FinanceSummaryExport;
use App\Modules\Admin\Business\FinanceAnalyticsService;
use App\Modules\Admin\Business\PaymentLedgerService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinanceAnalyticsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.timezone' => 'America/Sao_Paulo',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('domain')->nullable();
            $table->string('database')->nullable();
            $table->string('timezone', 64)->default('America/Sao_Paulo');
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('number')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('customer_email')->nullable();
            $table->json('delivery_address')->nullable();
            $table->string('delivery_method')->nullable();
            $table->string('status');
            $table->string('payment_status')->default('pending');
            $table->string('payment_method')->nullable();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('coupon_discount', 10, 2)->default(0);
            $table->string('coupon_code')->nullable();
            $table->decimal('total', 10, 2);
            $table->text('notes')->nullable();
            $table->timestamp('placed_at');
            $table->timestamps();
        });

        Schema::create('order_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('order_id');
            $table->decimal('amount', 10, 2);
            $table->string('method')->nullable();
            $table->string('status')->default('pending');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('idempotency_key');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_refunds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('order_payment_id');
            $table->decimal('amount', 10, 2);
            $table->string('status')->default('pending');
            $table->text('reason')->nullable();
            $table->uuid('idempotency_key');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->nullableMorphs('subject');
            $table->nullableMorphs('causer');
            $table->json('properties')->nullable();
            $table->string('event')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->timestamps();
        });

        Carbon::setTestNow(Carbon::parse('2026-11-25 12:00:00', 'America/Sao_Paulo'));
        Tenant::query()->create([
            'name' => 'Estabelecimento A',
            'slug' => 'estabelecimento-a',
            'timezone' => 'America/Sao_Paulo',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::purge('sqlite');

        parent::tearDown();
    }

    public function test_finance_overview_uses_confirmed_ledger_values_and_isolates_tenants(): void
    {
        $partialOrder = $this->createOrder(1, 'delivered', 120, '2026-11-25 09:00:00');
        $paidOrder = $this->createOrder(1, 'delivered', 60, '2026-11-25 10:00:00');
        $this->createOrder(1, 'canceled', 50, '2026-11-25 11:00:00');
        $otherTenantOrder = $this->createOrder(2, 'delivered', 9000, '2026-11-25 11:00:00');

        $partialPayment = $this->createPayment($partialOrder, 1, 100, 'paid', '2026-11-25 10:00:00');
        $this->createPayment($partialOrder, 1, 20, 'pending');
        $this->createPayment($paidOrder, 1, 60, 'paid', '2026-11-25 11:00:00');
        $otherTenantPayment = $this->createPayment($otherTenantOrder, 2, 9000, 'paid', '2026-11-25 11:00:00');

        DB::table('order_refunds')->insert([
            'tenant_id' => 1,
            'order_payment_id' => $partialPayment,
            'amount' => 20,
            'status' => 'refunded',
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            'processed_at' => '2026-11-25 11:30:00',
            'created_at' => '2026-11-25 11:00:00',
            'updated_at' => '2026-11-25 11:30:00',
        ]);
        $this->assertNotNull($otherTenantPayment);

        $range = app(FinanceAnalyticsService::class)->resolveRange(['period' => 'today'], 'America/Sao_Paulo');
        $analytics = app(FinanceAnalyticsService::class);
        $this->assertSame(3, $analytics->ordersQuery(1, $range)->count());
        $overview = $analytics->getOverview(1, $range);

        $this->assertSame('180.00', $overview['metrics']['sales_total']);
        $this->assertSame('160.00', $overview['metrics']['received_total']);
        $this->assertSame('20.00', $overview['metrics']['pending_total']);
        $this->assertSame('20.00', $overview['metrics']['refunded_total']);
        $this->assertSame('140.00', $overview['metrics']['net_received']);
        $this->assertSame(3, $overview['metrics']['order_count']);
        $this->assertSame(1, $overview['metrics']['paid_orders']);
        $this->assertSame(1, $overview['metrics']['pending_orders']);
        $this->assertSame(1, $overview['metrics']['canceled_orders']);
        $this->assertSame(0, $overview['metrics']['untracked_orders']);
        $this->assertEqualsWithDelta(180, array_sum($overview['sales_chart']['values']), 0.01);
    }

    public function test_tenant_timezone_sets_the_local_calendar_day_for_reports(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-25 01:00:00', 'UTC'));

        $range = app(FinanceAnalyticsService::class)->resolveRange(['period' => 'today'], 'America/Los_Angeles');

        $this->assertSame('2026-11-24 00:00:00', $range['start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-24 23:59:59', $range['end']->format('Y-m-d H:i:s'));
    }

    public function test_summary_exports_generate_xlsx_and_pdf_documents(): void
    {
        $range = app(FinanceAnalyticsService::class)->resolveRange(['period' => 'today'], 'America/Sao_Paulo');
        $export = new FinanceSummaryExport([
            'metrics' => [
                'sales_total' => '100.00',
                'received_total' => '80.00',
                'pending_total' => '20.00',
                'refunded_total' => '0.00',
                'net_received' => '80.00',
                'delivery_fees' => '0.00',
                'discounts' => '0.00',
                'order_count' => 1,
                'paid_orders' => 0,
                'pending_orders' => 1,
                'canceled_orders' => 0,
                'untracked_orders' => 0,
            ],
        ], $range);

        $xlsx = Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX);
        $pdf = Pdf::loadView('admin.finance.exports.pdf', [
            'headers' => $export->headings(),
            'rows' => $export->array(),
            'title' => 'Resumo financeiro',
            'range' => $range,
        ])->setPaper('a4', 'landscape')->output();

        $this->assertStringStartsWith('PK', $xlsx);
        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function test_ledger_is_idempotent_supports_partial_refunds_and_rejects_over_refunding(): void
    {
        Event::fake([OrderRealtimeChanged::class]);
        $orderId = $this->createOrder(1, 'delivered', 100, '2026-11-25 09:00:00');
        $ledger = app(PaymentLedgerService::class);
        $idempotencyKey = (string) \Illuminate\Support\Str::uuid();

        $payment = $ledger->createAttempt(1, $orderId, [
            'amount' => '60.00',
            'method' => 'pix',
            'idempotency_key' => $idempotencyKey,
        ]);
        $again = $ledger->createAttempt(1, $orderId, [
            'amount' => '60.00',
            'method' => 'pix',
            'idempotency_key' => $idempotencyKey,
        ]);

        $this->assertSame($payment->id, $again->id);
        $this->assertDatabaseCount('order_payments', 1);
        $ledger->updatePaymentStatus(1, $payment->id, 'paid');
        $refund = $ledger->createRefund(1, $payment->id, [
            'amount' => '20.00',
            'reason' => 'Item indisponível',
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ]);
        $ledger->updateRefundStatus(1, $refund->id, 'refunded');

        $this->assertSame('partially_refunded', DB::table('orders')->where('id', $orderId)->value('payment_status'));
        $ledger->createAttempt(1, $orderId, [
            'amount' => '40.00',
            'method' => 'pix',
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ]);
        $this->assertSame('partially_paid', DB::table('orders')->where('id', $orderId)->value('payment_status'));

        $this->expectException(ValidationException::class);
        $ledger->createRefund(1, $payment->id, [
            'amount' => '50.00',
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ]);
    }

    private function createOrder(int $tenantId, string $status, float $total, string $placedAt): int
    {
        return DB::table('orders')->insertGetId([
            'tenant_id' => $tenantId,
            'number' => 'ORD-'.uniqid(),
            'customer_name' => 'Cliente',
            'status' => $status,
            'payment_status' => 'pending',
            'payment_method' => 'pix',
            'subtotal' => $total,
            'delivery_fee' => 0,
            'discount' => 0,
            'coupon_discount' => 0,
            'total' => $total,
            'placed_at' => $placedAt,
            'created_at' => $placedAt,
            'updated_at' => $placedAt,
        ]);
    }

    private function createPayment(
        int $orderId,
        int $tenantId,
        float $amount,
        string $status,
        ?string $paidAt = null
    ): int {
        return DB::table('order_payments')->insertGetId([
            'tenant_id' => $tenantId,
            'order_id' => $orderId,
            'amount' => $amount,
            'method' => 'pix',
            'status' => $status,
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            'paid_at' => $paidAt,
            'created_at' => '2026-11-25 10:00:00',
            'updated_at' => '2026-11-25 10:00:00',
        ]);
    }
}
