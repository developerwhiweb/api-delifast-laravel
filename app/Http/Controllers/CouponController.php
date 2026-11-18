<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CouponController extends Controller
{
    /**
     * Listar cupons.
     */
    public function index()
    {
        $coupons = Coupon::with([
            'products:id,name',
            'categories:id,name'
        ])->latest()->paginate(20);

        return response()->json($coupons);
    }

    /**
     * Criar cupom.
     */
    public function store(Request $request)
    {
        $data = $this->validateCouponData($request);

        $coupon = DB::transaction(function () use ($data) {

            $coupon = Coupon::create(
                $this->couponAttributes($data)
            );

            $this->syncTargets($coupon, $data);

            return $coupon;
        });

        return response()->json([
            'message' => 'Cupom criado com sucesso.',
            'coupon' => $coupon->load(['products', 'categories'])
        ], 201);
    }

    /**
     * Exibir cupom.
     */
    public function show(Coupon $coupon)
    {
        return response()->json(
            $coupon->load(['products', 'categories'])
        );
    }

    /**
     * Atualizar cupom.
     */
    public function update(Request $request, Coupon $coupon)
    {
        $data = $this->validateCouponData($request, $coupon);

        DB::transaction(function () use ($coupon, $data) {

            $coupon->update(
                $this->couponAttributes($data)
            );

            $this->syncTargets($coupon, $data);
        });

        return response()->json([
            'message' => 'Cupom atualizado com sucesso.',
            'coupon' => $coupon->fresh()->load([
                'products',
                'categories'
            ])
        ]);
    }

    /**
     * Excluir cupom.
     */
    public function destroy(Coupon $coupon)
    {
        $coupon->delete();

        return response()->json([
            'message' => 'Cupom excluído com sucesso.'
        ]);
    }

    /**
     * Validar dados do cadastro.
     */
    private function validateCouponData(
        Request $request,
        ?Coupon $coupon = null
    ): array {

        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('coupons', 'code')
                    ->ignore($coupon?->id)
            ],

            'description' => ['nullable', 'string'],

            'discount_type' => [
                'required',
                Rule::in(['PERCENTAGE', 'FIXED'])
            ],

            'discount_value' => [
                'required',
                'numeric',
                'gt:0'
            ],

            'applies_to' => [
                'required',
                Rule::in([
                    'ALL',
                    'SPECIFIC_PRODUCTS',
                    'SPECIFIC_CATEGORIES'
                ])
            ],

            'fixed_scope' => [
                'required',
                Rule::in(['ORDER', 'UNIT'])
            ],

            'min_order_value' => [
                'nullable',
                'numeric',
                'min:0'
            ],

            'max_discount' => [
                'nullable',
                'numeric',
                'gt:0'
            ],

            'usage_limit' => [
                'nullable',
                'integer',
                'min:1'
            ],

            'usage_per_customer' => [
                'nullable',
                'integer',
                'min:1'
            ],

            'starts_at' => ['nullable', 'date'],
            'expires_at' => [
                'nullable',
                'date',
                'after_or_equal:starts_at'
            ],

            'is_active' => ['boolean'],

            'product_ids' => [
                'required_if:applies_to,SPECIFIC_PRODUCTS',
                'array'
            ],

            'product_ids.*' => [
                'integer',
                'distinct',
                'exists:products,id'
            ],

            'category_ids' => [
                'required_if:applies_to,SPECIFIC_CATEGORIES',
                'array'
            ],

            'category_ids.*' => [
                'integer',
                'distinct',
                'exists:categories,id'
            ],
        ]);
    }

    /**
     * Separar os atributos principais do cupom.
     */
    private function couponAttributes(array $data): array
    {
        return [
            'code' => strtoupper(trim($data['code'])),
            'description' => $data['description'] ?? null,
            'discount_type' => $data['discount_type'],
            'discount_value' => $data['discount_value'],
            'applies_to' => $data['applies_to'],
            'fixed_scope' => $data['fixed_scope'] ?? 'ORDER',
            'min_order_value' => $data['min_order_value'] ?? 0,
            'max_discount' => $data['max_discount'] ?? null,
            'usage_limit' => $data['usage_limit'] ?? null,
            'usage_per_customer' => $data['usage_per_customer'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ];
    }

    /**
     * Sincronizar produtos e categorias.
     */
    private function syncTargets(
        Coupon $coupon,
        array $data
    ): void {

        $coupon->products()->sync(
            $data['applies_to'] === 'SPECIFIC_PRODUCTS'
                ? ($data['product_ids'] ?? [])
                : []
        );

        $coupon->categories()->sync(
            $data['applies_to'] === 'SPECIFIC_CATEGORIES'
                ? ($data['category_ids'] ?? [])
                : []
        );
    }

    /**
     * Validar cupom e calcular desconto.
     *
     * Recebe os produtos do carrinho.
     * Os preços são consultados diretamente no banco.
     */
    public function validateCode(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'integer',
                'distinct',
                'exists:products,id'
            ],
            'items.*.quantity' => [
                'required',
                'integer',
                'min:1'
            ],
            'customer_id' => ['nullable', 'integer'],
        ]);

        $coupon = Coupon::with([
            'products:id',
            'categories:id'
        ])
            ->whereRaw('UPPER(code) = ?', [
                strtoupper(trim($data['code']))
            ])
            ->first();

        if (!$coupon || !$coupon->isValid()) {
            return response()->json([
                'valid' => false,
                'message' => 'Cupom inválido ou expirado.'
            ], 422);
        }

        // Limite de utilização por cliente.
        if (
            isset($data['customer_id']) &&
            $coupon->usage_per_customer !== null
        ) {
            $customerUsages = $coupon->usages()
                ->where('customer_id', $data['customer_id'])
                ->count();

            if ($customerUsages >= $coupon->usage_per_customer) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Você já atingiu o limite de uso deste cupom.'
                ], 422);
            }
        }

        // Buscar produtos reais no banco.
        $productIds = collect($data['items'])
            ->pluck('product_id')
            ->unique();

        $products = Product::whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $subtotalCents = 0;
        $eligibleSubtotalCents = 0;
        $eligibleQuantity = 0;

        $items = [];

        foreach ($data['items'] as $cartItem) {

            $product = $products->get($cartItem['product_id']);
            $quantity = (int) $cartItem['quantity'];

            // Considera o preço unitário cadastrado no banco.
            $unitPriceCents = (int) round(
                (float) $product->price * 100
            );

            $itemSubtotalCents = $unitPriceCents * $quantity;

            $subtotalCents += $itemSubtotalCents;

            $isEligible = $this->isProductEligible(
                $coupon,
                $product
            );

            if ($isEligible) {
                $eligibleSubtotalCents += $itemSubtotalCents;
                $eligibleQuantity += $quantity;
            }

            $items[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'quantity' => $quantity,
                'unit_price' => $unitPriceCents / 100,
                'subtotal' => $itemSubtotalCents / 100,
                'eligible' => $isEligible,
            ];
        }

        // Valor mínimo considera o subtotal total do pedido.
        if (
            $subtotalCents <
            (int) round((float) $coupon->min_order_value * 100)
        ) {
            return response()->json([
                'valid' => false,
                'message' => 'O pedido não atingiu o valor mínimo para este cupom.'
            ], 422);
        }

        if ($eligibleSubtotalCents <= 0) {
            return response()->json([
                'valid' => false,
                'message' => 'Este cupom não se aplica aos produtos do carrinho.'
            ], 422);
        }

        $discountCents = $this->calculateDiscount(
            $coupon,
            $eligibleSubtotalCents,
            $eligibleQuantity
        );

        return response()->json([
            'valid' => true,
            'coupon' => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'discount_type' => $coupon->discount_type,
                'discount_value' => $coupon->discount_value,
                'applies_to' => $coupon->applies_to,
            ],
            'items' => $items,
            'subtotal' => $subtotalCents / 100,
            'eligible_subtotal' => $eligibleSubtotalCents / 100,
            'discount' => $discountCents / 100,
            'total' => ($subtotalCents - $discountCents) / 100,
        ]);
    }

    /**
     * Verificar se o produto pode receber desconto.
     */
    private function isProductEligible(
        Coupon $coupon,
        Product $product
    ): bool {

        if ($coupon->applies_to === 'ALL') {
            return true;
        }

        if ($coupon->applies_to === 'SPECIFIC_PRODUCTS') {
            return $coupon->products->contains('id', $product->id);
        }

        if ($coupon->applies_to === 'SPECIFIC_CATEGORIES') {
            return $coupon->categories->contains(
                'id',
                $product->category_id
            );
        }

        return false;
    }

    /**
     * Calcular desconto em centavos.
     */
    private function calculateDiscount(
        Coupon $coupon,
        int $eligibleSubtotalCents,
        int $eligibleQuantity
    ): int {

        if ($coupon->discount_type === 'PERCENTAGE') {

            // Ex.: 10,50% = 1050 pontos-base.
            $percentageBasisPoints = (int) round(
                (float) $coupon->discount_value * 100
            );

            $discountCents = (int) round(
                $eligibleSubtotalCents *
                $percentageBasisPoints / 10000
            );

        } else {

            $fixedDiscountCents = (int) round(
                (float) $coupon->discount_value * 100
            );

            if ($coupon->fixed_scope === 'UNIT') {
                $discountCents = $fixedDiscountCents * $eligibleQuantity;
            } else {
                $discountCents = $fixedDiscountCents;
            }
        }

        // Aplicar desconto máximo.
        if ($coupon->max_discount !== null) {
            $maxDiscountCents = (int) round(
                (float) $coupon->max_discount * 100
            );

            $discountCents = min(
                $discountCents,
                $maxDiscountCents
            );
        }

        // Nunca permitir desconto maior que o valor elegível.
        return min($discountCents, $eligibleSubtotalCents);
    }
}