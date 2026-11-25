@forelse ($products as $product)
    <div class="dashboard-product-row">
        <div class="dashboard-product-image">
            @if ($product->product_image)
                <img
                    src="{{ \Illuminate\Support\Str::startsWith($product->product_image, ['http://', 'https://']) ? $product->product_image : asset('storage/'.$product->product_image) }}"
                    alt=""
                    loading="lazy"
                >
            @else
                <i class="mdi mdi-food-outline" aria-hidden="true"></i>
            @endif
        </div>
        <div class="dashboard-product-details">
            <div class="fw-semibold text-truncate">{{ $product->product_title }}</div>
            <div class="text-muted small">{{ number_format((int) $product->quantity_sold, 0, ',', '.') }} vendidos</div>
        </div>
        <div class="text-end fw-semibold">{{ 'R$ '.number_format((float) $product->revenue, 2, ',', '.') }}</div>
    </div>
@empty
    <div class="dashboard-empty-state">
        <i class="mdi mdi-food-off-outline" aria-hidden="true"></i>
        <p class="mb-0">Ainda não há produtos vendidos neste período.</p>
    </div>
@endforelse
