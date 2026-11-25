<div class="modal fade" id="productCategory-manage" tabindex="-1" aria-labelledby="productCategory-manage-title" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title" id="productCategory-manage-title">Gerenciar categorias</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <div class="modal-body">
                @if ($categories->isEmpty())
                    <div class="text-center text-muted py-4">
                        <i class="mdi mdi-shape-outline font-30"></i>
                        <p class="mb-0 mt-2">Nenhuma categoria cadastrada.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover {{ $canEditCategory ? 'table-sortable' : '' }} align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    @if ($canEditCategory)
                                        <th style="width: 36px"></th>
                                    @endif
                                    <th>Categoria</th>
                                    <th class="text-nowrap">Produtos</th>
                                    <th class="text-nowrap">Status</th>
                                    @if ($canEditCategory || $canDeleteCategory)
                                        <th class="text-end">Ações</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody @if ($canEditCategory) data-route="{{ route('admin.dashboard.productCategory.sorting') }}" @endif>
                                @foreach ($categories as $category)
                                    <tr data-code="{{ $category->id }}">
                                        @if ($canEditCategory)
                                            <td>
                                                <span class="btnDrag mdi mdi-drag-horizontal font-22 text-muted" aria-label="Arraste para reordenar"></span>
                                            </td>
                                        @endif
                                        <td class="fw-semibold">{{ $category->title }}</td>
                                        <td>{{ $category->products_count ?? 0 }}</td>
                                        <td>
                                            <span class="badge {{ $category->active ? 'bg-success' : 'bg-danger' }}">
                                                {{ $category->active ? 'Ativa' : 'Inativa' }}
                                            </span>
                                        </td>
                                        @if ($canEditCategory || $canDeleteCategory)
                                            <td class="text-end text-nowrap">
                                                @if ($canEditCategory)
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-outline-secondary"
                                                        data-product-category-modal-target="#productCategory-edit-{{ $category->id }}"
                                                        title="Editar categoria"
                                                        aria-label="Editar {{ $category->title }}"
                                                    >
                                                        <i class="mdi mdi-pencil"></i>
                                                    </button>
                                                @endif

                                                @if ($canDeleteCategory)
                                                    <form
                                                        action="{{ route('admin.dashboard.productCategory.destroy', ['productCategory' => $category->id]) }}"
                                                        method="POST"
                                                        class="d-inline"
                                                    >
                                                        @csrf
                                                        @method('DELETE')
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-outline-danger btSubmitDeleteItem"
                                                            title="Excluir categoria"
                                                            aria-label="Excluir {{ $category->title }}"
                                                        >
                                                            <i class="mdi mdi-delete-outline"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="modal-footer">
                @if ($canCreateCategory)
                    <button
                        type="button"
                        class="btn btn-primary text-black me-auto"
                        data-product-category-modal-target="#productCategory-create"
                    >
                        <i class="mdi mdi-plus me-1"></i>
                        Nova categoria
                    </button>
                @endif
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

@if ($canEditCategory)
    @foreach ($categories as $category)
        <div class="modal fade" id="productCategory-edit-{{ $category->id }}" tabindex="-1" aria-labelledby="productCategory-edit-title-{{ $category->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-light">
                        <h5 class="modal-title" id="productCategory-edit-title-{{ $category->id }}">Editar categoria</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <form
                        action="{{ route('admin.dashboard.productCategory.update', ['productCategory' => $category->id]) }}"
                        method="POST"
                        enctype="multipart/form-data"
                    >
                        @csrf
                        @method('PUT')
                        <div class="modal-body">
                            @includeIf(
                                "admin.templates.{$themeData->slug}.{$themeData->template_variation}.productCategory.form",
                                ['productCategory' => $category, 'themeData' => $themeData]
                            )
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary text-black">Salvar alterações</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endif
