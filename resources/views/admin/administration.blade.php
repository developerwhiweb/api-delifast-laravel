@extends('admin.core.admin')

@section('content')
    @php
        $stages = [
            'pending' => ['label' => 'Novos pedidos', 'color' => 'warning'],
            'confirmed' => ['label' => 'Confirmados', 'color' => 'info'],
            'preparing' => ['label' => 'Em preparação', 'color' => 'primary'],
            'ready' => ['label' => 'Prontos para retirada/entrega', 'color' => 'success'],
            'out_for_delivery' => ['label' => 'Em rota de entrega', 'color' => 'purple'],
            'delivered' => ['label' => 'Finalizados', 'color' => 'dark'],
            'canceled' => ['label' => 'Cancelados', 'color' => 'danger'],
        ];
        $paymentStatuses = [
            'pending' => 'Pendente',
            'paid' => 'Pago',
            'failed' => 'Recusado',
            'refunded' => 'Reembolsado',
        ];
        $paymentColors = [
            'pending' => 'warning',
            'paid' => 'success',
            'failed' => 'danger',
            'refunded' => 'secondary',
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

        .orders-column[data-status="pending"] {
            --stage-color: #f0ad4e;
            --stage-bg: #fff8e6;
        }

        .orders-column[data-status="confirmed"] {
            --stage-color: #17a2b8;
            --stage-bg: #e8f8fb;
        }

        .orders-column[data-status="preparing"] {
            --stage-color: #536de6;
            --stage-bg: #eef0ff;
        }

        .orders-column[data-status="ready"] {
            --stage-color: #22a06b;
            --stage-bg: #e9f8f0;
        }

        .orders-column[data-status="out_for_delivery"] {
            --stage-color: #8a63d2;
            --stage-bg: #f2edfc;
        }

        .orders-column[data-status="delivered"] {
            --stage-color: #495057;
            --stage-bg: #eceff1;
        }

        .orders-column[data-status="canceled"] {
            --stage-color: #dc3545;
            --stage-bg: #fff0f1;
        }

        .orders-column-heading {
            position: sticky;
            top: -.75rem;
            z-index: 1;
            background: var(--stage-bg, #f3f5f7);
            border-top: 3px solid var(--stage-color, #6c757d);
            padding: .5rem .25rem .75rem;
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

        .order-item-details {
            white-space: pre-wrap;
            font-size: .75rem;
        }

        .bg-soft-purple {
            color: #6f42c1;
            background: rgba(111, 66, 193, .12);
        }

        .text-purple {
            color: #6f42c1 !important;
        }

        .bg-soft-dark {
            color: #495057;
            background: rgba(73, 80, 87, .12);
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
            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            @endif
            @if ($errors->has('status'))
                <div class="alert alert-danger" role="alert">{{ $errors->first('status') }}</div>
            @endif

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
                            <div class="orders-column" data-status="{{ $status }}">
                                <div class="orders-column-heading d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">{{ $stage['label'] }}</h5>
                                    <span class="badge bg-white text-dark">{{ $stageOrders->count() }}</span>
                                </div>

                                @forelse ($stageOrders as $order)
                                    @php
                                        $deliveryMethod = $deliveryMethods[$order->delivery_method] ?? ucfirst($order->delivery_method ?? 'Não informado');
                                        $paymentStatus = $paymentStatuses[$order->payment_status] ?? ucfirst($order->payment_status);
                                        $paymentColor = $paymentColors[$order->payment_status] ?? 'secondary';
                                        $actions = match ($order->status) {
                                            'pending' => [
                                                ['status' => 'canceled', 'label' => 'Recusar', 'class' => 'btn-outline-danger'],
                                                ['status' => 'confirmed', 'label' => 'Aceitar', 'class' => 'btn-success'],
                                            ],
                                            'confirmed' => [
                                                ['status' => 'preparing', 'label' => 'Começar a preparar', 'class' => 'btn-primary'],
                                            ],
                                            'preparing' => [
                                                ['status' => 'ready', 'label' => 'Marcar como pronto', 'class' => 'btn-success'],
                                            ],
                                            'ready' => $order->delivery_method === 'delivery'
                                                ? [['status' => 'out_for_delivery', 'label' => 'Saiu para entrega', 'class' => 'btn-primary']]
                                                : [['status' => 'delivered', 'label' => 'Concluir retirada', 'class' => 'btn-success']],
                                            'out_for_delivery' => [
                                                ['status' => 'delivered', 'label' => 'Marcar como entregue', 'class' => 'btn-success'],
                                            ],
                                            default => [],
                                        };
                                    @endphp
                                    <article class="card order-card shadow-sm">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start gap-2">
                                                <h5 class="card-title mb-0">#{{ $order->number }}</h5>
                                                <span class="badge bg-soft-{{ $paymentColor }} text-{{ $paymentColor }}">
                                                    {{ $paymentStatus }}
                                                </span>
                                            </div>
                                            <p class="text-muted font-13 mt-1 mb-2">
                                                {{ $order->placed_at?->format('d/m/Y H:i') ?? $order->created_at?->format('d/m/Y H:i') }}
                                            </p>

                                            <div class="fw-semibold">{{ $order->customer_name }}</div>
                                            @if ($order->customer_phone)
                                                <a href="tel:{{ $order->customer_phone }}" class="text-muted font-13">
                                                    {{ $order->customer_phone }}
                                                </a>
                                            @endif
                                            <div class="d-flex justify-content-between align-items-center border-top mt-2 pt-2">
                                                <span class="text-muted font-13">{{ $deliveryMethod }}</span>
                                                <strong>R$ {{ number_format((float) $order->total, 2, ',', '.') }}</strong>
                                            </div>

                                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
                                                <button type="button" class="btn btn-sm btn-light col-12" data-bs-toggle="modal" data-bs-target="#order-details-{{ $order->id }}">
                                                    <i class="mdi mdi-eye-outline me-1"></i> Ver detalhes
                                                </button>
                                                @foreach ($actions as $action)
                                                    <form method="POST" action="{{ route('admin.dashboard.administration.orders.status', $order->id) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="status" value="{{ $action['status'] }}">
                                                        <button
                                                            type="submit"
                                                            class="btn btn-sm {{ $action['class'] }}"
                                                            @if ($action['status'] === 'canceled')
                                                                onclick="return confirm('Deseja recusar este pedido?')"
                                                            @endif
                                                        >
                                                            {{ $action['label'] }}
                                                        </button>
                                                    </form>
                                                @endforeach
                                            </div>
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

                @foreach ($orders as $order)
                    @php
                        $address = collect($order->delivery_address ?? [])
                            ->only(['recipient_name', 'street', 'number', 'complement', 'neighborhood', 'city', 'state', 'zip_code', 'reference'])
                            ->filter()
                            ->implode(', ');
                        $deliveryMethod = $deliveryMethods[$order->delivery_method] ?? ucfirst($order->delivery_method ?? 'Não informado');
                        $paymentStatus = $paymentStatuses[$order->payment_status] ?? ucfirst($order->payment_status);
                    @endphp
                    <div class="modal fade" id="order-details-{{ $order->id }}" tabindex="-1" aria-labelledby="order-details-title-{{ $order->id }}" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <div>
                                        <h5 class="modal-title" id="order-details-title-{{ $order->id }}">Pedido #{{ $order->number }}</h5>
                                        <small class="text-muted">{{ $order->placed_at?->format('d/m/Y H:i') ?? $order->created_at?->format('d/m/Y H:i') }}</small>
                                    </div>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <h6>Cliente</h6>
                                            <div>{{ $order->customer_name }}</div>
                                            @if ($order->customer_phone)
                                                <div><a href="tel:{{ $order->customer_phone }}">{{ $order->customer_phone }}</a></div>
                                            @endif
                                            @if ($order->customer_email)
                                                <div><a href="mailto:{{ $order->customer_email }}">{{ $order->customer_email }}</a></div>
                                            @endif
                                        </div>
                                        <div class="col-md-6">
                                            <h6>Entrega e pagamento</h6>
                                            <div>{{ $deliveryMethod }}</div>
                                            @if ($address)
                                                <div class="text-muted">{{ $address }}</div>
                                            @endif
                                            <div class="mt-1">{{ $paymentStatus }} · {{ $order->payment_method ?: 'Forma de pagamento não informada' }}</div>
                                        </div>
                                    </div>

                                    <h6>Itens do pedido</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle">
                                            <thead>
                                                <tr>
                                                    <th>Item</th>
                                                    <th class="text-center">Qtd.</th>
                                                    <th class="text-end">Unitário</th>
                                                    <th class="text-end">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse ($order->items as $item)
                                                    <tr>
                                                        <td>
                                                            <div>{{ $item->product_title }}</div>
                                                            @php
                                                                $itemDetails = collect($item->details ?? []);
                                                                $options = collect($itemDetails->get('options', $itemDetails->get('selected_options', [])));
                                                                $additionals = collect($itemDetails->get('aditionals', $itemDetails->get('additionals', [])));
                                                            @endphp
                                                            @if ($options->isNotEmpty() || $additionals->isNotEmpty())
                                                                <div class="mt-2">
                                                                    @if ($options->isNotEmpty())
                                                                        <div class="small text-muted mb-1">Personalizações</div>
                                                                        <div class="d-flex flex-wrap gap-1 mb-2">
                                                                            @foreach ($options as $optionKey => $option)
                                                                                @php
                                                                                    $optionName = is_array($option)
                                                                                        ? ($option['optionName'] ?? $option['option_name'] ?? $option['title'] ?? (is_string($optionKey) ? $optionKey : 'Opção'))
                                                                                        : (is_string($optionKey) ? $optionKey : 'Opção');
                                                                                    $choiceName = is_array($option)
                                                                                        ? ($option['choiceName'] ?? $option['choice_name'] ?? $option['choice'] ?? $option['value'] ?? $option['selected'] ?? $option['name'] ?? null)
                                                                                        : $option;
                                                                                    if (is_array($choiceName)) {
                                                                                        $choiceName = $choiceName['name'] ?? $choiceName['title'] ?? $choiceName['value'] ?? null;
                                                                                    }
                                                                                @endphp
                                                                                @if (is_scalar($choiceName) && (string) $choiceName !== '')
                                                                                    <span class="badge bg-light text-dark">
                                                                                        {{ $optionName }}: {{ $choiceName }}
                                                                                    </span>
                                                                                @endif
                                                                            @endforeach
                                                                        </div>
                                                                    @endif
                                                                    @if ($additionals->isNotEmpty())
                                                                        <div class="small text-muted mb-1">Adicionais</div>
                                                                        <ul class="list-unstyled small mb-0">
                                                                            @foreach ($additionals as $additional)
                                                                                @php
                                                                                    $additionalName = is_array($additional)
                                                                                        ? ($additional['name'] ?? $additional['title'] ?? 'Adicional')
                                                                                        : $additional;
                                                                                    $additionalQuantity = is_array($additional)
                                                                                        ? ($additional['quantity'] ?? 1)
                                                                                        : 1;
                                                                                    $additionalPrice = is_array($additional)
                                                                                        ? ($additional['price'] ?? null)
                                                                                        : null;
                                                                                @endphp
                                                                                @if (is_scalar($additionalName))
                                                                                    <li>
                                                                                        {{ $additionalName }}
                                                                                        @if ((int) $additionalQuantity > 1)
                                                                                            <span class="text-muted">× {{ $additionalQuantity }}</span>
                                                                                        @endif
                                                                                        @if (is_numeric($additionalPrice) && (float) $additionalPrice > 0)
                                                                                            <span class="text-muted">(+ R$ {{ number_format((float) $additionalPrice, 2, ',', '.') }})</span>
                                                                                        @endif
                                                                                    </li>
                                                                                @endif
                                                                            @endforeach
                                                                        </ul>
                                                                    @endif
                                                                </div>
                                                            @endif
                                                        </td>
                                                        <td class="text-center">{{ $item->quantity }}</td>
                                                        <td class="text-end">R$ {{ number_format((float) $item->unit_price, 2, ',', '.') }}</td>
                                                        <td class="text-end">R$ {{ number_format((float) $item->total_price, 2, ',', '.') }}</td>
                                                    </tr>
                                                @empty
                                                    <tr><td colspan="4" class="text-muted text-center">Itens não informados.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>

                                    @if ($order->notes)
                                        <div class="alert alert-light mb-3">
                                            <strong>Observações do cliente:</strong><br>
                                            {{ $order->notes }}
                                        </div>
                                    @endif

                                    <div class="ms-auto" style="max-width: 320px;">
                                        <div class="d-flex justify-content-between"><span>Subtotal</span><span>R$ {{ number_format((float) $order->subtotal, 2, ',', '.') }}</span></div>
                                        <div class="d-flex justify-content-between"><span>Taxa de entrega</span><span>R$ {{ number_format((float) $order->delivery_fee, 2, ',', '.') }}</span></div>
                                        @if ((float) $order->discount > 0)
                                            <div class="d-flex justify-content-between"><span>Desconto</span><span>- R$ {{ number_format((float) $order->discount, 2, ',', '.') }}</span></div>
                                        @endif
                                        <div class="d-flex justify-content-between border-top mt-2 pt-2 fw-bold"><span>Total</span><span>R$ {{ number_format((float) $order->total, 2, ',', '.') }}</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach

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
