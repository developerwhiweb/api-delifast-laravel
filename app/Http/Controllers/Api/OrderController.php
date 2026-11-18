<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    /**
     * Listar pedidos do tenant atual.
     */
    public function index(Request $request)
    {
        $tenant = Tenant::current();
        $client = $request->user();

        $orders = Order::query()
            ->where('tenant_id', $tenant->id)
            ->where('client_id', $client->id)
            ->with('items')
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Registrar um novo pedido.
     */
    public function store(Request $request, CouponService $couponService): JsonResponse
    {
        $client = $request->user();

        $validated = $request->validate([
            'delivery_address' => [
                'nullable',
                'array',
            ],

            'notes' => [
                'nullable',
                'string',
            ],

            'delivery_fee' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'discount' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'payment_method' => [
                'nullable',
                'string',
                'max:50',
            ],

            'coupon_code' => [
                'nullable',
                'string',
                'max:50',
            ],

            'coupon_discount' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.product_id' => [
                'required',
                'integer',
            ],

            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
            ],

            'items.*.unit_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'items.*.price' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'items.*.details' => [
                'nullable',
                'array',
            ],

            'items.*.selected_options' => ['sometimes', 'array'],
            'items.*.selected_options.*' => ['nullable'],
            'items.*.selected_size_id' => ['nullable'],
            'items.*.selected_size_name' => ['nullable', 'string', 'max:100'],
        ]);

        $tenant = Tenant::current();

        $order = DB::transaction(function () use ($validated, $tenant, $client, $couponService) {
            $cartItems = array_values($validated['items']);
            $couponCode = trim((string) ($validated['coupon_code'] ?? ''));
            $couponQuote = null;

            if ($couponCode !== '') {
                $couponQuote = $couponService->quote(
                    $couponCode,
                    $cartItems,
                    $client->id,
                    true
                );

                if (!$couponQuote['valid']) {
                    throw ValidationException::withMessages([
                        'coupon_code' => $couponQuote['message'],
                    ]);
                }
            }

            $subtotal = 0;
            $items = [];

            foreach ($cartItems as $index => $item) {

                $product = Product::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('active', true)
                    ->findOrFail($item['product_id']);

                $quantity = $item['quantity'];

                $unitPrice = $couponQuote
                    ? (float) $couponQuote['items'][$index]['unit_price']
                    : $this->resolveUnitPrice($product, $item);

                $totalPrice = round($unitPrice * $quantity, 2);

                $subtotal += $totalPrice;

                $items[] = [
                    'product_id' => $product->id,

                    'product_title' => $product->title,
                    'product_slug' => $product->slug,
                    'product_image' => $product->path_image,
                    'product_type' => $product->product_type,

                    'quantity' => $quantity,

                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,

                    'details' => $item['details'] ?? null,
                ];
            }

            $deliveryFee = $validated['delivery_fee'] ?? 0;
            $couponDiscount = $couponQuote
                ? $couponQuote['discountCents'] / 100
                : 0;
            $discount = $couponQuote
                ? $couponDiscount
                : ($validated['discount'] ?? 0);

            if ($discount > ($subtotal + $deliveryFee)) {
                throw ValidationException::withMessages([
                    'discount' => 'O desconto não pode ser maior que o valor do pedido.',
                ]);
            }

            $couponCodeToSave = $couponQuote
                ? $couponQuote['coupon']->code
                : null;

            $order = Order::create([
                'tenant_id' => $tenant->id,

                'number' => $this->generateOrderNumber($tenant->id),

                'client_id' => $client->id,
                'customer_name' => $client->name,
                'customer_phone' => $client->phone,

                'delivery_address' => $validated['delivery_address'] ?? null,

                'status' => 'pending',
                'payment_status' => 'pending',
                'payment_method' => $validated['payment_method'] ?? null,

                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'discount' => $discount,
                'coupon_code' => $couponCodeToSave,
                'coupon_discount' => $couponDiscount,

                'total' => $subtotal + $deliveryFee - $discount,

                'notes' => $validated['notes'] ?? null,

                'placed_at' => now(),
            ]);

            $order->items()->createMany($items);

            if ($couponQuote) {
                CouponUsage::create([
                    'coupon_id' => $couponQuote['coupon']->id,
                    'client_id' => $client->id,
                    'order_id' => $order->id,
                    'discount_amount' => $couponDiscount,
                ]);

                $couponQuote['coupon']->increment('usage_count');
            }

            return $order;
        });

        return response()->json([
            'message' => 'Pedido realizado com sucesso!',
            'data' => $order->load('items'),
        ], 201);
    }

    /**
     * Consultar detalhes de um pedido.
     */
    public function show(Request $request, int $id)
    {
        $tenant = Tenant::current();
        $client = $request->user();

        $order = Order::query()
            ->where('tenant_id', $tenant->id)
            ->where('client_id', $client->id)
            ->with('items')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }

    /**
     * Atualizar status do pedido.
     */
    public function updateStatus(
        Request $request,
        int $id
    ): JsonResponse {

        $validated = $request->validate([
            'status' => [
                'required',
                'in:pending,confirmed,preparing,ready,out_for_delivery,delivered,canceled',
            ],
        ]);

        $tenant = Tenant::current();

        $order = Order::query()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($id);

        $order->update([
            'status' => $validated['status'],
        ]);

        return response()->json([
            'message' => 'Status do pedido atualizado com sucesso!',
            'data' => $order,
        ]);
    }

    private function resolveUnitPrice(Product $product, array $item): float
    {
        // The cart sends the finalized price with customizations; older clients send only product_id.
        $submittedPrice = $item['unit_price'] ?? $item['price'] ?? null;

        return round(
            $submittedPrice !== null ? (float) $submittedPrice : (float) $product->price,
            2
        );
    }

    /**
     * Gerar número único por tenant.
     */
    private function generateOrderNumber(int $tenantId): string
    {
        do {
            $number = 'PED-' . now()->format('ymd') . '-' . Str::upper(
                Str::random(6)
            );

        } while (
            Order::query()
                ->where('tenant_id', $tenantId)
                ->where('number', $number)
                ->exists()
        );

        return $number;
    }
}