<?php

namespace Tests\Feature;

use App\Modules\Admin\Business\DashboardAnalyticsService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardAnalyticsTest extends TestCase
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

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('number');
            $table->string('customer_name');
            $table->string('status');
            $table->string('payment_status')->default('pending');
            $table->string('payment_method')->nullable();
            $table->decimal('total', 10, 2);
            $table->timestamp('placed_at');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_title');
            $table->string('product_image')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('total_price', 10, 2);
        });

        Carbon::setTestNow(Carbon::parse('2026-11-25 12:00:00', 'America/Sao_Paulo'));

        $this->createOrder(1, 'pending', 40, '2026-11-25 09:00:00');
        $this->createOrder(1, 'preparing', 60, '2026-11-25 10:00:00');
        $this->createOrder(1, 'out_for_delivery', 20, '2026-11-25 11:00:00');
        $canceledId = $this->createOrder(1, 'canceled', 90, '2026-11-25 11:30:00');
        $this->createOrder(1, 'delivered', 50, '2026-11-24 12:00:00');
        $this->createOrder(2, 'delivered', 1000, '2026-11-25 11:00:00');

        DB::table('order_items')->insert([
            [
                'tenant_id' => 1,
                'order_id' => 1,
                'product_id' => 10,
                'product_title' => 'Pizza',
                'product_image' => null,
                'quantity' => 2,
                'total_price' => 20,
            ],
            [
                'tenant_id' => 1,
                'order_id' => 2,
                'product_id' => 10,
                'product_title' => 'Pizza',
                'product_image' => null,
                'quantity' => 1,
                'total_price' => 10,
            ],
            [
                'tenant_id' => 1,
                'order_id' => $canceledId,
                'product_id' => 10,
                'product_title' => 'Pizza',
                'product_image' => null,
                'quantity' => 10,
                'total_price' => 100,
            ],
            [
                'tenant_id' => 2,
                'order_id' => 6,
                'product_id' => 20,
                'product_title' => 'Other tenant product',
                'product_image' => null,
                'quantity' => 99,
                'total_price' => 990,
            ],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::purge('sqlite');

        parent::tearDown();
    }

    public function test_dashboard_aggregates_real_order_snapshots_and_isolates_tenant_data(): void
    {
        $data = app(DashboardAnalyticsService::class)->getDashboardData(1, 'today');

        $this->assertSame(120.0, $data['metrics']['revenue']);
        $this->assertSame(4, $data['metrics']['orders']);
        $this->assertSame(40.0, $data['metrics']['average_ticket']);
        $this->assertSame(1, $data['metrics']['waiting']);
        $this->assertSame(1, $data['metrics']['preparing']);
        $this->assertSame(1, $data['metrics']['out_for_delivery']);
        $this->assertSame(1, $data['metrics']['canceled']);
        $this->assertEqualsWithDelta(140.0, $data['metrics']['revenue_change'], 0.01);
        $this->assertSame(24, count($data['chart']['labels']));
        $this->assertSame(120.0, array_sum($data['chart']['values']));
        $this->assertSame('Pizza', $data['products'][0]->product_title);
        $this->assertSame(3, (int) $data['products'][0]->quantity_sold);
        $this->assertSame(30.0, (float) $data['products'][0]->revenue);
        $this->assertNotContains('Other tenant product', array_column($data['products'], 'product_title'));
        $this->assertNotContains(6, array_column($data['recentOrders'], 'id'));
    }

    public function test_empty_day_returns_zero_ticket_and_no_revenue_comparison_baseline(): void
    {
        $data = app(DashboardAnalyticsService::class)->getDashboardData(3, 'today');

        $this->assertSame(0.0, $data['metrics']['revenue']);
        $this->assertSame(0, $data['metrics']['orders']);
        $this->assertSame(0.0, $data['metrics']['average_ticket']);
        $this->assertNull($data['metrics']['revenue_change']);
        $this->assertSame([], $data['products']);
        $this->assertSame([], $data['recentOrders']);
    }

    public function test_period_filters_return_the_requested_number_of_buckets(): void
    {
        $service = app(DashboardAnalyticsService::class);

        foreach (['7' => 7, '30' => 30, '90' => 90] as $period => $bucketCount) {
            $data = $service->getDashboardData(1, $period);

            $this->assertCount($bucketCount, $data['chart']['labels']);
            $this->assertSame(170.0, array_sum($data['chart']['values']));
        }
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
            'total' => $total,
            'placed_at' => $placedAt,
        ]);
    }
}
