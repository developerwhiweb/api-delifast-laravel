@extends('admin.core.admin')

@section('content')
    @php
        $stages = [
            'pending' => ['label' => 'Novos pedidos', 'color' => 'warning'],
            'confirmed' => ['label' => 'Confirmados', 'color' => 'info'],
            'preparing' => ['label' => 'Em preparação', 'color' => 'primary'],
            'ready' => ['label' => 'Prontos para retirada/entrega', 'color' => 'success'],
            'out_for_delivery' => ['label' => 'Em rota de entrega', 'color' => 'purple'],
            'delivered' => ['label' => 'Finalizados', 'color' => 'success'],
            'canceled' => ['label' => 'Cancelados', 'color' => 'danger'],
        ];
        $paymentStatuses = [
            'pending' => 'Pendente',
            'paid' => 'Pago',
            'failed' => 'Recusado',
            'refunded' => 'Reembolsado',
        ];
        $deliveryMethods = [
            'delivery' => 'Entrega',
            'pickup' => 'Retirada',
            'local' => 'Consumo no local',
        ];
    @endphp

    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex justify-content-between align-items-center">
                <div>
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item">Pedidos</li>
                        <li class="breadcrumb-item active">Gerenciamento</li>
                    </ol>
                    <h4 class="page-title">Gerenciamento de pedidos</h4>
                </div>
                <div class="btn-group" role="group" aria-label="Modo de visualização dos pedidos">
                    <button type="button" class="btn btn-primary" id="show-kanban" aria-pressed="true">
                        <i class="mdi mdi-view-column-outline me-1"></i> Kanban
                    </button>
                    <button type="button" class="btn btn-light" id="show-list" aria-pressed="false">
                        <i class="mdi mdi-view-list-outline me-1"></i> Lista
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .orders-board {
            display: flex;
            gap: 1rem;
            overflow-x: auto;
            padding: .25rem .25rem 1rem;
            align-items: flex-start;
        }

        .orders-column {
            flex: 0 0 300px;
            max-height: calc(100vh - 230px);
            overflow-y: auto;
            background: var(--bs-tertiary-bg, #f3f5f7);
            border-radius: .5rem;
            padding: .75rem;
        }

        .orders-column-heading {
            position: sticky;
            top: -.75rem;
            z-index: 1;
            background: var(--bs-tertiary-bg, #f3f5f7);
            padding: .25rem 0 .75rem;
        }

        .order-card {
            border: 0;
            margin-bottom: .75rem;
        }

        .order-card:last-child {
            margin-bottom: 0;
        }

        .order-items-summary {
            max-height: 4.5rem;
            overflow: hidden;
        }

        .bg-soft-purple {
            color: #6f42c1;
            background: rgba(111, 66, 193, .12);
        }

        .text-purple {
            color: #6f42c1 !important;
        }

        @media (max-width: 575.98px) {
            .page-title-box {
                align-items: flex-start !important;
                gap: .75rem;
                flex-direction: column;
            }

            .orders-column {
                flex-basis: min(86vw, 300px);
                max-height: 70vh;
            }
        }
    </style>

    <div class="card">
        <div class="card-body">
            @if ($orders->isEmpty())
                <div class="text-center py-5">
                    <i class="mdi mdi-receipt-text-outline display-4 text-muted"></i>
                    <h5 class="mt-2">Nenhum pedido encontrado</h5>
                    <p class="text-muted mb-0">Os pedidos recebidos aparecerão aqui.</p>
                </div>
            @else
                <section id="orders-kanban" aria-label="Pedidos por etapa">
                    <div class="orders-board">
                        @foreach ($stages as $status => $stage)
                            @php
                                $stageOrders = $orders->getCollection()->where('status', $status);
                            @endphp
                            <div class="orders-column">
                                <div class="orders-column-heading d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">{{ $stage['label'] }}</h5>
                                    <span class="badge bg-light text-dark">{{ $stageOrders->count() }}</span>
                                </div>

                                @forelse ($stageOrders as $order)
                                    @php
                                        $address = collect($order->delivery_address ?? [])
                                            ->only(['street', 'number', 'neighborhood', 'city'])
                                            ->filter()
                                            ->implode(', ');
                                        $paymentStatus = $paymentStatuses[$order->payment_status] ?? ucfirst($order->payment_status);
                                        $deliveryMethod = $deliveryMethods[$order->delivery_method] ?? ucfirst($order->delivery_method ?? 'Não informado');
                                    @endphp
                                    <article class="card order-card shadow-sm">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-start flex-column align-items-start gap-2">
                                                <span class="badge bg-soft-{{ $stage['color'] }} text-{{ $stage['color'] }}">
                                                    {{ $stage['label'] }}
                                                </span>
                                                <h5 class="card-title mb-1">#{{ $order->number }}</h5>
                                            </div>
                                            <p class="text-muted font-13 mb-2">
                                                {{ $order->placed_at?->format('d/m/Y H:i') ?? $order->created_at?->format('d/m/Y H:i') }}
                                            </p>

                                            <div class="mb-2">
                                                <div class="fw-semibold">{{ $order->customer_name }}</div>
                                                @if ($order->customer_phone)
                                                    <a href="tel:{{ $order->customer_phone }}" class="text-muted font-13">
                                                        {{ $order->customer_phone }}
                                                    </a>
                                                @endif
                                            </div>

                                            <ul class="list-unstyled order-items-summary font-13 mb-2">
                                                @forelse ($order->items as $item)
                                                    <li>{{ $item->quantity }}x {{ $item->product_title }}</li>
                                                @empty
                                                    <li class="text-muted">Itens não informados</li>
                                                @endforelse
                                            </ul>

                                            <div class="d-flex justify-content-between align-items-center border-top pt-2">
                                                <span class="text-muted">{{ $deliveryMethod }}</span>
                                                <strong>R$ {{ number_format((float) $order->total, 2, ',', '.') }}</strong>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mt-1 font-13">
                                                <span class="text-muted">Pagamento: {{ $paymentStatuses[$order->payment_status] ?? ucfirst($order->payment_status) }}</span>
                                                <span class="text-muted">{{ $order->payment_method ?: 'Não informado' }}</span>
                                            </div>
                                            @if ($address)
                                                <p class="text-muted font-13 mt-2 mb-0">
                                                    <i class="mdi mdi-map-marker-outline"></i> {{ $address }}
                                                </p>
                                            @endif
                                            @if ($order->notes)
                                                <p class="font-13 mt-2 mb-0"><strong>Observação:</strong> {{ $order->notes }}</p>
                                            @endif
                                        </div>
                                    </article>
                                @empty
                                    <p class="text-muted text-center font-13 py-3 mb-0">Nenhum pedido nesta etapa.</p>
                                @endforelse
                            </div>
                        @endforeach
                    </div>
                </section>

                <section id="orders-list" class="d-none" aria-label="Lista de pedidos">
                    <div class="table-responsive">
                        <table class="table table-centered table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Pedido</th>
                                    <th>Data</th>
                                    <th>Cliente</th>
                                    <th>Itens</th>
                                    <th>Entrega</th>
                                    <th>Total</th>
                                    <th>Pagamento</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($orders as $order)
                                    @php
                                        $stage = $stages[$order->status] ?? ['label' => ucfirst($order->status), 'color' => 'secondary'];
                                        $deliveryMethod = $deliveryMethods[$order->delivery_method] ?? ucfirst($order->delivery_method ?? 'Não informado');
                                        $address = collect($order->delivery_address ?? [])
                                            ->only(['street', 'number', 'neighborhood', 'city', 'state'])
                                            ->filter()
                                            ->implode(', ');
                                    @endphp
                                    <tr>
                                        <td class="fw-semibold">#{{ $order->number }}</td>
                                        <td>{{ $order->placed_at?->format('d/m/Y H:i') ?? $order->created_at?->format('d/m/Y H:i') }}</td>
                                        <td>
                                            <div>{{ $order->customer_name }}</div>
                                            <small class="text-muted">{{ $order->customer_phone }}</small>
                                        </td>
                                        <td>
                                            @forelse ($order->items as $item)
                                                <div>{{ $item->quantity }}x {{ $item->product_title }}</div>
                                            @empty
                                                <span class="text-muted">Não informado</span>
                                            @endforelse
                                        </td>
                                        <td>
                                            <div>{{ $deliveryMethod }}</div>
                                            @if ($address)
                                                <small class="text-muted">{{ $address }}</small>
                                            @endif
                                        </td>
                                        <td class="text-nowrap">R$ {{ number_format((float) $order->total, 2, ',', '.') }}</td>
                                        <td>
                                            <div>{{ $paymentStatuses[$order->payment_status] ?? ucfirst($order->payment_status) }}</div>
                                            <small class="text-muted">{{ $order->payment_method ?: 'Não informado' }}</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-soft-{{ $stage['color'] }} text-{{ $stage['color'] }}">
                                                {{ $stage['label'] }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                <div class="mt-3">
                    {{ $orders->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const kanban = document.getElementById('orders-kanban');
            const list = document.getElementById('orders-list');
            const kanbanButton = document.getElementById('show-kanban');
            const listButton = document.getElementById('show-list');

            if (!kanban || !list || !kanbanButton || !listButton) {
                return;
            }

            function showView(view) {
                const showKanban = view === 'kanban';
                kanban.classList.toggle('d-none', !showKanban);
                list.classList.toggle('d-none', showKanban);
                kanbanButton.classList.toggle('btn-primary', showKanban);
                kanbanButton.classList.toggle('btn-light', !showKanban);
                listButton.classList.toggle('btn-primary', !showKanban);
                listButton.classList.toggle('btn-light', showKanban);
                kanbanButton.setAttribute('aria-pressed', String(showKanban));
                listButton.setAttribute('aria-pressed', String(!showKanban));
            }

            kanbanButton.addEventListener('click', function () {
                showView('kanban');
            });
            listButton.addEventListener('click', function () {
                showView('list');
            });
        });
    </script>

    <footer class="footer">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <div>
                        <a href="https://www.whi.dev.br/" target="_blank" rel="noopener noreferrer" style="color:#94a0ad;">
                            <script>document.write(new Date().getFullYear())</script>
                            © WHI - Web de Alta Inspiração
                        </a>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-none d-md-flex gap-4 align-items-center justify-content-md-end footer-links">
                        <a href="https://www.whi.dev.br/" target="_blank" rel="noopener noreferrer" class="text-muted">Sobre a WHI</a>
                        <a href="https://wa.me/5571992768360" target="_blank" rel="noopener noreferrer" class="text-muted">Fale conosco</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>
@endsection
