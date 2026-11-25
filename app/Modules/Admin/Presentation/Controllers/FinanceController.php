<?php

namespace App\Modules\Admin\Presentation\Controllers;

use App\Models\OrderPayment;
use App\Models\Tenant;
use App\Modules\Admin\Business\Exports\FinanceReportExport;
use App\Modules\Admin\Business\Exports\FinanceSummaryExport;
use App\Modules\Admin\Business\FinanceAnalyticsService;
use App\Modules\Admin\Business\PaymentLedgerService;
use App\Services\ThemeManager;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController
{
    public function index(Request $request, FinanceAnalyticsService $analytics, ThemeManager $theme)
    {
        $this->authorizeFinance('visualizar');
        $tenant = $this->currentTenant();
        $filters = $this->validatedFilters($request, $analytics);
        $range = $analytics->resolveRange($filters, $tenant->timezone ?? config('app.timezone'));
        $overview = $analytics->getOverview((int) $tenant->id, $range, $filters);
        $orders = $this->loadOrders($analytics, (int) $tenant->id, $range, $filters);

        return view('admin.finance.index', [
            'theme' => $theme,
            'tenant' => $tenant,
            'filters' => $filters,
            'range' => $range,
            'overview' => $overview,
            'orders' => $orders,
            'paymentMethods' => $analytics->availablePaymentMethods((int) $tenant->id),
            'paymentStatuses' => FinanceAnalyticsService::PAYMENT_STATUSES,
        ]);
    }

    public function snapshot(Request $request, FinanceAnalyticsService $analytics): JsonResponse
    {
        $this->authorizeFinance('visualizar');
        $tenant = $this->currentTenant();
        $filters = $this->validatedFilters($request, $analytics);
        $range = $analytics->resolveRange($filters, $tenant->timezone ?? config('app.timezone'));
        $overview = $analytics->getOverview((int) $tenant->id, $range, $filters);
        $orders = $this->loadOrders($analytics, (int) $tenant->id, $range, $filters);
        $labels = [
            'pending' => 'Pendente',
            'paid' => 'Pago',
            'partially_paid' => 'Parcialmente pago',
            'failed' => 'Falhou',
            'canceled' => 'Cancelado',
            'refunded' => 'Reembolsado',
            'partially_refunded' => 'Parcialmente reembolsado',
            'untracked' => 'Sem lançamento',
        ];
        $classes = [
            'pending' => 'bg-warning-subtle text-warning',
            'paid' => 'bg-success-subtle text-success',
            'partially_paid' => 'bg-info-subtle text-info',
            'failed' => 'bg-danger-subtle text-danger',
            'canceled' => 'bg-secondary-subtle text-secondary',
            'refunded' => 'bg-primary-subtle text-primary',
            'partially_refunded' => 'bg-warning-subtle text-warning',
            'untracked' => 'bg-light text-muted',
        ];

        return response()->json([
            'metrics' => $overview['metrics'],
            'order_summary' => $overview['order_summary'],
            'sales_chart' => $overview['sales_chart'],
            'delivery_chart' => $overview['delivery_chart'],
            'payment_methods' => $overview['payment_methods'],
            'orders_html' => view('admin.finance.partials.orders-content', [
                'orders' => $orders,
                'filters' => $filters,
                'range' => $range,
                'financeLabels' => $labels,
                'financeClasses' => $classes,
            ])->render(),
            'synced_at' => now($range['timezone'])->toISOString(),
        ]);
    }

    public function createPayment(
        Request $request,
        int $order,
        PaymentLedgerService $ledger
    ): RedirectResponse {
        $this->authorizeFinance('editar');
        $tenant = $this->currentTenant();
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'regex:/^\\d+(?:\\.\\d{1,2})?$/'],
            'method' => ['nullable', 'string', 'max:80'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['required', 'uuid'],
        ]);

        $ledger->createAttempt((int) $tenant->id, $order, $data);

        return $this->backToFinance($request, 'Tentativa de pagamento registrada.');
    }

    public function updatePaymentStatus(
        Request $request,
        int $payment,
        PaymentLedgerService $ledger
    ): RedirectResponse {
        $this->authorizeFinance('editar');
        $tenant = $this->currentTenant();
        $data = $request->validate([
            'status' => ['required', Rule::in(['paid', 'failed', 'canceled'])],
        ]);

        $ledger->updatePaymentStatus((int) $tenant->id, $payment, $data['status']);

        return $this->backToFinance($request, 'Status do pagamento atualizado.');
    }

    public function createRefund(
        Request $request,
        int $payment,
        PaymentLedgerService $ledger
    ): RedirectResponse {
        $this->authorizeFinance('editar');
        $tenant = $this->currentTenant();
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'regex:/^\\d+(?:\\.\\d{1,2})?$/'],
            'reason' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['required', 'uuid'],
        ]);

        $ledger->createRefund((int) $tenant->id, $payment, $data);

        return $this->backToFinance($request, 'Solicitação de reembolso registrada.');
    }

    public function updateRefundStatus(
        Request $request,
        int $refund,
        PaymentLedgerService $ledger
    ): RedirectResponse {
        $this->authorizeFinance('editar');
        $tenant = $this->currentTenant();
        $data = $request->validate([
            'status' => ['required', Rule::in(['refunded', 'failed', 'canceled'])],
        ]);

        $ledger->updateRefundStatus((int) $tenant->id, $refund, $data['status']);

        return $this->backToFinance($request, 'Status do reembolso atualizado.');
    }

    public function export(
        Request $request,
        FinanceAnalyticsService $analytics
    ) {
        $this->authorizeFinance('visualizar');
        $exportOptions = $request->validate([
            'type' => ['required', Rule::in(['summary', 'orders', 'payments'])],
            'format' => ['required', Rule::in(['csv', 'xlsx', 'pdf'])],
        ]);
        $type = $exportOptions['type'];
        $format = $exportOptions['format'];
        abort_unless(in_array($type, ['summary', 'orders', 'payments'], true), 404);
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 404);

        $tenant = $this->currentTenant();
        $filters = $this->validatedFilters($request, $analytics);
        $range = $analytics->resolveRange($filters, $tenant->timezone ?? config('app.timezone'));
        $filename = 'financeiro-'.$type.'-'.$range['start']->format('Ymd').'-'.$range['end']->format('Ymd');

        if ($type === 'summary') {
            $export = new FinanceSummaryExport(
                $analytics->getOverview((int) $tenant->id, $range, $filters),
                $range
            );

            if ($format === 'xlsx') {
                return Excel::download($export, $filename.'.xlsx');
            }

            $headers = $export->headings();
            $rows = $export->array();

            if ($format === 'csv') {
                return $this->streamCsv($filename.'.csv', $headers, $rows);
            }

            return $this->renderPdf($filename.'.pdf', $headers, $rows, 'Resumo financeiro', $range);
        }

        $query = $type === 'orders'
            ? $analytics->ordersQuery((int) $tenant->id, $range, $filters)
            : $analytics->paymentAttemptsQuery((int) $tenant->id, $range, $filters);
        $export = new FinanceReportExport($query, $type);

        if ($format === 'xlsx') {
            return Excel::download($export, $filename.'.xlsx');
        }

        if ($format === 'csv') {
            return $this->streamQueryCsv($filename.'.csv', $export);
        }

        if ($query->count() > 2000) {
            throw ValidationException::withMessages([
                'export' => 'O PDF comporta até 2.000 registros. Use CSV ou Excel para exportações maiores.',
            ]);
        }

        $rows = $query->get()->map(fn ($row) => $export->map($row))->all();

        return $this->renderPdf($filename.'.pdf', $export->headings(), $rows, $type === 'orders' ? 'Pedidos' : 'Pagamentos', $range);
    }

    private function validatedFilters(Request $request, FinanceAnalyticsService $analytics): array
    {
        $filters = $request->validate([
            'period' => ['nullable', Rule::in(['today', 'yesterday', '7', '30', '90', 'this_month', 'last_month', 'custom'])],
            'start_date' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'payment_method' => ['nullable', 'string', 'max:80'],
            'payment_status' => ['nullable', Rule::in(FinanceAnalyticsService::PAYMENT_STATUSES)],
        ]);

        $filters['period'] = $filters['period'] ?? '30';

        if ($filters['period'] === 'custom') {
            $start = Carbon::createFromFormat('Y-m-d', $filters['start_date']);
            $end = Carbon::createFromFormat('Y-m-d', $filters['end_date']);

            if ($start->diffInDays($end) > 365) {
                throw ValidationException::withMessages([
                    'end_date' => 'O período personalizado não pode ultrapassar 366 dias.',
                ]);
            }
        }

        $tenantId = (int) $this->currentTenant()->id;
        if (! empty($filters['payment_method']) && ! in_array($filters['payment_method'], $analytics->availablePaymentMethods($tenantId), true)) {
            throw ValidationException::withMessages([
                'payment_method' => 'A forma de pagamento selecionada não foi encontrada.',
            ]);
        }

        return array_filter($filters, fn ($value) => $value !== null && $value !== '');
    }

    private function currentTenant(): Tenant
    {
        $tenant = Tenant::current();
        abort_unless($tenant instanceof Tenant, 404, 'Estabelecimento não encontrado.');

        return $tenant;
    }

    private function loadOrders(
        FinanceAnalyticsService $analytics,
        int $tenantId,
        array $range,
        array $filters
    ) {
        $orders = $analytics->ordersQuery($tenantId, $range, $filters)
            ->paginate(20)
            ->withQueryString();

        $paymentsByOrder = OrderPayment::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('order_id', $orders->getCollection()->pluck('id'))
            ->with('refunds')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('order_id');

        $orders->getCollection()->each(function ($order) use ($paymentsByOrder): void {
            $order->payments = $paymentsByOrder->get($order->id, collect());
        });

        return $orders;
    }

    private function authorizeFinance(string $action): void
    {
        $user = Auth::user();
        $hasPermission = $user && (
            $user->can('financeiro.'.$action)
            || ($action === 'visualizar' && $user->can('financeiro.editar'))
        );
        $allowed = $user && (
            $user->hasRole('Super')
            || $user->can('usuario.tornar usuario master')
            || $hasPermission
        );

        abort_unless($allowed, 403);
    }

    private function backToFinance(Request $request, string $message): RedirectResponse
    {
        $filters = $request->input('filters', []);
        $allowedPeriods = ['today', 'yesterday', '7', '30', '90', 'this_month', 'last_month', 'custom'];

        if (! is_array($filters)) {
            $filters = [];
        }

        $filters = array_intersect_key($filters, array_flip([
            'period',
            'start_date',
            'end_date',
            'payment_method',
            'payment_status',
        ]));

        if (isset($filters['period']) && ! in_array($filters['period'], $allowedPeriods, true)) {
            unset($filters['period']);
        }

        return redirect()
            ->route('admin.dashboard.finance.index', $filters)
            ->with('status', $message);
    }

    private function streamQueryCsv(string $filename, FinanceReportExport $export): StreamedResponse
    {
        return response()->streamDownload(function () use ($export): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $export->headings(), ';');

            $export->query()->chunk(500, function ($rows) use ($output, $export): void {
                foreach ($rows as $row) {
                    fputcsv($output, $export->map($row), ';');
                }
            });

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function streamCsv(string $filename, array $headers, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $headers, ';');

            foreach ($rows as $row) {
                fputcsv($output, $row, ';');
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function renderPdf(string $filename, array $headers, array $rows, string $title, array $range)
    {
        return Pdf::loadView('admin.finance.exports.pdf', [
            'headers' => $headers,
            'rows' => $rows,
            'title' => $title,
            'range' => $range,
        ])->setPaper('a4', 'landscape')->download($filename);
    }
}
