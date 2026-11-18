<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

class CouponService
{
    public function quote(
        string $code,
        array $items,
        int|string|null $clientId = null,
        bool $lockForUpdate = false
    ): array {
        $couponQuery = Coupon::query()
            ->whereRaw('UPPER(code) = ?', [strtoupper(trim($code))]);

        if ($lockForUpdate) {
            $couponQuery->lockForUpdate();
        }

        $coupon = $couponQuery->first();

        if (!$coupon || !$coupon->isValid()) {
            return [
                'valid' => false,
                'message' => 'Cupom inválido ou expirado.',
            ];
        }

        if (
            $clientId !== null
            && $coupon->usage_per_customer !== null
            && $coupon->usageCountForClient($clientId) >= $coupon->usage_per_customer
        ) {
            return [
                'valid' => false,
                'message' => 'Você já atingiu o limite de uso deste cupom.',
            ];
        }

        if ($coupon->applies_to === 'SPECIFIC_PRODUCTS') {
            $coupon->load('products:id');
        } elseif ($coupon->applies_to === 'SPECIFIC_CATEGORIES') {
            $coupon->load('categories:id');
        }

        $cartItems = array_map(
            fn (array $item) => $this->normalizeSelections($item),
            array_values($items)
        );
        $productIds = collect($cartItems)->pluck('product_id')->unique();
        $products = Product::with('options')
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $subtotalCents = 0;
        $eligibleSubtotalCents = 0;
        $eligibleQuantity = 0;
        $calculatedItems = [];

        foreach ($cartItems as $cartItem) {
            $product = $products->get($cartItem['product_id']);
            if (!$product) {
                throw ValidationException::withMessages([
                    'items' => 'Um dos produtos do pedido não está disponível.',
                ]);
            }

            $quantity = (int) $cartItem['quantity'];
            $unitPriceCents = $this->resolveUnitPriceCents($product, $cartItem);
            $itemSubtotalCents = $unitPriceCents * $quantity;
            $subtotalCents += $itemSubtotalCents;
            $isEligible = $this->isProductEligible($coupon, $product);

            if ($isEligible) {
                $eligibleSubtotalCents += $itemSubtotalCents;
                $eligibleQuantity += $quantity;
            }

            $calculatedItems[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'quantity' => $quantity,
                'unit_price' => $unitPriceCents / 100,
                'subtotal' => $itemSubtotalCents / 100,
                'eligible' => $isEligible,
            ];
        }

        if (
            $subtotalCents
            < (int) round((float) $coupon->min_order_value * 100)
        ) {
            return [
                'valid' => false,
                'message' => 'O pedido não atingiu o valor mínimo para este cupom.',
            ];
        }

        if ($eligibleSubtotalCents <= 0) {
            return [
                'valid' => false,
                'message' => 'Este cupom não se aplica aos produtos do carrinho.',
            ];
        }

        $discountCents = $this->calculateDiscount(
            $coupon,
            $eligibleSubtotalCents,
            $eligibleQuantity
        );

        return [
            'valid' => true,
            'coupon' => $coupon,
            'items' => $calculatedItems,
            'subtotalCents' => $subtotalCents,
            'eligibleSubtotalCents' => $eligibleSubtotalCents,
            'discountCents' => $discountCents,
        ];
    }

