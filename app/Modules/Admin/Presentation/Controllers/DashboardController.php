<?php

namespace App\Modules\Admin\Presentation\Controllers;

use App\Models\Order;
use App\Models\Tenant;
use App\Modules\Admin\Business\DashboardService;
use App\Services\ThemeManager;
use Illuminate\Http\RedirectResponse;
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
        $tenant = Tenant::current();
        $orders = Order::query()
            ->where('tenant_id', $tenant?->id ?? 0)
            ->with('items')
            ->latest('placed_at')
            ->paginate(50)
            ->withQueryString();

        return view('admin.administration', array_merge($data, compact('orders')));
    }

    public function updateOrderStatus(Request $request, int $id): RedirectResponse
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

        $messages = [
            'confirmed' => 'Pedido aceito.',
            'preparing' => 'Preparação iniciada.',
            'ready' => 'Pedido marcado como pronto.',
            'out_for_delivery' => 'Pedido saiu para entrega.',
            'delivered' => 'Pedido finalizado.',
            'canceled' => 'Pedido recusado.',
        ];

        return back()->with('success', $messages[$order->status]);
    }
}
