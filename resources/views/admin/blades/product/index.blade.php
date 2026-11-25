@extends('admin.core.admin')

@section('content')
    @php
        $user = Auth::user();
        $canCreateProduct = $user->hasRole('Super')
            || $user->can('usuario.tornar usuario master')
            || ($user->can('produtos.visualizar') && $user->can('produtos.criar'));
        $canEditProduct = $user->hasRole('Super')
            || $user->can('usuario.tornar usuario master')
            || ($user->can('produtos.visualizar') && $user->can('produtos.editar'));
        $canDeleteProduct = $user->hasRole('Super')
            || $user->can('usuario.tornar usuario master')
            || ($user->can('produtos.visualizar') && $user->can('produtos.remover'));
        $canCreateCategory = $theme->hasModule('product_categories') && (
            $user->hasRole('Super')
            || $user->can('usuario.tornar usuario master')
            || ($user->can('categorias de produtos.visualizar') && $user->can('categorias de produtos.criar'))
        );
        $canEditCategory = $theme->hasModule('product_categories') && (
            $user->hasRole('Super')
            || $user->can('usuario.tornar usuario master')
            || ($user->can('categorias de produtos.visualizar') && $user->can('categorias de produtos.editar'))
        );
        $canDeleteCategory = $theme->hasModule('product_categories') && (
            $user->hasRole('Super')
            || $user->can('usuario.tornar usuario master')
            || ($user->can('categorias de produtos.visualizar') && $user->can('categorias de produtos.remover'))
        );
        $canViewCategories = $theme->hasModule('product_categories') && (
            $user->hasRole('Super')
            || $user->can('usuario.tornar usuario master')
            || $user->can('categorias de produtos.visualizar')
        );
        $canDeleteSelected = $canDeleteProduct;
    @endphp

    <div class="content-page">
        <div class="content">
            <div class="container-fluid">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                    <div>
                        <h4 class="page-title mb-1">Cardápio</h4>
                        <p class="text-muted mb-0">Gerencie as categorias e os produtos do seu cardápio.</p>
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-2">
                        @if ($canViewCategories)
                            <button
                                type="button"
                                class="btn btn-light"
                                data-bs-toggle="modal"
                                data-bs-target="#productCategory-manage"
                            >
                                <i class="mdi mdi-shape-outline me-1"></i>
                                Gerenciar categorias
                            </button>
                            @include('admin.blades.product.partials.category-management')
                        @endif

                        @if ($canCreateCategory)
                            <button
                                type="button"
                                class="btn btn-primary text-black"
                                data-bs-toggle="modal"
                                data-bs-target="#productCategory-create"
                            >
                                <i class="mdi mdi-plus me-1"></i>
                                Nova categoria
                            </button>
                        @endif
                    </div>
                </div>

                @if ($errors->has('title'))
                    <div class="alert alert-danger" role="alert">
                        {{ $errors->first('title') }}
                    </div>
                @endif

                <div class="card mb-3">
                    <div class="card-body">
                        <form action="{{ route('admin.dashboard.product.index') }}" method="GET">
                            <div class="row g-2 align-items-center">
                                <div class="col-12 col-lg-6">
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="mdi mdi-magnify"></i>
                                        </span>
                                        <input
                                            type="text"
                                            name="title"
                                            class="form-control"
                                            value="{{ request('title') }}"
                                            placeholder="Buscar produto..."
                                            aria-label="Buscar produto"
                                        >
                                    </div>
                                </div>

                                <div class="col-12 col-sm-6 col-lg-3">
                                    <select name="product_category_id" class="form-select" aria-label="Filtrar por categoria">
                                        <option value="">Todas as categorias</option>
                                        @foreach ($categories as $category)
                                            <option
                                                value="{{ $category->id }}"
                                                {{ (string) request('product_category_id') === (string) $category->id ? 'selected' : '' }}
                                            >
                                                {{ $category->title }}{{ $category->active ? '' : ' (inativa)' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-12 col-sm-6 col-lg-2">
                                    <select name="product_type" class="form-select" aria-label="Filtrar por tipo">
                                        <option value="">Todos os tipos</option>
                                        <option value="food" {{ request('product_type') === 'food' ? 'selected' : '' }}>
                                            Produto
                                        </option>
                                        <option value="combo" {{ request('product_type') === 'combo' ? 'selected' : '' }}>
                                            Combo
                                        </option>
                                    </select>
                                </div>

                                <div class="col-12 col-lg-1 d-grid">
                                    <button type="submit" class="btn btn-primary text-black" aria-label="Filtrar produtos">
                                        <i class="mdi mdi-filter-outline"></i>
                                        <span class="d-lg-none ms-1">Filtrar</span>
                                    </button>
                                </div>
                            </div>

                            @if (request()->filled('title') || request()->filled('product_category_id') || request()->filled('product_type'))
                                <div class="text-end mt-2">
                                    <a href="{{ route('admin.dashboard.product.index') }}" class="link-secondary small">
                                        Limpar filtros
                                    </a>
                                </div>
                            @endif
                        </form>
                    </div>
                </div>

                @if ($canDeleteSelected)
                    <div class="d-flex justify-content-end mb-3">
                        <button
                            id="btSubmitDelete"
                            data-route="{{ route('admin.dashboard.product.destroySelected') }}"
                            type="button"
                            class="btSubmitDelete btn btn-danger"
                            style="display: none"
                        >
                            Excluir selecionados
                        </button>
                    </div>
                @endif

                @forelse ($categoryGroups as $group)
                    @php
                        $category = $group['category'];
                    @endphp

                    <section class="card shadow-sm mb-4" aria-label="{{ $category?->title ?? 'Produtos sem categoria' }}">
                        <div class="card-header bg-white py-3">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                @if ($category)
                                    <span class="badge {{ $category->active ? 'bg-success' : 'bg-danger' }}">
                                        <i class="mdi {{ $category->active ? 'mdi-check-circle-outline' : 'mdi-close-circle-outline' }} me-1"></i>
                                        {{ $category->active ? 'Ativa' : 'Inativa' }}
                                    </span>
                                @else
                                    <span class="badge bg-secondary">Sem categoria</span>
                                @endif

                                <h5 class="mb-0">{{ $category?->title ?? 'Sem categoria' }}</h5>
                                <span class="text-muted">({{ $group['count'] }} produtos)</span>

                                @if ($canCreateProduct)
                                    <a
                                        href="{{ route(
                                            'admin.dashboard.product.create',
                                            $category ? ['product_category_id' => $category->id] : []
                                        ) }}"
                                        class="btn btn-primary text-black ms-auto"
                                    >
                                        <i class="mdi mdi-plus me-1"></i>
                                        Produto
                                    </a>
                                @endif
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover table-sortable align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 36px"></th>
                                        <th class="bs-checkbox" style="width: 36px">
                                            <input type="checkbox" name="btnSelectAll" aria-label="Selecionar produtos desta categoria">
                                        </th>
                                        <th>Produto</th>
                                        <th class="text-nowrap">Status</th>
                                        <th class="text-nowrap">Preço</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>

                                <tbody data-route="{{ route('admin.dashboard.product.sorting') }}">
                                    @foreach ($group['products'] as $product)
                                        <tr data-code="{{ $product->id }}">
                                            <td>
                                                <span class="btnDrag mdi mdi-drag-horizontal font-22 text-muted" aria-label="Arraste para reordenar"></span>
                                            </td>
                                            <td class="bs-checkbox">
                                                <input
                                                    type="checkbox"
                                                    name="btnSelectItem"
                                                    class="btnSelectItem"
                                                    value="{{ $product->id }}"
                                                    aria-label="Selecionar {{ $product->title }}"
                                                >
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-3">
                                                    @if ($product->path_image)
                                                        <img
                                                            src="{{ asset('storage/' . $product->path_image) }}"
                                                            alt="{{ $product->title }}"
                                                            width="52"
                                                            height="52"
                                                            class="rounded"
                                                            style="object-fit: cover"
                                                        >
                                                    @else
                                                        <div class="rounded bg-light d-flex align-items-center justify-content-center text-muted" style="width: 52px; height: 52px">
                                                            <i class="mdi mdi-image-outline font-22"></i>
                                                        </div>
                                                    @endif

                                                    <div class="min-w-0">
                                                        <div class="fw-semibold">{{ $product->title }}</div>
                                                        <div class="small text-muted">{{ $product->description }}</div>
                                                        @if ($product->product_type === 'combo')
                                                            <span class="badge bg-info mt-1">Combo</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                @if ($canEditProduct)
                                                    <div class="d-flex align-items-center gap-2">
                                                        <label class="form-check form-switch mb-0">
                                                            <input
                                                                type="checkbox"
                                                                class="form-check-input product-status-toggle"
                                                                role="switch"
                                                                aria-label="Alterar disponibilidade de {{ $product->title }}"
                                                                data-product-status-url="{{ route('admin.dashboard.product.status', ['product' => $product->id]) }}"
                                                                @checked($product->active)
                                                            >
                                                        </label>
                                                        <span class="badge {{ $product->active ? 'bg-success' : 'bg-danger' }} product-status-badge">
                                                            <i class="mdi {{ $product->active ? 'mdi-check-circle-outline' : 'mdi-close-circle-outline' }} me-1"></i>
                                                            <span class="product-status-text">{{ $product->active ? 'Disponível' : 'Indisponível' }}</span>
                                                        </span>
                                                    </div>
                                                    <small class="product-status-error text-danger" aria-live="polite"></small>
                                                @elseif ($product->active)
                                                    <span class="badge bg-success">
                                                        <i class="mdi mdi-check-circle-outline me-1"></i>
                                                        Disponível
                                                    </span>
                                                @else
                                                    <span class="badge bg-danger">
                                                        <i class="mdi mdi-close-circle-outline me-1"></i>
                                                        Indisponível
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="fw-semibold text-nowrap">
                                                @if ($product->price !== null)
                                                    R$ {{ number_format((float) $product->price, 2, ',', '.') }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="text-end text-nowrap">
                                                @if ($canEditProduct)
                                                    <a
                                                        href="{{ route('admin.dashboard.product.edit', ['product' => $product->id]) }}"
                                                        class="btn btn-sm btn-outline-secondary"
                                                        title="Editar produto"
                                                        aria-label="Editar {{ $product->title }}"
                                                    >
                                                        <i class="mdi mdi-pencil"></i>
                                                    </a>
                                                @endif

                                                @if ($canDeleteProduct)
                                                    <form
                                                        action="{{ route('admin.dashboard.product.destroy', ['product' => $product->id]) }}"
                                                        method="POST"
                                                        class="d-inline"
                                                    >
                                                        @csrf
                                                        @method('DELETE')
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-outline-danger demo-delete-row btSubmitDeleteItem"
                                                            title="Excluir produto"
                                                            aria-label="Excluir {{ $product->title }}"
                                                        >
                                                            <i class="mdi mdi-delete-outline"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @empty
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="mdi mdi-food-outline font-36 text-muted"></i>
                            <h5 class="mt-2">Nenhum produto encontrado</h5>
                            <p class="text-muted">Ajuste os filtros ou cadastre um produto para começar.</p>
                            @if ($canCreateProduct)
                                <a href="{{ route('admin.dashboard.product.create') }}" class="btn btn-primary text-black">
                                    <i class="mdi mdi-plus me-1"></i>
                                    Novo produto
                                </a>
                            @endif
                        </div>
                    </div>
                @endforelse

                <div class="mt-3 d-flex justify-content-end">
                    {{ $products->withQueryString()->links() }}
                </div>

                @if ($canCreateCategory)
                    <div class="modal fade" id="productCategory-create" tabindex="-1" aria-labelledby="productCategory-create-title" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header bg-light">
                                    <h5 class="modal-title" id="productCategory-create-title">Nova categoria</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                </div>
                                <form action="{{ route('admin.dashboard.productCategory.store') }}" method="POST">
                                    @csrf
                                    <div class="modal-body">
                                        <label for="productCategory-title" class="form-label">Nome da categoria</label>
                                        <input
                                            id="productCategory-title"
                                            type="text"
                                            name="title"
                                            class="form-control @error('title') is-invalid @enderror"
                                            value="{{ old('title') }}"
                                            maxlength="255"
                                            required
                                            autofocus
                                        >
                                        @error('title')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror

                                        <input type="hidden" name="active" value="0">
                                        <div class="form-check mt-3">
                                            <input
                                                id="productCategory-active"
                                                type="checkbox"
                                                name="active"
                                                value="1"
                                                class="form-check-input"
                                                @checked(old('active', '1') === '1')
                                            >
                                            <label for="productCategory-active" class="form-check-label">Categoria ativa no cardápio</label>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn btn-primary text-black">
                                            <i class="mdi mdi-content-save-outline me-1"></i>
                                            Salvar categoria
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-product-category-modal-target]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var managerElement = document.getElementById('productCategory-manage');
                    var targetElement = document.querySelector(button.dataset.productCategoryModalTarget);

                    if (!managerElement || !targetElement) {
                        return;
                    }

                    var managerModal = bootstrap.Modal.getOrCreateInstance(managerElement);
                    var targetModal = bootstrap.Modal.getOrCreateInstance(targetElement);

                    targetElement.addEventListener('hidden.bs.modal', function () {
                        managerModal.show();
                    }, { once: true });

                    managerElement.addEventListener('hidden.bs.modal', function () {
                        targetModal.show();
                    }, { once: true });

                    managerModal.hide();
                });
            });

            document.querySelectorAll('.product-status-toggle').forEach(function (toggle) {
                toggle.addEventListener('change', function () {
                    var desiredActive = toggle.checked;
                    var cell = toggle.closest('td');
                    var errorMessage = cell.querySelector('.product-status-error');
                    var csrfMeta = document.querySelector('meta[name="csrf-token"]');

                    toggle.disabled = true;
                    errorMessage.textContent = '';

                    fetch(toggle.dataset.productStatusUrl, {
                        method: 'PATCH',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : ''
                        },
                        body: JSON.stringify({ active: desiredActive })
                    })
                        .then(function (response) {
                            return response.json().then(function (data) {
                                if (!response.ok) {
                                    throw new Error(data.message || 'Não foi possível atualizar o status.');
                                }
                                return data;
                            });
                        })
                        .then(function (data) {
                            var active = data.active === true;
                            var badge = cell.querySelector('.product-status-badge');
                            var icon = badge.querySelector('i');

                            toggle.checked = active;
                            badge.classList.toggle('bg-success', active);
                            badge.classList.toggle('bg-danger', !active);
                            icon.className = 'mdi ' + (active ? 'mdi-check-circle-outline' : 'mdi-close-circle-outline') + ' me-1';
                            badge.querySelector('.product-status-text').textContent = active
                                ? 'Disponível'
                                : 'Indisponível';
                        })
                        .catch(function (error) {
                            toggle.checked = !desiredActive;
                            errorMessage.textContent = error.message || 'Não foi possível atualizar o status.';
                        })
                        .finally(function () {
                            toggle.disabled = false;
                        });
                });
            });
        });
    </script>

    @if ($errors->has('title') && $canCreateCategory)
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modal = document.getElementById('productCategory-create');
                if (modal) {
                    bootstrap.Modal.getOrCreateInstance(modal).show();
                }
            });
        </script>
    @endif
@endsection