    private function resolveUnitPriceCents(
        Product $product,
        array $cartItem
    ): int {
        $unitPriceCents = (int) round((float) $product->price * 100);
        $selectedSizeId = $cartItem['selected_size_id'] ?? null;
        $selectedSizeName = $cartItem['selected_size_name'] ?? null;

        if ($selectedSizeId !== null || $selectedSizeName !== null) {
            $selectedSize = collect($product->sizes ?? [])->first(
                fn ($size) => is_array($size)
                    && (
                        ($selectedSizeId !== null
                            && isset($size['id'])
                            && (string) $size['id'] === (string) $selectedSizeId)
                        || ($selectedSizeName !== null
                            && (string) ($size['name'] ?? '') === $selectedSizeName)
                    )
            );

            if ($selectedSize === null || !isset($selectedSize['price'])) {
                throw ValidationException::withMessages([
                    'items' => 'O tamanho selecionado não está disponível para este produto.',
                ]);
            }

            $unitPriceCents = (int) round((float) $selectedSize['price'] * 100);
        }

        $optionsById = $product->options->keyBy(
            fn ($option) => (string) $option->id
        );

        foreach (($cartItem['selected_options'] ?? []) as $optionId => $selectedChoices) {
            $option = $optionsById->get((string) $optionId);
            if ($option === null) {
                throw ValidationException::withMessages([
                    'items' => 'Uma das opções selecionadas não pertence a este produto.',
                ]);
            }

            if ($selectedChoices === null || $selectedChoices === '') {
                continue;
            }

            $selectedChoices = is_array($selectedChoices)
                ? $selectedChoices
                : [$selectedChoices];
            $replacesBasePrice = preg_match('/tamanho|size/i', (string) $option->title) === 1;

            if ($replacesBasePrice && count($selectedChoices) > 1) {
                throw ValidationException::withMessages([
                    'items' => 'Selecione apenas um tamanho para cada produto.',
                ]);
            }

            foreach ($selectedChoices as $choiceId) {
                $choice = collect($option->choices ?? [])->first(
                    fn ($choice) => is_array($choice)
                        && isset($choice['id'])
                        && (string) $choice['id'] === (string) $choiceId
                );

                if ($choice === null) {
                    throw ValidationException::withMessages([
                        'items' => 'Uma das opções selecionadas não está disponível.',
                    ]);
                }

                $choicePriceCents = (int) round((float) ($choice['price'] ?? 0) * 100);
                $unitPriceCents = $replacesBasePrice
                    ? $choicePriceCents
                    : $unitPriceCents + $choicePriceCents;
            }
        }

        return max(0, $unitPriceCents);
    }

    private function normalizeSelections(array $cartItem): array
    {
        $details = is_array($cartItem['details'] ?? null)
            ? $cartItem['details']
            : [];

        if (empty($cartItem['selected_options'])) {
            $cartItem['selected_options'] = $details['selected_options']
                ?? $details['options']
                ?? [];
        }

        $cartItem['selected_size_id'] ??= $details['selected_size_id'] ?? null;
        $cartItem['selected_size_name'] ??= $details['selected_size_name'] ?? null;

        return $cartItem;
    }

    private function isProductEligible(Coupon $coupon, Product $product): bool
    {
        if ($coupon->applies_to === 'ALL') {
            return true;
        }

        if ($coupon->applies_to === 'SPECIFIC_PRODUCTS') {
            return $coupon->products->contains('id', $product->id);
        }

        if ($coupon->applies_to === 'SPECIFIC_CATEGORIES') {
            return $coupon->categories->contains(
                'id',
                $product->product_category_id
            );
        }

        return false;
    }

    private function calculateDiscount(
        Coupon $coupon,
        int $eligibleSubtotalCents,
        int $eligibleQuantity
    ): int {
        if ($coupon->discount_type === 'PERCENTAGE') {
            $percentageBasisPoints = (int) round(
                (float) $coupon->discount_value * 100
            );

            $discountCents = (int) round(
                $eligibleSubtotalCents * $percentageBasisPoints / 10000
            );
        } else {
            $fixedDiscountCents = (int) round(
                (float) $coupon->discount_value * 100
            );

            $discountCents = $coupon->fixed_scope === 'UNIT'
                ? $fixedDiscountCents * $eligibleQuantity
                : $fixedDiscountCents;
        }

        if ($coupon->max_discount !== null) {
            $maxDiscountCents = (int) round(
                (float) $coupon->max_discount * 100
            );
            $discountCents = min($discountCents, $maxDiscountCents);
        }

        return min($discountCents, $eligibleSubtotalCents);
    }
}
