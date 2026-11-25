@extends('admin.core.admin')

@section('content')
    @php
        $financeLabels = [
            'pending' => 'Pendente',
            'paid' => 'Pago',
            'partially_paid' => 'Parcialmente pago',
            'failed' => 'Falhou',
            'canceled' => 'Cancelado',
            'refunded' => 'Reembolsado',
            'partially_refunded' => 'Parcialmente reembolsado',
            'untracked' => 'Sem lançamento',
        ];
        $financeClasses = [
            'pending' => 'bg-warning-subtle text-warning',
            'paid' => 'bg-success-subtle text-success',
            'partially_paid' => 'bg-info-subtle text-info',
            'failed' => 'bg-danger-subtle text-danger',
            'canceled' => 'bg-secondary-subtle text-secondary',
            'refunded' => 'bg-primary-subtle text-primary',
            'partially_refunded' => 'bg-warning-subtle text-warning',
            'untracked' => 'bg-light text-muted',
        ];
        $periodLabels = [
            'today' => 'Hoje',
            'yesterday' => 'Ontem',
            '7' => 'Últimos 7 dias',
            '30' => 'Últimos 30 dias',
            '90' => 'Últimos 90 dias',
            'this_month' => 'Este mês',
            'last_month' => 'Mês passado',
            'custom' => 'Personalizado',
        ];
    @endphp

    <div id="finance-live" data-snapshot-url="{{ route('admin.dashboard.finance.snapshot') }}" data-tenant-id="{{ $tenant->id }}" aria-busy="false">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="mdi mdi-cash-multiple text-primary me-2"></i>Financeiro</h1>
            <p class="text-muted mb-0">Visão financeira de {{ $tenant->name }} no fuso {{ $range['timezone'] }}.</p>
        </div>
        <form action="{{ route('admin.dashboard.finance.export') }}" method="GET" class="d-flex flex-wrap gap-2">
            @foreach ($filters as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
            <select name="type" class="form-select" aria-label="Tipo de relatório">
                <option value="summary">Resumo</option>
                <option value="orders">Pedidos</option>
                <option value="payments">Tentativas de pagamento</option>
            </select>
            <select name="format" class="form-select" aria-label="Formato do relatório">
                <option value="csv">CSV</option>
                <option value="xlsx">Excel</option>
                <option value="pdf">PDF</option>
            </select>
            <button type="submit" class="btn btn-outline-primary"><i class="mdi mdi-download me-1"></i>Exportar</button>
        </form>
    </div>
    <div id="finance-sync-status" class="text-muted small mb-3">Atualizando informações financeiras…</div>
    <div id="finance-live-error" class="alert alert-warning d-none" role="alert"></div>

    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>Não foi possível concluir a operação.</strong>
            <ul class="mb-0 mt-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.dashboard.finance.index') }}" class="row g-3 align-items-end" id="finance-filters">
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label" for="finance-period">Período</label>
                    <select class="form-select" id="finance-period" name="period">
                        @foreach ($periodLabels as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['period'] ?? '30') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2 finance-custom-date">
                    <label class="form-label" for="finance-start-date">De</label>
                    <input class="form-control" type="date" id="finance-start-date" name="start_date" value="{{ $filters['start_date'] ?? '' }}">
                </div>
                <div class="col-6 col-lg-2 finance-custom-date">
                    <label class="form-label" for="finance-end-date">Até</label>
                    <input class="form-control" type="date" id="finance-end-date" name="end_date" value="{{ $filters['end_date'] ?? '' }}">
                </div>
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label" for="finance-payment-method">Forma de pagamento</label>
                    <select class="form-select" id="finance-payment-method" name="payment_method">
                        <option value="">Todas</option>
                        @foreach ($paymentMethods as $method)
                            <option value="{{ $method }}" @selected(($filters['payment_method'] ?? '') === $method)>{{ $method }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label" for="finance-payment-status">Status financeiro</label>
                    <select class="form-select" id="finance-payment-status" name="payment_status">
                        <option value="">Todos</option>
                        @foreach ($paymentStatuses as $status)
                            <option value="{{ $status }}" @selected(($filters['payment_status'] ?? '') === $status)>
                                {{ $financeLabels[$status] ?? $status }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-lg-1 d-grid">
                    <button class="btn btn-primary" type="submit">Aplicar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="alert alert-info d-flex align-items-start gap-2 mb-4" role="note">
        <i class="mdi mdi-information-outline fs-4"></i>
        <div>
            O faturamento considera o total registrado nos pedidos não cancelados; recebimentos e reembolsos são calculados pelo ledger financeiro.
            Pedidos antigos sem lançamento não são tratados como pagos. Os indicadores não representam lucro.
        </div>
    </div>

    <div class="row g-3 mb-4">
        @php
            $cards = [
                ['key' => 'sales_total', 'title' => 'Faturamento em pedidos', 'value' => $overview['metrics']['sales_total'], 'icon' => 'mdi-chart-line', 'color' => 'primary'],
                ['key' => 'received_total', 'title' => 'Recebido confirmado', 'value' => $overview['metrics']['received_total'], 'icon' => 'mdi-cash-check', 'color' => 'success'],
                ['key' => 'pending_total', 'title' => 'Saldo pendente registrado', 'value' => $overview['metrics']['pending_total'], 'icon' => 'mdi-clock-outline', 'color' => 'warning'],
                ['key' => 'refunded_total', 'title' => 'Reembolsos efetivados', 'value' => $overview['metrics']['refunded_total'], 'icon' => 'mdi-cash-refund', 'color' => 'danger'],
                ['key' => 'order_count', 'title' => 'Pedidos', 'value' => $overview['metrics']['order_count'], 'icon' => 'mdi-receipt-text-outline', 'color' => 'info', 'money' => false],
                ['key' => 'paid_orders', 'title' => 'Pedidos pagos', 'value' => $overview['metrics']['paid_orders'], 'icon' => 'mdi-check-circle-outline', 'color' => 'success', 'money' => false],
                ['key' => 'pending_orders', 'title' => 'Pedidos pendentes', 'value' => $overview['metrics']['pending_orders'], 'icon' => 'mdi-progress-clock', 'color' => 'warning', 'money' => false],
                ['key' => 'canceled_orders', 'title' => 'Pedidos cancelados', 'value' => $overview['metrics']['canceled_orders'], 'icon' => 'mdi-cancel', 'color' => 'secondary', 'money' => false],
                ['key' => 'untracked_orders', 'title' => 'Sem lançamento financeiro', 'value' => $overview['metrics']['untracked_orders'], 'icon' => 'mdi-help-circle-outline', 'color' => 'dark', 'money' => false],
                ['key' => 'delivery_fees', 'title' => 'Taxas de entrega cobradas', 'value' => $overview['metrics']['delivery_fees'], 'icon' => 'mdi-moped', 'color' => 'info'],
                ['key' => 'discounts', 'title' => 'Descontos concedidos', 'value' => $overview['metrics']['discounts'], 'icon' => 'mdi-tag-outline', 'color' => 'danger'],
                ['key' => 'net_received', 'title' => 'Recebido líquido de reembolsos', 'value' => $overview['metrics']['net_received'], 'icon' => 'mdi-bank-transfer', 'color' => 'primary'],
            ];
        @endphp
        @foreach ($cards as $card)
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card h-100 mb-0">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="avatar-sm rounded-circle bg-{{ $card['color'] }}-subtle text-{{ $card['color'] }} d-flex align-items-center justify-content-center fs-4">
                            <i class="mdi {{ $card['icon'] }}"></i>
                        </span>
                        <div class="min-w-0">
                            <div class="text-muted text-truncate">{{ $card['title'] }}</div>
                            <div class="h4 mb-0" data-finance-metric="{{ $card['key'] }}" data-money="{{ ($card['money'] ?? true) ? 'true' : 'false' }}">
                                @if (($card['money'] ?? true) === false)
                                    {{ number_format((int) $card['value'], 0, ',', '.') }}
                                @else
                                    R$ {{ number_format((float) $card['value'], 2, ',', '.') }}
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-xl-8">
            <div class="card h-100 mb-0">
                <div class="card-header bg-transparent">
                    <h5 class="card-title mb-0">Faturamento por período</h5>
                    <small class="text-muted">Valores dos pedidos não cancelados</small>
                </div>
                <div class="card-body">
                    <div id="finance-sales-chart" class="min-vh-25" aria-label="Gráfico de faturamento"></div>
                    <div id="finance-sales-empty" class="text-center text-muted py-5 d-none">Não há vendas neste período.</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="card h-100 mb-0">
                <div class="card-header bg-transparent">
                    <h5 class="card-title mb-0">Recebimentos por forma de pagamento</h5>
                    <small class="text-muted">Somente valores confirmados no período</small>
                </div>
                <div class="card-body">
                    <div id="finance-methods-chart" aria-label="Gráfico de formas de pagamento"></div>
                    <div id="finance-methods-empty" class="text-center text-muted py-5 d-none">Nenhum recebimento confirmado no período.</div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card mb-0">
                <div class="card-header bg-transparent">
                    <h5 class="card-title mb-0">Taxas de entrega por período</h5>
                </div>
                <div class="card-body">
                    <div id="finance-delivery-chart" aria-label="Gráfico de taxas de entrega"></div>
                    <div id="finance-delivery-empty" class="text-center text-muted py-5 d-none">Não há taxas de entrega neste período.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-transparent">
            <h5 class="card-title mb-0">Composição dos pedidos</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-nowrap mb-0">
                <thead><tr><th>Subtotal dos produtos</th><th>Descontos</th><th>Taxas de entrega</th><th>Total dos pedidos</th><th>Recebido confirmado</th><th>Reembolsado</th></tr></thead>
                <tbody>
                    <tr>
                        <td data-finance-summary="product_subtotal">R$ {{ number_format((float) $overview['order_summary']->product_subtotal, 2, ',', '.') }}</td>
                        <td data-finance-summary="discounts">R$ {{ number_format((float) $overview['order_summary']->discounts, 2, ',', '.') }}</td>
                        <td data-finance-summary="delivery_fees">R$ {{ number_format((float) $overview['order_summary']->delivery_fees, 2, ',', '.') }}</td>
                        <td class="fw-semibold" data-finance-summary="order_total">R$ {{ number_format((float) $overview['order_summary']->order_total, 2, ',', '.') }}</td>
                        <td data-finance-summary="received_total">R$ {{ number_format((float) $overview['metrics']['received_total'], 2, ',', '.') }}</td>
                        <td data-finance-summary="refunded_total">R$ {{ number_format((float) $overview['metrics']['refunded_total'], 2, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div id="finance-orders-content">
        @include('admin.finance.partials.orders-content', [
            'orders' => $orders,
            'filters' => $filters,
            'financeLabels' => $financeLabels,
            'financeClasses' => $financeClasses,
        ])
    </div>
    </div>
@endsection

@section('scripts')
    <script>
        window.financeCharts = @json([
            'sales_chart' => $overview['sales_chart'],
            'delivery_chart' => $overview['delivery_chart'],
            'payment_methods' => $overview['payment_methods'],
        ]);
    </script>
    @vite('resources/js/admin/finance.js')
@endsection
