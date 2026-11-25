<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\OrderRealtimeChanged;
use App\Models\Client;
use App\Models\Contact;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\ClientSecurityService;
use App\Services\CouponService;
use Carbon\CarbonInterface;
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
    public function store(
        Request $request,
        CouponService $couponService,
        ClientSecurityService $clientSecurity
    ): JsonResponse {
        $client = $request->user();

        $validated = $request->validate([
            'delivery_address' => ['nullable', 'array'],
            'delivery_address.title' => ['nullable', 'string', 'max:255'],
            'delivery_address.recipient_name' => ['required_with:delivery_address', 'string', 'max:255'],
            'delivery_address.zip_code' => ['required_with:delivery_address', 'string', 'max:9', 'regex:/^\d{5}-?\d{3}$/'],
            'delivery_address.state' => ['required_with:delivery_address', 'string', 'size:2'],
            'delivery_address.city' => ['required_with:delivery_address', 'string', 'max:255'],
            'delivery_address.neighborhood' => ['required_with:delivery_address', 'string', 'max:255'],
            'delivery_address.street' => ['required_with:delivery_address', 'string', 'max:255'],
            'delivery_address.number' => ['required_with:delivery_address', 'string', 'max:30'],
            'delivery_address.complement' => ['nullable', 'string', 'max:255'],
            'delivery_address.reference' => ['nullable', 'string', 'max:2000'],
            'delivery_address.is_default' => ['nullable', 'boolean'],
            'delivery_method' => ['nullable', 'in:delivery,pickup,local'],
            'address_id' => ['nullable', 'integer'],
            'save_address' => ['nullable', 'boolean'],

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

        $deliveryMethod = $validated['delivery_method']
            ?? (! empty($validated['delivery_address']) ? 'delivery' : 'pickup');
        $addressId = $validated['address_id'] ?? null;
        $saveAddress = filter_var(
            $validated['save_address'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        if ($deliveryMethod === 'delivery' && ! $addressId && empty($validated['delivery_address'])) {
            throw ValidationException::withMessages([
                'delivery_address' => 'Informe o endereço necessário para a entrega.',
            ]);
        }

        if ($deliveryMethod !== 'delivery' && ($addressId || $saveAddress)) {
            throw ValidationException::withMessages([
                'delivery_address' => 'Endereços salvos só podem ser usados em pedidos com entrega.',
            ]);
        }

        if ($addressId && ! $saveAddress) {
            throw ValidationException::withMessages([
                'save_address' => 'Selecione a opção de armazenamento para usar um endereço da conta.',
            ]);
        }

        $tenant = Tenant::current();

        $order = DB::transaction(function () use (
            $validated,
            $tenant,
            $client,
            $couponService,
            $clientSecurity,
            $request,
            $deliveryMethod,
            $addressId,
            $saveAddress
        ) {
            $client = Client::query()
                ->lockForUpdate()
                ->findOrFail($client->id);

            $this->assertStoreIsOpen($tenant);

            $deliveryAddress = $deliveryMethod === 'delivery'
                ? ($validated['delivery_address'] ?? null)
                : null;
            if ($deliveryMethod === 'delivery' && $addressId) {
                if (! $client->address_storage_enabled || ! $client->email_verified_at) {
                    throw ValidationException::withMessages([
                        'address_id' => 'Ative o armazenamento após confirmar o e-mail para usar endereços salvos.',
                    ]);
                }

                $clientSecurity->assertSensitiveAccess($request, $client);
                $savedAddress = $client->addresses()->lockForUpdate()->findOrFail($addressId);
                $deliveryAddress = $savedAddress->only([
                    'title',
                    'recipient_name',
                    'zip_code',
                    'state',
                    'city',
                    'neighborhood',
                    'street',
                    'number',
                    'complement',
                    'reference',
                ]);
            }

            if ($deliveryMethod === 'delivery') {
                if ($saveAddress && (! $client->email || ! $client->email_verified_at)) {
                    throw ValidationException::withMessages([
                        'save_address' => 'Confirme um e-mail para salvar o endereço na conta.',
                    ]);
                }

                if ($saveAddress && ! $addressId) {
                    if ($client->addresses()->count() >= 1) {
                        throw ValidationException::withMessages([
                            'save_address' => 'A conta pode manter no máximo 1 endereço salvo.',
                        ]);
                    }

                    $addressToSave = $deliveryAddress;
                    $addressToSave['title'] = $addressToSave['title'] ?? 'Casa';
                    $addressToSave['is_default'] = filter_var(
                        $addressToSave['is_default'] ?? false,
                        FILTER_VALIDATE_BOOLEAN
                    ) || $client->addresses()->doesntExist();
                    if ($addressToSave['is_default']) {
                        $client->addresses()->update(['is_default' => false]);
                    }
                    $client->addresses()->create($addressToSave);
                }

                $client->address_storage_enabled = $saveAddress;
                $client->save();
            }

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

                if (! $couponQuote['valid']) {
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
                    ->lockForUpdate()
                    ->findOrFail($item['product_id']);

                $quantity = $item['quantity'];
                $this->consumeStock($product, $quantity, $index);

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

            $this->assertStoreIsOpen($tenant);

            $couponCodeToSave = $couponQuote
                ? $couponQuote['coupon']->code
                : null;

            $order = Order::create([
                'tenant_id' => $tenant->id,

                'number' => $this->generateOrderNumber($tenant->id),

                'client_id' => $client->id,
                'customer_name' => $client->name,
                'customer_phone' => $client->phone,
                'customer_email' => $client->email,

                'delivery_address' => $deliveryAddress,
                'delivery_method' => $deliveryMethod,

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

                'placed_at' => now($tenant->timezone ?? config('app.timezone')),
            ]);

            if ($order->total > 0) {
                $placedAt = $order->placed_at;
                $order->payments()->create([
                    'amount' => $order->total,
                    'method' => $order->payment_method,
                    'status' => 'pending',
                    'idempotency_key' => (string) Str::uuid(),
                    'created_at' => $placedAt,
                    'updated_at' => $placedAt,
                ]);
            }

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

        OrderRealtimeChanged::dispatch(
            (int) $tenant->id,
            (int) $order->id,
            $order->status,
            'created',
            $order->updated_at?->toISOString()
        );

        return response()->json([
            'message' => 'Pedido realizado com sucesso!',
            'data' => $order->load('items'),
        ], 201);
    }

    private function assertStoreIsOpen(Tenant $tenant): void
    {
        $contact = Contact::query()
            ->where('tenant_id', $tenant->id)
            ->lockForUpdate()
            ->first(['opening_hours']);

        $isOpen = $this->storeIsOpenAt($contact?->opening_hours, now());

        if ($isOpen === false) {
            throw ValidationException::withMessages([
                'store_hours' => 'O estabelecimento está fechado no momento. Não é possível finalizar o pedido.',
            ]);
        }

        if ($isOpen !== true) {
            throw ValidationException::withMessages([
                'store_hours' => 'Não foi possível verificar o horário de funcionamento do estabelecimento.',
            ]);
        }
    }

    private function storeIsOpenAt(?array $openingHours, CarbonInterface $date): ?bool
    {
        if (! is_array($openingHours) || count($openingHours) !== 7) {
            return null;
        }

        $scheduleByDay = [];

        foreach ($openingHours as $schedule) {
            if (
                ! is_array($schedule)
                || ! array_key_exists('day_of_week', $schedule)
                || ! array_key_exists('is_closed', $schedule)
            ) {
                return null;
            }

            $dayOfWeek = filter_var($schedule['day_of_week'] ?? null, FILTER_VALIDATE_INT);
            $isClosed = filter_var(
                $schedule['is_closed'] ?? null,
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );

            if (
                $dayOfWeek === false
                || $dayOfWeek < 1
                || $dayOfWeek > 7
                || array_key_exists($dayOfWeek, $scheduleByDay)
                || $isClosed === null
            ) {
                return null;
            }

            $openMinutes = $this->timeToMinutes($schedule['open_time'] ?? null);
            $closeMinutes = $this->timeToMinutes($schedule['close_time'] ?? null);

            if (! $isClosed && ($openMinutes === null || $closeMinutes === null)) {
                return null;
            }

            $scheduleByDay[$dayOfWeek] = [
                'is_closed' => $isClosed,
                'open_minutes' => $openMinutes,
                'close_minutes' => $closeMinutes,
            ];
        }

        if (count($scheduleByDay) !== 7) {
            return null;
        }

        $dayOfWeek = (int) $date->format('N');
        $previousDay = $dayOfWeek === 1 ? 7 : $dayOfWeek - 1;
        $currentMinutes = ((int) $date->format('G')) * 60 + (int) $date->format('i');

        return $this->isOpenDuringSchedule(
            $scheduleByDay[$dayOfWeek],
            $currentMinutes
        ) || $this->isOpenDuringSchedule(
            $scheduleByDay[$previousDay],
            $currentMinutes,
            true
        );
    }

    private function isOpenDuringSchedule(
        array $schedule,
        int $currentMinutes,
        bool $isPreviousDay = false
    ): bool {
        if ($schedule['is_closed']) {
            return false;
        }

        $openMinutes = $schedule['open_minutes'];
        $closeMinutes = $schedule['close_minutes'];

        if ($openMinutes < $closeMinutes) {
            return ! $isPreviousDay
                && $currentMinutes >= $openMinutes
                && $currentMinutes < $closeMinutes;
        }

        if ($openMinutes > $closeMinutes) {
            return $isPreviousDay
                ? $currentMinutes < $closeMinutes
                : $currentMinutes >= $openMinutes;
        }

        return false;
    }

    private function timeToMinutes(mixed $time): ?int
    {
        if (! is_string($time) || ! preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time, $matches)) {
            return null;
        }

        return ((int) $matches[1]) * 60 + (int) $matches[2];
    }

    private function consumeStock(Product $product, int $quantity, int $itemIndex): void
    {
        $stock = $product->stock ?? [];

        if (! filter_var($stock['control'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $available = max(0, (int) ($stock['quantity'] ?? 0));

        if ($quantity > $available) {
            throw ValidationException::withMessages([
                "items.{$itemIndex}.quantity" => sprintf(
                    'Estoque insuficiente para %s. Disponível: %d.',
                    $product->title,
                    $available
                ),
            ]);
        }

        $stock['control'] = 1;
        $stock['quantity'] = $available - $quantity;
        $product->stock = $stock;
        $product->save();
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

        if ($order->wasChanged('status')) {
            OrderRealtimeChanged::dispatch(
                (int) $tenant->id,
                (int) $order->id,
                $order->status,
                'status',
                $order->updated_at?->toISOString()
            );
        }

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
            $number = 'PED-'.now()->format('ymd').'-'.Str::upper(
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
