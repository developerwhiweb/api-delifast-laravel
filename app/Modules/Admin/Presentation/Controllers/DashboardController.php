<?php

namespace App\Modules\Admin\Presentation\Controllers;

use App\Events\OrderRealtimeChanged;
use App\Models\Order;
use App\Models\Tenant;
use App\Modules\Admin\Business\DashboardService;
use App\Services\ThemeManager;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DashboardController
{
    protected ThemeManager $theme;
    public function __construct(protected DashboardService $service, ThemeManager $theme)
    {
        $this->theme = $theme;
    }

    public function index(): View|RedirectResponse
    {
        $data = $this->service->getDashboardData($this->theme);
        $user = $data['user'] ?? null;

        if ($user) {
            return view('admin.dashboard', $data);
        }

        return redirect()->route('admin.login');
    }
    public function administration(): View|RedirectResponse
    {
        $data = $this->service->getDashboardData($this->theme);
        $tenantId = Tenant::current()?->id;
        $orders = $this->ordersForTenant($tenantId);

        return view('admin.administration', array_merge($data, [
            'orders' => $orders,
            'tenantId' => $tenantId,
            'ordersSnapshotUrl' => route('admin.dashboard.administration.orders.snapshot'),
        ]));
    }

    public function administrationSnapshot(Request $request): JsonResponse
    {
        $tenantId = Tenant::current()?->id;
        $orders = $this->ordersForTenant($tenantId);
        $html = view('admin.partials.administration-orders-content', compact('orders'))->render();

        return response()->json([
            'html' => $html,
            'synced_at' => now()->toISOString(),
        ]);
    }

    public function updateOrderStatus(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:confirmed,preparing,ready,out_for_delivery,delivered,canceled'],
        ]);
        $tenantId = Tenant::current()?->id;

        abort_if(! $tenantId, 404);

        $order = DB::transaction(function () use ($id, $tenantId, $validated): Order {
            $order = Order::query()
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->findOrFail($id);

            if ($order->status === $validated['status']) {
                return $order;
            }

            $allowedTransitions = [
                'pending' => ['confirmed', 'canceled'],
                'confirmed' => ['preparing'],
                'preparing' => ['ready'],
                'ready' => [$order->delivery_method === 'delivery' ? 'out_for_delivery' : 'delivered'],
                'out_for_delivery' => ['delivered'],
            ];

            if (! in_array($validated['status'], $allowedTransitions[$order->status] ?? [], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Esta ação não está disponível para o status atual do pedido.',
                ]);
            }

            $order->update(['status' => $validated['status']]);

            return $order;
        });

        if ($order->wasChanged('status')) {
            OrderRealtimeChanged::dispatch(
                (int) $tenantId,
                (int) $order->id,
                $order->status,
                'status',
                $order->updated_at?->toISOString()
            );
        }

        $messages = [
            'confirmed' => 'Pedido aceito.',
            'preparing' => 'Preparação iniciada.',
            'ready' => 'Pedido marcado como pronto.',
            'out_for_delivery' => 'Pedido saiu para entrega.',
            'delivered' => 'Pedido finalizado.',
            'canceled' => 'Pedido recusado.',
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $messages[$order->status],
                'order_id' => $order->id,
                'status' => $order->status,
                'updated_at' => $order->updated_at?->toISOString(),
            ]);
        }

        return back()->with('success', $messages[$order->status]);
    }

    private function ordersForTenant(?int $tenantId): LengthAwarePaginator
    {
        return Order::query()
            ->where('tenant_id', $tenantId ?? 0)
            ->with('items')
            ->latest('placed_at')
            ->paginate(50)
            ->withQueryString();
    }
}
