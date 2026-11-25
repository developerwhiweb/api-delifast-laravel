<?php

namespace App\Modules\Admin\Business;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardAnalyticsService
{
    private const PERIODS = [
        'today' => 1,
        '7' => 7,
        '30' => 30,
        '90' => 90,
    ];

    public function getDashboardData(int $tenantId, string $period = '7'): array
    {
        abort_unless(isset(self::PERIODS[$period]), 422, 'Período inválido.');

        $timezone = config('app.timezone');
        $today = now($timezone)->startOfDay();
        $periodDays = self::PERIODS[$period];
        $periodStart = $today->copy()->subDays($periodDays - 1);
        $periodEnd = $today->copy()->endOfDay();

        return [
            'period' => $period,
            'metrics' => $this->getMetrics($tenantId, $today),
            'chart' => $this->getSalesChart($tenantId, $period, $periodStart, $periodEnd),
            'products' => $this->getTopProducts($tenantId, $periodStart, $periodEnd),
            'recentOrders' => $this->getRecentOrders($tenantId),
        ];
    }

    private function getMetrics(int $tenantId, Carbon $today): array
    {
        $todayMetrics = $this->aggregateOrders(
            $tenantId,
            $today->copy()->startOfDay(),
            $today->copy()->endOfDay()
        );
        $previousMetrics = $this->aggregateOrders(
            $tenantId,
            $today->copy()->subDay()->startOfDay(),
            $today->copy()->subDay()->endOfDay()
        );

        $previousRevenue = (float) $previousMetrics->revenue;
        $revenue = (float) $todayMetrics->revenue;

        return [
            'revenue' => $revenue,
            'orders' => (int) $todayMetrics->orders,
            'average_ticket' => $todayMetrics->valid_orders > 0
                ? $revenue / (int) $todayMetrics->valid_orders
                : 0.0,
            'waiting' => (int) $todayMetrics->waiting,
            'preparing' => (int) $todayMetrics->preparing,
            'out_for_delivery' => (int) $todayMetrics->out_for_delivery,
            'canceled' => (int) $todayMetrics->canceled,
            'revenue_change' => $previousRevenue > 0
                ? (($revenue - $previousRevenue) / $previousRevenue) * 100
                : null,
        ];
    }

    private function aggregateOrders(int $tenantId, Carbon $start, Carbon $end): object
    {
        return DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->whereBetween('placed_at', [$start, $end])
            ->selectRaw(
                'COUNT(*) as orders,
                SUM(CASE WHEN status <> ? THEN 1 ELSE 0 END) as valid_orders,
                COALESCE(SUM(CASE WHEN status <> ? THEN total ELSE 0 END), 0) as revenue,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as waiting,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as preparing,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as out_for_delivery,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as canceled',
                ['canceled', 'canceled', 'pending', 'preparing', 'out_for_delivery', 'canceled']
            )
            ->first();
    }

    private function getSalesChart(
        int $tenantId,
        string $period,
        Carbon $start,
        Carbon $end
    ): array {
        $isToday = $period === 'today';
        $driver = DB::connection()->getDriverName();
        $bucketExpression = $isToday
            ? ($driver === 'sqlite'
                ? "strftime('%Y-%m-%d %H:00:00', placed_at)"
                : "DATE_FORMAT(placed_at, '%Y-%m-%d %H:00:00')")
            : ($driver === 'sqlite' ? 'DATE(placed_at)' : 'DATE(placed_at)');

        $sales = DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->where('status', '<>', 'canceled')
            ->whereBetween('placed_at', [$start, $end])
            ->selectRaw("{$bucketExpression} as bucket, COALESCE(SUM(total), 0) as revenue")
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->pluck('revenue', 'bucket');

        $labels = [];
        $values = [];

        if ($isToday) {
            for ($hour = 0; $hour < 24; $hour++) {
                $bucket = $start->copy()->setTime($hour, 0)->format('Y-m-d H:00:00');
                $labels[] = sprintf('%02dh', $hour);
                $values[] = (float) ($sales[$bucket] ?? 0);
            }
        } else {
            $cursor = $start->copy()->startOfDay();
            $lastDay = $end->copy()->startOfDay();

            while ($cursor->lte($lastDay)) {
                $bucket = $cursor->format('Y-m-d');
                $labels[] = $cursor->format('d/m');
                $values[] = (float) ($sales[$bucket] ?? 0);
                $cursor->addDay();
            }
        }

        return compact('labels', 'values');
    }

    private function getTopProducts(int $tenantId, Carbon $start, Carbon $end): array
    {
        return DB::table('order_items')
            ->join('orders', function ($join) use ($tenantId) {
                $join->on('orders.id', '=', 'order_items.order_id')
                    ->where('orders.tenant_id', '=', $tenantId);
            })
            ->where('order_items.tenant_id', $tenantId)
            ->where('orders.status', '<>', 'canceled')
            ->whereBetween('orders.placed_at', [$start, $end])
            ->select(
                'order_items.product_title',
                'order_items.product_image',
                'order_items.product_id'
            )
            ->selectRaw('SUM(order_items.quantity) as quantity_sold, SUM(order_items.total_price) as revenue')
            ->groupBy(
                'order_items.product_title',
                'order_items.product_image',
                'order_items.product_id'
            )
            ->orderByDesc('quantity_sold')
            ->limit(5)
            ->get()
            ->all();
    }

    private function getRecentOrders(int $tenantId): array
    {
        return DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get([
                'id',
                'number',
                'customer_name',
                'placed_at',
                'total',
                'payment_method',
                'status',
            ])
            ->all();
    }
}
