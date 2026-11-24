<?php

namespace App\Modules\Admin\Presentation\Controllers;

use App\Models\Order;
use App\Models\Tenant;
use App\Modules\Admin\Business\DashboardService;
use App\Services\ThemeManager;
use Illuminate\Http\RedirectResponse;
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
}
