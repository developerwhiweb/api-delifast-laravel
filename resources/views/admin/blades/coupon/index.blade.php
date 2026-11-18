@extends('admin.core.admin')

@section('content')
    <div class="content-page">
        <div class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box">
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                                    <li class="breadcrumb-item active">Cupons</li>
                                </ol>
                            </div>
                            <h4 class="page-title">Cupons</h4>
                        </div>
                    </div>
                </div>

                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <strong>Não foi possível salvar o cupom:</strong>
                        <ul class="mb-0 mt-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-end mb-3">
                                    @if (Auth::user()->hasRole('Super') || Auth::user()->can('usuario.tornar usuario master') || Auth::user()->can('cupons.criar'))
                                        <button type="button" class="btn btn-primary text-black waves-effect waves-light"
                                            data-bs-toggle="modal" data-bs-target="#coupon-create">
                                            <i class="mdi mdi-plus-circle me-1"></i> Novo cupom
                                        </button>
                                    @endif
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-centered table-nowrap table-striped">
                                        <thead>
                                            <tr>
                                                <th>Código</th>
                                                <th>Desconto</th>
                                                <th>Aplicação</th>
                                                <th>Validade</th>
                                                <th>Status</th>
                                                <th style="width: 85px;">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($coupons as $coupon)
                                                <tr>
                                                    <td>
                                                        <strong>{{ $coupon->code }}</strong>
                                                        @if ($coupon->description)
                                                            <div class="text-muted">{{ $coupon->description }}</div>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if ($coupon->discount_type === 'PERCENTAGE')
                                                            {{ number_format((float) $coupon->discount_value, 2, ',', '.') }}%
                                                        @else
                                                            R$ {{ number_format((float) $coupon->discount_value, 2, ',', '.') }}
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @switch($coupon->applies_to)
                                                            @case('SPECIFIC_PRODUCTS')
                                                                Produtos selecionados
                                                                @break
                                                            @case('SPECIFIC_CATEGORIES')
                                                                Categorias selecionadas
                                                                @break
                                                            @default
                                                                Todos os produtos
                                                        @endswitch
                                                    </td>
                                                    <td>
                                                        {{ $coupon->starts_at ? $coupon->starts_at->format('d/m/Y H:i') : 'Sem início' }}
                                                        <br>
                                                        {{ $coupon->expires_at ? $coupon->expires_at->format('d/m/Y H:i') : 'Sem vencimento' }}
                                                    </td>
                                                    <td>
                                                        @if ($coupon->is_active)
                                                            <span class="badge bg-success">Ativo</span>
                                                        @else
                                                            <span class="badge bg-danger">Inativo</span>
                                                        @endif
                                                    </td>
                                                    <td class="d-flex gap-1">
                                                        @if (Auth::user()->hasRole('Super') || Auth::user()->can('usuario.tornar usuario master') || Auth::user()->can('cupons.editar'))
                                                            <button type="button" class="btn btn-primary text-black"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#coupon-edit-{{ $coupon->id }}"
                                                                aria-label="Editar {{ $coupon->code }}">
                                                                <span class="mdi mdi-pencil"></span>
                                                            </button>
                                                        @endif
                                                        @if (Auth::user()->hasRole('Super') || Auth::user()->can('usuario.tornar usuario master') || Auth::user()->can('cupons.remover'))
                                                            <form action="{{ route('admin.dashboard.coupon.destroy', $coupon) }}"
                                                                method="POST">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="button"
                                                                    class="btn btn-danger btSubmitDeleteItem"
                                                                    aria-label="Excluir {{ $coupon->code }}">
                                                                    <i class="fa fa-times"></i>
                                                                </button>
                                                            </form>
                                                        @endif
                                                    </td>
                                                </tr>

                                            @empty
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted">
                                                        Nenhum cupom cadastrado.
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>

                                @if (Auth::user()->hasRole('Super') || Auth::user()->can('usuario.tornar usuario master') || Auth::user()->can('cupons.editar'))
                                    @foreach ($coupons as $coupon)
                                    <div class="modal fade" id="coupon-edit-{{ $coupon->id }}" tabindex="-1"
                                        aria-hidden="true">
                                        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                                            <div class="modal-content">
                                                <div class="modal-header bg-light">
                                                    <h4 class="modal-title">Editar cupom {{ $coupon->code }}</h4>
                                                    <button type="button" class="btn-close"
                                                        data-bs-dismiss="modal" aria-label="Fechar"></button>
                                                </div>
                                                <div class="modal-body p-4">
                                                    <form id="coupon-edit-form-{{ $coupon->id }}" class="coupon-form"
                                                        action="{{ route('admin.dashboard.coupon.update', $coupon) }}"
                                                        method="POST">
                                                        @csrf
                                                        @method('PUT')
                                                        @include('admin.blades.coupon.form', [
                                                            'coupon' => $coupon,
                                                            'formId' => 'coupon-edit-form-' . $coupon->id,
                                                        ])
                                                        <div class="d-flex justify-content-end gap-2">
                                                            <button type="button" class="btn btn-danger"
                                                                data-bs-dismiss="modal">Cancelar</button>
                                                            <button type="submit"
                                                                class="btn btn-primary text-black">Salvar</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                @endif

                                <div class="mt-3 float-end">
                                    {{ $coupons->links() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if (Auth::user()->hasRole('Super') || Auth::user()->can('usuario.tornar usuario master') || Auth::user()->can('cupons.criar'))
        <div class="modal fade" id="coupon-create" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header bg-light">
                        <h4 class="modal-title">Cadastrar cupom</h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body p-4">
                        <form id="coupon-create-form" class="coupon-form"
                            action="{{ route('admin.dashboard.coupon.store') }}" method="POST">
                            @csrf
                            @include('admin.blades.coupon.form', [
                                'coupon' => null,
                                'formId' => 'coupon-create-form',
                            ])
                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary text-black">Cadastrar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@section('scripts')
    @parent
    <script>
        document.querySelectorAll('.coupon-form').forEach(function (form) {
            function updateCouponFields() {
                var discountType = form.querySelector('input[name="discount_type"]:checked');
                var appliesTo = form.querySelector('input[name="applies_to"]:checked');
                var fixedScope = form.querySelector('[data-fixed-scope]');
                var isFixed = discountType && discountType.value === 'FIXED';
                var discountSuffix = form.querySelector('[data-discount-suffix]');

                if (discountSuffix) {
                    discountSuffix.textContent = isFixed ? 'R$' : '%';
                }

                if (fixedScope) {
                    fixedScope.classList.toggle('d-none', !isFixed);
                    fixedScope.querySelector('select').disabled = !isFixed;
                }

                form.querySelectorAll('[data-coupon-target]').forEach(function (target) {
                    var isSelected = appliesTo && target.dataset.couponTarget === appliesTo.value;
                    target.classList.toggle('d-none', !isSelected);
                    target.querySelectorAll('input').forEach(function (input) {
                        input.disabled = !isSelected;
                    });
                });
            }

            form.querySelectorAll('input[name="discount_type"], input[name="applies_to"]')
                .forEach(function (input) {
                    input.addEventListener('change', updateCouponFields);
                });
            updateCouponFields();
        });

        @if ($errors->any())
            document.addEventListener('DOMContentLoaded', function () {
                var couponId = @json(old('coupon_id'));
                var modalId = couponId ? 'coupon-edit-' + couponId : 'coupon-create';
                var modalElement = document.getElementById(modalId);
                if (modalElement && window.bootstrap) {
                    window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
                }
            });
        @endif
    </script>
@endsection
