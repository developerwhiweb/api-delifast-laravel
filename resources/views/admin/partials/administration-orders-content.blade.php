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

<div id="orders-content">
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
                            <div
                                class="orders-column"
                                data-status="{{ $status }}"
                                data-stage-label="{{ $stage['label'] }}"
                                data-stage-color="{{ $stage['color'] }}"
                            >
                                <div class="orders-column-heading d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0 text-dark">{{ $stage['label'] }}</h5>
                                    <span class="badge bg-white text-dark" data-stage-count>{{ $stageOrders->count() }}</span>
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
                                    <article
                                        class="card order-card shadow-sm"
                                        data-order-card="{{ $order->id }}"
                                        data-status="{{ $order->status }}"
                                        data-updated-at="{{ $order->updated_at?->toISOString() }}"
                                        data-placed-at="{{ $order->placed_at?->toISOString() }}"
                                    >
                                        <div class="card-body">
                                            <div class="d-flex flex-column justify-content-start align-items-start gap-2">
                                                <span class="badge bg-soft-{{ $paymentColor }} text-{{ $paymentColor }}">
                                                    {{ $paymentStatus }}
                                                </span>
                                                <h5 class="card-title mb-0">#{{ $order->number }}</h5>
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
                                                    <form
                                                        class="order-status-form"
                                                        method="POST"
                                                        action="{{ route('admin.dashboard.administration.orders.status', $order->id) }}"
                                                        data-order-id="{{ $order->id }}"
                                                        data-target-status="{{ $action['status'] }}"
                                                        @if ($action['status'] === 'canceled') data-confirm="Deseja recusar este pedido?" @endif
                                                    >
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="status" value="{{ $action['status'] }}">
                                                        <button type="submit" class="btn btn-sm {{ $action['class'] }}">
                                                            {{ $action['label'] }}
                                                        </button>
                                                    </form>
                                                @endforeach
                                            </div>
                                        </div>
                                    </article>
                                @empty
                                    <p class="orders-empty text-muted text-center font-13 py-3 mb-0">Nenhum pedido nesta etapa.</p>
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
                                    <tr data-order-row="{{ $order->id }}" data-status="{{ $order->status }}">
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
                                            <span class="badge bg-soft-{{ $stage['color'] }} text-{{ $stage['color'] }}" data-order-status>
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
                                                                                    <span class="badge bg-light text-dark">{{ $optionName }}: {{ $choiceName }}</span>
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
</div>
