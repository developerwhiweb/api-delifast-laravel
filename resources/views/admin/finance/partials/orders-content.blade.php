<div class="card">
    <div class="card-header bg-transparent d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h5 class="card-title mb-0">Pedidos e lançamentos financeiros</h5>
            <small class="text-muted">Registre tentativas, confirme recebimentos e acompanhe reembolsos.</small>
        </div>
        <span class="text-muted small">{{ $orders->total() }} pedido(s)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Pedido</th>
                    <th>Cliente</th>
                    <th>Data</th>
                    <th>Total</th>
                    <th>Pagamento</th>
                    <th>Status</th>
                    <th>Gestão financeira</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    <tr>
                        <td class="fw-semibold">{{ $order->number ?: '#'.$order->id }}</td>
                        <td>{{ $order->customer_name ?: 'Cliente não informado' }}</td>
                        <td>{{ $order->placed_at ? \Illuminate\Support\Carbon::parse($order->placed_at, $range['timezone'])->format('d/m/Y H:i') : '—' }}</td>
                        <td>R$ {{ number_format((float) $order->total, 2, ',', '.') }}</td>
                        <td>{{ $order->payment_methods ?: $order->original_payment_method ?: 'Não informado' }}</td>
                        <td>
                            <span class="badge {{ $financeClasses[$order->finance_status] ?? 'bg-secondary-subtle text-secondary' }}">
                                {{ $financeLabels[$order->finance_status] ?? $order->finance_status }}
                            </span>
                            <div class="small text-muted mt-1">{{ $order->status }}</div>
                        </td>
                        <td class="min-w-250">
                            <details>
                                <summary class="text-primary" role="button">Ver e gerenciar</summary>
                                <div class="pt-2">
                                    @forelse ($order->payments as $payment)
                                        <div class="border rounded p-2 mb-2">
                                            <div class="d-flex flex-wrap justify-content-between gap-2">
                                                <span>
                                                    {{ $payment->method ?: 'Não informado' }} ·
                                                    R$ {{ number_format((float) $payment->amount, 2, ',', '.') }}
                                                    <span class="badge {{ $financeClasses[$payment->status] ?? 'bg-secondary-subtle text-secondary' }}">
                                                        {{ $financeLabels[$payment->status] ?? $payment->status }}
                                                    </span>
                                                </span>
                                                @if ($payment->reference)
                                                    <small class="text-muted">Ref. {{ $payment->reference }}</small>
                                                @endif
                                            </div>
                                            @if ($payment->status === 'pending')
                                                <div class="d-flex flex-wrap gap-1 mt-2">
                                                    @foreach (['paid' => 'Confirmar recebimento', 'failed' => 'Marcar falha', 'canceled' => 'Cancelar tentativa'] as $status => $label)
                                                        <form method="POST" action="{{ route('admin.dashboard.finance.payments.status', $payment->id) }}">
                                                            @csrf
                                                            @method('PATCH')
                                                            <input type="hidden" name="status" value="{{ $status }}">
                                                            @foreach ($filters as $key => $value)
                                                                <input type="hidden" name="filters[{{ $key }}]" value="{{ $value }}">
                                                            @endforeach
                                                            <button class="btn btn-sm {{ $status === 'paid' ? 'btn-success' : 'btn-outline-secondary' }}" type="submit">{{ $label }}</button>
                                                        </form>
                                                    @endforeach
                                                </div>
                                            @endif
                                            @foreach ($payment->refunds as $refund)
                                                <div class="small mt-2">
                                                    Reembolso R$ {{ number_format((float) $refund->amount, 2, ',', '.') }} ·
                                                    <span class="badge {{ $financeClasses[$refund->status === 'refunded' ? 'refunded' : ($refund->status === 'pending' ? 'pending' : $refund->status)] ?? 'bg-secondary-subtle text-secondary' }}">
                                                        {{ $refund->status === 'refunded' ? 'Efetivado' : ($financeLabels[$refund->status] ?? $refund->status) }}
                                                    </span>
                                                    @if ($refund->reason) <span class="text-muted">{{ $refund->reason }}</span> @endif
                                                </div>
                                                @if ($refund->status === 'pending')
                                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                                        @foreach (['refunded' => 'Confirmar reembolso', 'failed' => 'Marcar falha', 'canceled' => 'Cancelar'] as $status => $label)
                                                            <form method="POST" action="{{ route('admin.dashboard.finance.refunds.status', $refund->id) }}">
                                                                @csrf
                                                                @method('PATCH')
                                                                <input type="hidden" name="status" value="{{ $status }}">
                                                                @foreach ($filters as $key => $value)
                                                                    <input type="hidden" name="filters[{{ $key }}]" value="{{ $value }}">
                                                                @endforeach
                                                                <button class="btn btn-sm btn-outline-secondary" type="submit">{{ $label }}</button>
                                                            </form>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            @endforeach
                                            @if ($payment->status === 'paid')
                                                <form method="POST" action="{{ route('admin.dashboard.finance.refunds.store', $payment->id) }}" class="row g-2 mt-1">
                                                    @csrf
                                                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                                    @foreach ($filters as $key => $value)
                                                        <input type="hidden" name="filters[{{ $key }}]" value="{{ $value }}">
                                                    @endforeach
                                                    <div class="col-12 col-sm-4">
                                                        <input class="form-control form-control-sm" name="amount" type="number" min="0.01" step="0.01" placeholder="Valor a reembolsar" required>
                                                    </div>
                                                    <div class="col-12 col-sm-5">
                                                        <input class="form-control form-control-sm" name="reason" maxlength="500" placeholder="Motivo (opcional)">
                                                    </div>
                                                    <div class="col-12 col-sm-3 d-grid">
                                                        <button class="btn btn-sm btn-outline-danger" type="submit">Registrar reembolso</button>
                                                    </div>
                                                </form>
                                            @endif
                                        </div>
                                    @empty
                                        <p class="small text-muted mb-2">Este pedido ainda não possui lançamentos financeiros.</p>
                                    @endforelse

                                    @if ($order->status !== 'canceled' && ! $order->payments->contains('status', 'pending') && (float) $order->paid_amount < (float) $order->total)
                                        <form method="POST" action="{{ route('admin.dashboard.finance.payments.store', $order->id) }}" class="row g-2 border-top pt-2">
                                            @csrf
                                            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                            @foreach ($filters as $key => $value)
                                                <input type="hidden" name="filters[{{ $key }}]" value="{{ $value }}">
                                            @endforeach
                                            <div class="col-12 col-sm-3">
                                                <input class="form-control form-control-sm" name="amount" type="number" min="0.01" step="0.01" placeholder="Valor" required>
                                            </div>
                                            <div class="col-12 col-sm-4">
                                                <input class="form-control form-control-sm" name="method" maxlength="80" value="{{ $order->original_payment_method }}" placeholder="Forma de pagamento">
                                            </div>
                                            <div class="col-12 col-sm-3">
                                                <input class="form-control form-control-sm" name="reference" maxlength="120" placeholder="Referência (opcional)">
                                            </div>
                                            <div class="col-12 col-sm-2 d-grid">
                                                <button class="btn btn-sm btn-primary" type="submit">Nova tentativa</button>
                                            </div>
                                        </form>
                                    @endif
                                </div>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-5">Nenhum pedido encontrado para os filtros selecionados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($orders->hasPages())
        <div class="card-footer">{{ $orders->links() }}</div>
    @endif
</div>
