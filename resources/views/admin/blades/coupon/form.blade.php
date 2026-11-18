@php
    $selectedProductIds = array_map('intval', old('product_ids', $coupon ? $coupon->products->modelKeys() : []));
    $selectedCategoryIds = array_map('intval', old('category_ids', $coupon ? $coupon->categories->modelKeys() : []));
@endphp

@if ($coupon)
    <input type="hidden" name="coupon_id" value="{{ $coupon->id }}">
@endif

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="{{ $formId }}-code" class="form-label">Código *</label>
        <input id="{{ $formId }}-code" type="text" name="code" class="form-control"
            value="{{ old('code', $coupon?->code) }}" maxlength="50" required>
        <small class="text-muted">O código será convertido para letras maiúsculas.</small>
    </div>
    <div class="col-md-6 mb-3">
        <label for="{{ $formId }}-description" class="form-label">Descrição</label>
        <input id="{{ $formId }}-description" type="text" name="description" class="form-control"
            value="{{ old('description', $coupon?->description) }}">
    </div>
</div>

<div class="mb-3">
    <label class="form-label d-block">Tipo de desconto *</label>
    <div class="form-check form-check-inline">
        <input id="{{ $formId }}-percentage" class="form-check-input" type="radio" name="discount_type"
            value="PERCENTAGE" @checked(old('discount_type', $coupon?->discount_type ?? 'PERCENTAGE') === 'PERCENTAGE')>
        <label class="form-check-label" for="{{ $formId }}-percentage">Percentual</label>
    </div>
    <div class="form-check form-check-inline">
        <input id="{{ $formId }}-fixed" class="form-check-input" type="radio" name="discount_type"
            value="FIXED" @checked(old('discount_type', $coupon?->discount_type) === 'FIXED')>
        <label class="form-check-label" for="{{ $formId }}-fixed">Valor fixo</label>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="{{ $formId }}-discount-value" class="form-label">Valor do desconto *</label>
        <div class="input-group">
            <input id="{{ $formId }}-discount-value" type="number" name="discount_value"
                class="form-control" min="0.01" step="0.01"
                value="{{ old('discount_value', $coupon?->discount_value) }}" required>
            <span class="input-group-text" data-discount-suffix>
                {{ old('discount_type', $coupon?->discount_type ?? 'PERCENTAGE') === 'PERCENTAGE' ? '%' : 'R$' }}
            </span>
        </div>
        <small class="text-muted">Para percentual, informe de 0,01 a 100. Para valor fixo, informe em reais.</small>
    </div>
    <div class="col-md-6 mb-3 d-none" data-fixed-scope>
        <label for="{{ $formId }}-fixed-scope" class="form-label">Aplicação do valor fixo</label>
        <select id="{{ $formId }}-fixed-scope" name="fixed_scope" class="form-select">
            <option value="ORDER" @selected(old('fixed_scope', $coupon?->fixed_scope ?? 'ORDER') === 'ORDER')>
                Uma vez por pedido
            </option>
            <option value="UNIT" @selected(old('fixed_scope', $coupon?->fixed_scope) === 'UNIT')>
                Por unidade elegível
            </option>
        </select>
    </div>
</div>

<div class="mb-3">
    <label class="form-label d-block">Onde aplicar *</label>
    @foreach ([
        'ALL' => 'Todos os produtos',
        'SPECIFIC_PRODUCTS' => 'Produtos específicos',
        'SPECIFIC_CATEGORIES' => 'Categorias específicas',
    ] as $value => $label)
        <div class="form-check form-check-inline">
            <input id="{{ $formId }}-applies-{{ strtolower($value) }}" class="form-check-input"
                type="radio" name="applies_to" value="{{ $value }}"
                @checked(old('applies_to', $coupon?->applies_to ?? 'ALL') === $value)>
            <label class="form-check-label" for="{{ $formId }}-applies-{{ strtolower($value) }}">
                {{ $label }}
            </label>
        </div>
    @endforeach
</div>

