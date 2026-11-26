@php
    $statusLabels = [
        'pending' => ['Aguardando', 'warning'],
        'confirmed' => ['Confirmado', 'info'],
        'preparing' => ['Em preparação', 'primary'],
        'ready' => ['Pronto', 'success'],
        'out_for_delivery' => ['Em rota', 'purple'],
        'delivered' => ['Entregue', 'secondary'],
        'canceled' => ['Cancelado', 'danger'],
    ];
@endphp

@forelse ($orders as $order)
    @php
        [$statusLabel, $statusColor] = $statusLabels[$order->status] ?? ['Status desconhecido', 'secondary'];
    @endphp
    <style>
        .font-12{
            font-size: 0.75rem;
        }
    </style>
    <tr>
        <td class="fw-semibold font-12">#{{ $order->number }}</td>
        <td class="font-12">{{ $order->customer_name }}</td>
        <td class="text-nowrap font-12">{{ \Illuminate\Support\Carbon::parse($order->placed_at)->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
        <td class="text-nowrap font-12 fw-semibold">{{ 'R$ '.number_format((float) $order->total, 2, ',', '.') }}</td>
        <td class="font-12">{{ $order->payment_method ? ucfirst(str_replace('_', ' ', $order->payment_method)) : '—' }}</td>
        <td><span class="badge bg-soft-{{ $statusColor }} text-{{ $statusColor }}">{{ $statusLabel }}</span></td>
    </tr>
@empty
    <tr>
        <td colspan="6">
            <div class="dashboard-empty-state">
                <i class="mdi mdi-receipt-text-outline" aria-hidden="true"></i>
                <p class="mb-0">Nenhum pedido registrado ainda.</p>
            </div>
        </td>
    </tr>
@endforelse
