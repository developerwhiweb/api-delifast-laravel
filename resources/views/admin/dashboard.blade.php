@extends('admin.core.admin')

@section('content')
    {{-- HEADER --}}
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="mdi mdi-view-dashboard-outline me-2 text-primary"></i>
                Dashboard
            </h1>
            <p class="text-muted small mb-0">
                Acompanhe vendas, pedidos e desempenho da operação
            </p>
        </div>
    </div>

    <style>
        .announcement-badge {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            margin-bottom: 24px;
            border: 1px solid transparent;
            border-radius: 12px;
            transition: .2s ease;
        }

        .announcement-badge:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 18px rgba(0, 0, 0, .07);
        }

        .announcement-icon {
            width: 42px;
            height: 42px;
            min-width: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            font-size: 18px;
        }

        .announcement-content {
            flex: 1;
            min-width: 0;
        }

        .announcement-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-bottom: 2px;
            opacity: .75;
        }

        .announcement-title {
            font-size: 14px;
            font-weight: 700;
            line-height: 1.3;
        }

        .announcement-text {
            font-size: 13px;
            line-height: 1.4;
            margin-top: 2px;
        }

        .announcement-text p {
            margin: 0;
        }

        .announcement-action {
            flex-shrink: 0;
        }

        .announcement-button {
            border: 0;
            font-weight: 600;
            padding: 7px 12px;
            transition: .2s ease;
        }

        .announcement-button:hover {
            transform: translateX(2px);
        }

        /* Geral */
        .announcement-general {
            background: var(--ct-secondary-bg);
            border-color: var(--ct-border-color);
            color: var(--ct-body-color);
        }

        .announcement-general .announcement-icon {
            background: var(--ct-tertiary-bg);
        }

        .announcement-general .announcement-button {
            background: #fb6c04;
            color: #021127;
        }

        /* Manutenção */
        .announcement-maintenance {
            background: var(--ct-warning-bg-subtle);
            border-color: var(--ct-warning-border-subtle);
            color: var(--ct-warning-text);
        }

        .announcement-maintenance .announcement-icon {
            background: var(--ct-warning-border-subtle);
        }

        .announcement-maintenance .announcement-button {
            background: #ffc107;
            color: #212529;
        }

        /* Atualização */
        .announcement-update {
            background: var(--ct-success-bg-subtle);
            border-color: var(--ct-success-border-subtle);
            color: var(--ct-success-text);
        }

        .announcement-update .announcement-icon {
            background: var(--ct-success-border-subtle);
        }

        .announcement-update .announcement-button {
            background: #198754;
            color: #fff;
        }

        /* Promoção */
        .announcement-promotion {
            background: var(--ct-secondary-bg);
            border-color: var(--ct-border-color);
            color: var(--ct-body-color);
        }

        .announcement-promotion .announcement-icon {
            background: var(--ct-tertiary-bg);
        }

        .announcement-promotion .announcement-button {
            background: #fb6c04;
            color: #021127;
        }

        /* Aviso */
        .announcement-warning {
            background: var(--ct-danger-bg-subtle);
            border-color: var(--ct-danger-border-subtle);
            color: var(--ct-danger-text);
        }

        .announcement-warning .announcement-icon {
            background: var(--ct-danger-border-subtle);
        }

        .announcement-warning .announcement-button {
            background: #dc3545;
            color: #fff;
        }
        .announcement-wrapper {
        position: relative;
        margin-bottom: 24px;
        }

        .announcement-badge {
        position: relative;
        padding-right: 48px;
        transition: opacity .25s ease, transform .25s ease;
        }

        .announcement-toggle {
        position: absolute;
        top: 50%;
        right: 12px;
        transform: translateY(-50%);
        width: 30px;
        height: 30px;
        border: 0;
        border-radius: 7px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(var(--ct-body-color-rgb), .06);
        color: inherit;
        cursor: pointer;
        transition: .2s ease;
        }

        .announcement-toggle:hover {
        background: rgba(var(--ct-body-color-rgb), .12);
        }

        .announcement-toggle i {
        transition: transform .25s ease;
        }

        .announcement-open {
        display: none;
        align-items: center;
        gap: 7px;
        border: 1px solid var(--ct-border-color);
        border-radius: 8px;
        padding: 6px 11px;
        background: var(--ct-secondary-bg);
        color: var(--ct-body-color);
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        box-shadow: 0 2px 8px rgba(0, 0, 0, .06);
        transition: .2s ease;
        }

        .announcement-open:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, .1);
        }

        .announcement-wrapper.is-hidden .announcement-badge {
        display: none;
        }

        .announcement-wrapper.is-hidden .announcement-open {
        display: inline-flex;
        }

        @media (max-width: 576px) {
        .announcement-toggle {
            top: 12px;
            right: 10px;
            transform: none;
        }            
        .announcement-badge {
                align-items: flex-start;
                padding: 12px;
            }

            .announcement-icon {
                width: 36px;
                height: 36px;
                min-width: 36px;
                font-size: 15px;
            }

            .announcement-title {
                font-size: 13px;
            }

            .announcement-text {
                font-size: 12px;
            }

            .announcement-action {
                display: none;
            }
        }
    </style> 

    @if(!empty($announcement))
        @php
            $announcementConfig = [
                'general' => [
                    'icon' => 'bi-megaphone-fill',
                    'class' => 'announcement-general',
                    'label' => 'Informação',
                ],
                'maintenance' => [
                    'icon' => 'bi-tools',
                    'class' => 'announcement-maintenance',
                    'label' => 'Manutenção',
                ],
                'update' => [
                    'icon' => 'bi-arrow-up-circle-fill',
                    'class' => 'announcement-update',
                    'label' => 'Atualização',
                ],
                'promotion' => [
                    'icon' => 'bi-stars',
                    'class' => 'announcement-promotion',
                    'label' => 'Novidade',
                ],
                'warning' => [
                    'icon' => 'bi-exclamation-triangle-fill',
                    'class' => 'announcement-warning',
                    'label' => 'Aviso',
                ],
            ];

            $announcementType = $announcementConfig[$announcement->type] ?? $announcementConfig['general'];
        @endphp

        <div class="announcement-wrapper col-12" id="announcementWrapper">
            <div class="announcement-badge {{ $announcementType['class'] }}">
                <div class="announcement-icon">
                    <i class="bi {{ $announcementType['icon'] }}"></i>
                </div>

                <div class="announcement-content">
                    <div class="announcement-label">
                        {{ $announcementType['label'] }}
                    </div>

                    @if(!empty($announcement->title))
                        <div class="announcement-title">
                            {{ $announcement->title }}
                        </div>
                    @endif

                    @if(!empty($announcement->text))
                        <div class="announcement-text">
                            {!! $announcement->text !!}
                        </div>
                    @endif
                </div>

                @if(!empty($announcement->link))
                    <div class="announcement-action">
                        <a
                            href="{{ $announcement->link }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="btn btn-sm announcement-button"
                        >
                            Saiba mais
                            <i class="bi bi-arrow-up-right ms-1"></i>
                        </a>
                    </div>
                @endif

                <button
                    type="button"
                    class="announcement-toggle"
                    id="announcementToggle"
                    aria-label="Ocultar anúncio"
                    title="Ocultar anúncio"
                >
                    <i class="bi bi-chevron-up"></i>
                </button>
            </div>

            <button
                type="button"
                class="announcement-open"
                id="announcementOpen"
                aria-label="Exibir anúncio"
                title="Exibir anúncio"
            >
                <i class="bi {{ $announcementType['icon'] }}"></i>
                <span>{{ $announcementType['label'] }}</span>
            </button>
        </div>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const wrapper = document.getElementById('announcementWrapper');
            const toggle = document.getElementById('announcementToggle');
            const open = document.getElementById('announcementOpen');

            if (!wrapper || !toggle || !open) {
                return;
            }

            toggle.addEventListener('click', function () {
                wrapper.classList.add('is-hidden');
            });

            open.addEventListener('click', function () {
                wrapper.classList.remove('is-hidden');
            });
        });
    </script>

    @if($dashboardData)
        @php
            $metrics = $dashboardData['metrics'];
            $revenueChange = $metrics['revenue_change'];
        @endphp

        <style>
            .dashboard-metric-card {
                height: 100%;
                border: 1px solid rgba(152, 166, 173, .16);
                transition: transform .18s ease, box-shadow .18s ease;
            }

            .dashboard-metric-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 24px rgba(15, 34, 58, .08);
            }

            .dashboard-metric-icon {
                width: 44px;
                height: 44px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border-radius: 12px;
                font-size: 22px;
            }

            .dashboard-product-row {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 14px 0;
                border-bottom: 1px solid rgba(152, 166, 173, .16);
            }

            .dashboard-product-row:last-child {
                border-bottom: 0;
            }

            .dashboard-product-image {
                width: 46px;
                height: 46px;
                flex: 0 0 46px;
                display: flex;
                align-items: center;
                justify-content: center;
                overflow: hidden;
                border-radius: 10px;
                background: var(--bs-tertiary-bg, #f3f5f7);
                color: var(--ct-secondary-color);
                font-size: 22px;
            }

            .dashboard-product-image img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }

            .dashboard-product-details {
                flex: 1;
                min-width: 0;
            }

            .dashboard-empty-state {
                display: flex;
                min-height: 150px;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 8px;
                color: var(--ct-secondary-color);
                text-align: center;
            }

            .dashboard-empty-state i {
                font-size: 30px;
            }

            .dashboard-table th {
                white-space: nowrap;
                font-weight: 600;
            }

            #dashboard-live[aria-busy="true"] #dashboard-sales-chart {
                opacity: .55;
            }

            .bg-soft-purple {
                color: var(--ct-primary-text);
                background: var(--ct-primary-bg-subtle);
            }

            .text-purple {
                color: var(--ct-primary-text) !important;
            }

            @media (max-width: 575.98px) {
                .dashboard-metric-card .card-body {
                    padding: 1rem;
                }

                .dashboard-metric-value {
                    font-size: 1.25rem;
                }
            }
        </style>

        <div
            id="dashboard-live"
            data-analytics-url="{{ $dashboardAnalyticsUrl }}"
            data-tenant-id="{{ $dashboardTenantId ?? '' }}"
            data-chart-labels="{{ json_encode($dashboardData['chart']['labels'], JSON_HEX_APOS | JSON_HEX_QUOT) }}"
            data-chart-values="{{ json_encode($dashboardData['chart']['values'], JSON_HEX_APOS | JSON_HEX_QUOT) }}"
            aria-busy="false"
        >
            <div id="dashboard-error" class="alert alert-danger d-none" role="alert" aria-live="assertive"></div>
            <div id="dashboard-loading" class="alert alert-light d-none py-2" role="status" aria-live="polite">
                <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                Atualizando os dados do painel…
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card dashboard-metric-card">
                        <div class="card-body d-flex align-items-center gap-3">
                            <span class="dashboard-metric-icon bg-soft-success text-success"><i class="mdi mdi-currency-brl" aria-hidden="true"></i></span>
                            <div class="min-w-0">
                                <div class="text-muted small">Faturamento do dia</div>
                                <div class="dashboard-metric-value h4 mb-0" data-dashboard-metric="revenue">{{ 'R$ '.number_format($metrics['revenue'], 2, ',', '.') }}</div>
                                <div id="dashboard-revenue-comparison" class="{{ $revenueChange === null ? 'text-muted' : ($revenueChange >= 0 ? 'text-success' : 'text-danger') }} small mt-1">
                                    @if($revenueChange === null)
                                        Sem base de comparação com o dia anterior
                                    @else
                                        {{ $revenueChange > 0 ? '+' : '' }}{{ number_format($revenueChange, 1, ',', '.') }}% em relação ao dia anterior
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card dashboard-metric-card">
                        <div class="card-body d-flex align-items-center gap-3">
                            <span class="dashboard-metric-icon bg-soft-primary text-primary"><i class="mdi mdi-package-variant-closed" aria-hidden="true"></i></span>
                            <div>
                                <div class="text-muted small">Pedidos recebidos hoje</div>
                                <div class="dashboard-metric-value h4 mb-0" data-dashboard-metric="orders">{{ number_format($metrics['orders'], 0, ',', '.') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card dashboard-metric-card">
                        <div class="card-body d-flex align-items-center gap-3">
                            <span class="dashboard-metric-icon bg-soft-info text-info"><i class="mdi mdi-chart-line" aria-hidden="true"></i></span>
                            <div>
                                <div class="text-muted small">Ticket médio</div>
                                <div class="dashboard-metric-value h4 mb-0" data-dashboard-metric="average_ticket">{{ 'R$ '.number_format($metrics['average_ticket'], 2, ',', '.') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card dashboard-metric-card">
                        <div class="card-body d-flex align-items-center gap-3">
                            <span class="dashboard-metric-icon bg-soft-warning text-warning"><i class="mdi mdi-bell-alert-outline" aria-hidden="true"></i></span>
                            <div>
                                <div class="text-muted small">Aguardando confirmação</div>
                                <div class="dashboard-metric-value h4 mb-0" data-dashboard-metric="waiting">{{ number_format($metrics['waiting'], 0, ',', '.') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card dashboard-metric-card">
                        <div class="card-body d-flex align-items-center gap-3">
                            <span class="dashboard-metric-icon bg-soft-primary text-primary"><i class="mdi mdi-chef-hat" aria-hidden="true"></i></span>
                            <div>
                                <div class="text-muted small">Em preparação</div>
                                <div class="dashboard-metric-value h4 mb-0" data-dashboard-metric="preparing">{{ number_format($metrics['preparing'], 0, ',', '.') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card dashboard-metric-card">
                        <div class="card-body d-flex align-items-center gap-3">
                            <span class="dashboard-metric-icon bg-soft-purple"><i class="mdi mdi-moped-outline" aria-hidden="true"></i></span>
                            <div>
                                <div class="text-muted small">Em rota de entrega</div>
                                <div class="dashboard-metric-value h4 mb-0" data-dashboard-metric="out_for_delivery">{{ number_format($metrics['out_for_delivery'], 0, ',', '.') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card dashboard-metric-card">
                        <div class="card-body d-flex align-items-center gap-3">
                            <span class="dashboard-metric-icon bg-soft-danger text-danger"><i class="mdi mdi-close-circle-outline" aria-hidden="true"></i></span>
                            <div>
                                <div class="text-muted small">Cancelados hoje</div>
                                <div class="dashboard-metric-value h4 mb-0" data-dashboard-metric="canceled">{{ number_format($metrics['canceled'], 0, ',', '.') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-3">
                                <div>
                                    <h4 class="header-title mb-1">Faturamento por período</h4>
                                    <p class="text-muted small mb-0">Valores registrados nos pedidos, excluindo cancelamentos</p>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <label for="dashboard-period" class="text-muted small mb-0">Período</label>
                                    <select id="dashboard-period" class="form-select form-select-sm" aria-label="Período do gráfico de vendas">
                                        <option value="today" {{ $dashboardData['period'] === 'today' ? 'selected' : '' }}>Hoje</option>
                                        <option value="7" {{ $dashboardData['period'] === '7' ? 'selected' : '' }}>Últimos 7 dias</option>
                                        <option value="30" {{ $dashboardData['period'] === '30' ? 'selected' : '' }}>Últimos 30 dias</option>
                                        <option value="90" {{ $dashboardData['period'] === '90' ? 'selected' : '' }}>Últimos 90 dias</option>
                                    </select>
                                </div>
                            </div>
                            <div id="dashboard-sales-chart" role="img" aria-label="Gráfico de faturamento por período"></div>
                            <div id="dashboard-sales-empty" class="dashboard-empty-state d-none" role="status">
                                <i class="mdi mdi-chart-line-variant" aria-hidden="true"></i>
                                <p class="mb-0">Ainda não há vendas registradas neste período.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-xl-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                                <div>
                                    <h4 class="header-title mb-1">Produtos mais vendidos</h4>
                                    <p class="text-muted small mb-0">Ranking por quantidade no período selecionado</p>
                                </div>
                                <i class="mdi mdi-trophy-outline text-warning fs-4" aria-hidden="true"></i>
                            </div>
                            <div id="dashboard-products">
                                @include('admin.partials.dashboard-products', ['products' => $dashboardData['products']])
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-xl-8">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
                                <div>
                                    <h4 class="header-title mb-1">Últimos pedidos</h4>
                                    <p class="text-muted small mb-0">Pedidos mais recentes do estabelecimento</p>
                                </div>
                                <a href="{{ route('admin.dashboard.administration') }}" class="btn btn-sm btn-light">
                                    Gerenciar pedidos <i class="mdi mdi-arrow-right ms-1" aria-hidden="true"></i>
                                </a>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle dashboard-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Pedido</th>
                                            <th>Cliente</th>
                                            <th>Data e hora</th>
                                            <th>Total</th>
                                            <th>Pagamento</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="dashboard-recent-orders">
                                        @include('admin.partials.dashboard-recent-orders', ['orders' => $dashboardData['recentOrders']])
                                    </tbody>
                                </table>
                            </div>
                            <div id="dashboard-sync-status" class="text-muted small mt-2" role="status" aria-live="polite"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="alert alert-info" role="status">
            Não foi possível identificar o estabelecimento deste acesso para carregar os indicadores.
        </div>
    @endif


   {{-- ============================================================
        HOME
    ============================================================ --}}


    {{-- ============================================================
        FOOTER
    ============================================================ --}}
    <footer class="footer d-none d-lg-flex">
        <div class="container-fluid">
            <div class="row">

                <div class="col-md-6">
                    <div>
                        <a
                            href="https://www.whi.dev.br/"
                            target="_blank"
                            rel="noopener noreferrer"
                            style="color: var(--ct-secondary-color);"
                        >
                            <script>
                                document.write(new Date().getFullYear())
                            </script>
                            © WHI - Web de Alta Inspiração
                        </a>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="d-none d-md-flex gap-4 align-items-center justify-content-md-end footer-links">

                        <a
                            href="https://www.whi.dev.br/"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-muted"
                        >
                            Sobre a WHI
                        </a>

                        <a
                            href="https://wa.me/5571992768360"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-muted"
                        >
                            Fale conosco
                        </a>

                    </div>
                </div>

            </div>
        </div>
    </footer>

    @include('admin.loadPage.loading')

@endsection

@section('scripts')
    @vite('resources/js/admin/dashboard.js')
@endsection