<div class="border rounded p-3 mb-3 d-none" data-coupon-target="SPECIFIC_PRODUCTS">
    <label class="form-label">Selecione os produtos *</label>
    <div class="row">
        @forelse ($products as $product)
            <div class="col-md-6 col-lg-4">
                <div class="form-check">
                    <input id="{{ $formId }}-product-{{ $product->id }}" class="form-check-input"
                        type="checkbox" name="product_ids[]" value="{{ $product->id }}"
                        @checked(in_array($product->id, $selectedProductIds, true))>
                    <label class="form-check-label" for="{{ $formId }}-product-{{ $product->id }}">
                        {{ $product->name ?: $product->title }}
                    </label>
                </div>
            </div>
        @empty
            <div class="col-12 text-muted">Não há produtos cadastrados.</div>
        @endforelse
    </div>
</div>

<div class="border rounded p-3 mb-3 d-none" data-coupon-target="SPECIFIC_CATEGORIES">
    <label class="form-label">Selecione as categorias *</label>
    <div class="row">
        @forelse ($categories as $category)
            <div class="col-md-6 col-lg-4">
                <div class="form-check">
                    <input id="{{ $formId }}-category-{{ $category->id }}" class="form-check-input"
                        type="checkbox" name="category_ids[]" value="{{ $category->id }}"
                        @checked(in_array($category->id, $selectedCategoryIds, true))>
                    <label class="form-check-label" for="{{ $formId }}-category-{{ $category->id }}">
                        {{ $category->title }}
                    </label>
                </div>
            </div>
        @empty
            <div class="col-12 text-muted">Não há categorias cadastradas.</div>
        @endforelse
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <label for="{{ $formId }}-min-order-value" class="form-label">Pedido mínimo (R$)</label>
        <input id="{{ $formId }}-min-order-value" type="number" name="min_order_value"
            class="form-control" min="0" step="0.01"
            value="{{ old('min_order_value', $coupon?->min_order_value ?? 0) }}">
    </div>
    <div class="col-md-4 mb-3">
        <label for="{{ $formId }}-max-discount" class="form-label">Desconto máximo (R$)</label>
        <input id="{{ $formId }}-max-discount" type="number" name="max_discount"
            class="form-control" min="0.01" step="0.01"
            value="{{ old('max_discount', $coupon?->max_discount) }}">
    </div>
    <div class="col-md-4 mb-3">
        <label for="{{ $formId }}-usage-limit" class="form-label">Limite total de usos</label>
        <input id="{{ $formId }}-usage-limit" type="number" name="usage_limit"
            class="form-control" min="1" step="1"
            value="{{ old('usage_limit', $coupon?->usage_limit) }}">
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <label for="{{ $formId }}-usage-per-customer" class="form-label">Usos por cliente</label>
        <input id="{{ $formId }}-usage-per-customer" type="number" name="usage_per_customer"
            class="form-control" min="1" step="1"
            value="{{ old('usage_per_customer', $coupon?->usage_per_customer) }}">
    </div>
    <div class="col-md-4 mb-3">
        <label for="{{ $formId }}-starts-at" class="form-label">Início da validade</label>
        <input id="{{ $formId }}-starts-at" type="datetime-local" name="starts_at"
            class="form-control"
            value="{{ old('starts_at', $coupon?->starts_at?->format('Y-m-d\TH:i')) }}">
    </div>
    <div class="col-md-4 mb-3">
        <label for="{{ $formId }}-expires-at" class="form-label">Fim da validade</label>
        <input id="{{ $formId }}-expires-at" type="datetime-local" name="expires_at"
            class="form-control"
            value="{{ old('expires_at', $coupon?->expires_at?->format('Y-m-d\TH:i')) }}">
    </div>
</div>

<div class="form-check mb-3">
    <input type="hidden" name="is_active" value="0">
    <input id="{{ $formId }}-is-active" class="form-check-input" type="checkbox" name="is_active"
        value="1" @checked((bool) old('is_active', $coupon?->is_active ?? true))>
    <label class="form-check-label" for="{{ $formId }}-is-active">Cupom ativo</label>
</div>
