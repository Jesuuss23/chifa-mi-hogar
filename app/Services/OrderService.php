<?php

namespace App\Services;
use App\Jobs\SendOrderWhatsappNotification;
use App\Models\Extra;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductRecommendation;
use App\Models\StoreSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private readonly DeliveryService $deliveryService,
        private readonly ComboService $comboService
    ) {
    }
    /**
     * Registra un pedido completo.
     *
     * Todos los precios se obtienen del servidor.
     * El navegador nunca determina el total.
     */
        public function create(array $data): Order
        {
            return DB::transaction(function () use ($data) {

                $checkoutToken = $data['checkout_token'] ?? null;

                if (!$checkoutToken || !Str::isUuid($checkoutToken)) {
                    throw ValidationException::withMessages([
                        'checkout_token' => 'La sesión de compra no es válida.',
                    ]);
                }

                $settings = StoreSetting::current();

                $settings = StoreSetting::query()
                    ->whereKey($settings->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Comprobar después de adquirir el bloqueo.
                $existingOrder = Order::query()
                    ->where('checkout_token', $checkoutToken)
                    ->first();

                if ($existingOrder) {
                    return $existingOrder;
                }

            /*
            |--------------------------------------------------------------------------
            | 1. Verificar si el negocio acepta pedidos
            |--------------------------------------------------------------------------
            */
            if (!$settings->accepting_orders) {
                throw ValidationException::withMessages([
                    'store' => $settings->closed_message
                        ?: 'Por el momento no estamos recibiendo pedidos.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | 2. Validar modalidad de entrega
            |--------------------------------------------------------------------------
            */

            $deliveryType = $data['delivery_type'] ?? null;

            if (!in_array($deliveryType, ['pickup', 'delivery'], true)) {
                throw ValidationException::withMessages([
                    'delivery_type' => 'Modalidad de entrega no válida.',
                ]);
            }

            $deliveryFeeCents = 0;
            $deliveryDistanceKm = null;

            if ($deliveryType === 'delivery') {

                if (
                    empty($data['delivery_address'])
                    || !isset(
                        $data['delivery_latitude'],
                        $data['delivery_longitude']
                    )
                ) {
                    throw ValidationException::withMessages([
                        'delivery' =>
                            'Debes proporcionar dirección y ubicación GPS.',
                    ]);
                }

                $delivery = $this->deliveryService->calculate(
                    $settings,
                    (float) $data['delivery_latitude'],
                    (float) $data['delivery_longitude']
                );

                $deliveryFeeCents = $delivery['fee_cents'];
                $deliveryDistanceKm = $delivery['distance_km'];
            }

            /*
            |--------------------------------------------------------------------------
            | 3. Calcular productos y extras
            |--------------------------------------------------------------------------
            */

            if (
                !isset($data['items'])
                || !is_array($data['items'])
                || count($data['items']) < 1
                || count($data['items']) > 30
            ) {
                throw ValidationException::withMessages([
                    'items' => 'Debes seleccionar entre 1 y 30 productos.',
                ]);
            }

            $lines = [];
            $subtotalCents = 0;

            foreach ($data['items'] as $item) {
                $line = $this->buildLine($item);

                $subtotalCents += $line['line_total_cents'];
                $lines[] = $line;
            }

            if ($subtotalCents <= 0) {
                throw ValidationException::withMessages([
                    'items' => 'El total de los productos debe ser mayor a cero.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | 4. Calcular total definitivo
            |--------------------------------------------------------------------------
            */

            $totalCents = $subtotalCents + $deliveryFeeCents;

            /*
            |--------------------------------------------------------------------------
            | 5. Validar método de pago y vuelto
            |--------------------------------------------------------------------------
            */

            $paymentMethod = $data['payment_method'] ?? null;

            if (!in_array($paymentMethod, ['yape', 'plin', 'cash'], true)) {
                throw ValidationException::withMessages([
                    'payment_method' => 'Método de pago no válido.',
                ]);
            }

            $cashType = null;
            $cashReceivedCents = null;
            $cashChangeCents = null;

            if ($paymentMethod === 'cash') {

                $cashType = $data['cash_payment_type'] ?? null;

                if (!in_array($cashType, ['exact', 'change'], true)) {
                    throw ValidationException::withMessages([
                        'cash_payment_type' =>
                            'Indica si pagarás exacto o necesitas vuelto.',
                    ]);
                }

                if ($cashType === 'exact') {

                    $cashReceivedCents = $totalCents;
                    $cashChangeCents = 0;

                } else {

                    if (!isset($data['cash_received_amount'])) {
                        throw ValidationException::withMessages([
                            'cash_received_amount' =>
                                'Indica con cuánto dinero pagarás.',
                        ]);
                    }

                    $cashReceivedCents = $this->cents(
                        (string) $data['cash_received_amount']
                    );

                    if ($cashReceivedCents <= $totalCents) {
                        throw ValidationException::withMessages([
                            'cash_received_amount' =>
                                'El monto debe ser mayor al total del pedido.',
                        ]);
                    }

                    $cashChangeCents =
                        $cashReceivedCents - $totalCents;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | 6. Crear pedido principal
            |--------------------------------------------------------------------------
            */

            $order = Order::create([
                'checkout_token' => $checkoutToken,
                'tracking_token' => (string) Str::uuid(),
                'user_id' => auth('web')->id(),
                'code' => 'CHF-' . strtoupper((string) Str::ulid()),

                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'customer_email' => $data['customer_email'] ?? null,

                'delivery_type' => $deliveryType,

                'delivery_address' => $deliveryType === 'delivery'
                    ? $data['delivery_address']
                    : null,

                'delivery_reference' => $deliveryType === 'delivery'
                    ? ($data['delivery_reference'] ?? null)
                    : null,

                'delivery_latitude' => $deliveryType === 'delivery'
                    ? $data['delivery_latitude']
                    : null,

                'delivery_longitude' => $deliveryType === 'delivery'
                    ? $data['delivery_longitude']
                    : null,

                'delivery_distance_km' => $deliveryDistanceKm,

                'currency' => 'PEN',

                'subtotal' => $this->money($subtotalCents),
                'delivery_fee' => $this->money($deliveryFeeCents),
                'discount_amount' => '0.00',
                'total' => $this->money($totalCents),

                'payment_method' => $paymentMethod,
                'payment_status' => 'pending',

                'cash_payment_type' => $cashType,

                'cash_received_amount' =>
                    $cashReceivedCents !== null
                        ? $this->money($cashReceivedCents)
                        : null,

                'cash_change_amount' =>
                    $cashChangeCents !== null
                        ? $this->money($cashChangeCents)
                        : null,

                'status' => 'pending',

                'customer_notes' =>
                    $data['customer_notes'] ?? null,
            ]);

            /*
            |--------------------------------------------------------------------------
            | 7. Guardar productos del pedido
            |--------------------------------------------------------------------------
            */

            foreach ($lines as $line) {

                $orderItem = $order->items()->create([
                    'item_type' => $line['type'],
                    'catalog_item_id' => $line['id'],

                    'item_name' => $line['name'],
                    'item_description' => $line['description'],

                    'quantity' => $line['quantity'],

                    'unit_price' => $this->money(
                        $line['base_cents']
                    ),

                    'extras_unit_total' => $this->money(
                        $line['extras_cents']
                    ),

                    'line_total' => $this->money(
                        $line['line_total_cents']
                    ),

                    'notes' => $line['notes'],
                ]);

                /*
                | Guardar extras del producto
                */

                foreach ($line['extras'] as $extra) {

                    $orderItem->extras()->create([
                        'extra_id' => $extra['id'],
                        'extra_name' => $extra['name'],
                        'quantity' => $extra['quantity'],

                        'unit_price' => $this->money(
                            $extra['unit_cents']
                        ),

                        'line_total' => $this->money(
                            $extra['unit_cents']
                            * $extra['quantity']
                        ),
                    ]);
                }
                if ($line['type'] === 'combo') {
                    foreach ($line['components'] as $component) {
                        $orderItem->comboComponents()->create([
                            'component_type' => $component['type'],
                            'catalog_item_id' => $component['id'],
                            'component_name' => $component['name'],
                            'included_quantity' => $component['included_quantity'],
                            'selected_quantity' => $component['selected_quantity'],
                            'additional_quantity' => $component['additional_quantity'],
                            'unit_price' => $this->money($component['unit_cents']),
                            'additional_total' => $this->money(
                                $component['additional_total_cents']
                            ),
                        ]);
                    }
                }
            }

            /*
            |--------------------------------------------------------------------------
            | 8. Registrar historial inicial
            |--------------------------------------------------------------------------
            */

            $order->statusHistories()->create([
                'from_status' => null,
                'to_status' => 'pending',
                'comment' => 'Pedido registrado correctamente.',
                'changed_by' => null,
            ]);

            $order->load([
                'items.extras',
                'items.comboComponents',
            ]);

            SendOrderWhatsappNotification::dispatch($order->id)
                ->onQueue('whatsapp-orders')
                ->afterCommit();

            return $order;
        }, 3);
    }

    /**
     * Calcula una línea del carrito.
     */
    private function buildLine(array $item): array
    {
        $type = $item['type'] ?? null;
        $id = (int) ($item['id'] ?? 0);
        $quantity = (int) ($item['quantity'] ?? 0);
        if ($type === 'combo') {
            return $this->comboService->buildLine($item);
        }
        if (!in_array($type, ['product', 'recommendation'], true)) {
            throw ValidationException::withMessages([
                'items' => 'Tipo de producto no válido.',
            ]);
        }

        if ($id < 1 || $quantity < 1 || $quantity > 50) {
            throw ValidationException::withMessages([
                'items' => 'Producto o cantidad no válida.',
            ]);
        }

        $extras = [];
        $baseCents = 0;
        $name = '';
        $description = null;

        /*
        |--------------------------------------------------------------------------
        | Producto normal
        |--------------------------------------------------------------------------
        */

        if ($type === 'product') {

            $product = Product::query()
                ->whereKey($id)
                ->where('is_available', true)
                ->first();

            if (!$product) {
                throw ValidationException::withMessages([
                    'items' => 'Uno de los productos no está disponible.',
                ]);
            }

            $baseCents = $this->cents($product->price);
            $name = $product->name;
            $description = $product->description;

        } else {

            /*
            |--------------------------------------------------------------------------
            | Recomendación: producto principal + extras incluidos
            |--------------------------------------------------------------------------
            */

            $recommendation = ProductRecommendation::query()
                ->with(['product', 'extras'])
                ->whereKey($id)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->first();

            if (
                !$recommendation
                || !$recommendation->product
                || !$recommendation->product->is_available
            ) {
                throw ValidationException::withMessages([
                    'items' => 'La recomendación no está disponible.',
                ]);
            }

            $product = $recommendation->product;

            $baseCents = $this->cents($product->price);
            $name = $recommendation->name;
            $description = $recommendation->description;

            foreach ($recommendation->extras as $extra) {

                if (!$extra->is_available) {
                    throw ValidationException::withMessages([
                        'items' =>
                            'Un extra de la recomendación no está disponible.',
                    ]);
                }

                $includedQuantity = (int) $extra->pivot->quantity;

                if ($includedQuantity < 1 || $includedQuantity > 50) {
                    throw ValidationException::withMessages([
                        'items' => 'Cantidad de extras inválida.',
                    ]);
                }

                $extras[$extra->id] = [
                    'id' => $extra->id,
                    'name' => $extra->name,
                    'quantity' => $includedQuantity,
                    'unit_cents' => $this->cents($extra->price),
                ];
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Extras adicionales elegidos por el cliente
        |--------------------------------------------------------------------------
        */

        $selectedExtras = $item['extras'] ?? [];

        if (!is_array($selectedExtras) || count($selectedExtras) > 30) {
            throw ValidationException::withMessages([
                'items' => 'Lista de extras no válida.',
            ]);
        }

        $allowedExtraIds = $product->extras()
            ->pluck('extras.id')
            ->map(fn ($value) => (int) $value)
            ->all();

        foreach ($selectedExtras as $selected) {

            $extraId = (int) ($selected['id'] ?? 0);
            $extraQuantity = (int) ($selected['quantity'] ?? 0);

            if ($extraId < 1 || $extraQuantity < 1 || $extraQuantity > 50) {
                throw ValidationException::withMessages([
                    'items' => 'Cantidad de extra no válida.',
                ]);
            }

            if (!in_array($extraId, $allowedExtraIds, true)) {
                throw ValidationException::withMessages([
                    'items' =>
                        'El extra seleccionado no corresponde al plato.',
                ]);
            }

            $extra = Extra::query()
                ->whereKey($extraId)
                ->where('is_available', true)
                ->first();

            if (!$extra) {
                throw ValidationException::withMessages([
                    'items' => 'El extra seleccionado no está disponible.',
                ]);
            }

            if (isset($extras[$extraId])) {
                $extras[$extraId]['quantity'] += $extraQuantity;
            } else {
                $extras[$extraId] = [
                    'id' => $extra->id,
                    'name' => $extra->name,
                    'quantity' => $extraQuantity,
                    'unit_cents' => $this->cents($extra->price),
                ];
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Calcular precio de extras por unidad de plato
        |--------------------------------------------------------------------------
        */

        $extrasCents = 0;

        foreach ($extras as $extra) {

            if ($extra['quantity'] < 1 || $extra['quantity'] > 50) {
                throw ValidationException::withMessages([
                    'items' => 'La cantidad total de extras supera el límite.',
                ]);
            }

            $extrasCents +=
                $extra['unit_cents'] * $extra['quantity'];
        }

        if ($baseCents < 0 || $extrasCents < 0) {
            throw ValidationException::withMessages([
                'items' => 'Se encontró un precio inválido.',
            ]);
        }

        $lineTotalCents =
            ($baseCents + $extrasCents) * $quantity;

        return [
            'type' => $type,
            'id' => $id,
            'name' => $name,
            'description' => $description,
            'quantity' => $quantity,

            'base_cents' => $baseCents,
            'extras_cents' => $extrasCents,
            'line_total_cents' => $lineTotalCents,

            'extras' => array_values($extras),
            'notes' => $item['notes'] ?? null,
        ];
    }

    /**
     * Convierte soles a céntimos sin operar con precios
     * de punto flotante.
     */
    private function cents(string|int|float $amount): int
    {
        $value = (string) $amount;

        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw ValidationException::withMessages([
                'items' => 'Precio inválido en el catálogo.',
            ]);
        }

        [$whole, $fraction] = array_pad(
            explode('.', $value, 2),
            2,
            '0'
        );

        return ((int) $whole * 100)
            + (int) str_pad($fraction, 2, '0');
    }

    /**
     * Convierte céntimos a formato decimal para PostgreSQL.
     */
    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /**
     * Calcula una cotización sin guardar el pedido.
     *
     * Los precios siempre se consultan desde PostgreSQL.
     */
    public function quote(array $data): array
    {
        $settings = StoreSetting::current();

        if (!$settings->accepting_orders) {
            throw ValidationException::withMessages([
                'store' => $settings->closed_message
                    ?: 'Por el momento no estamos recibiendo pedidos.',
            ]);
        }

        $deliveryType = $data['delivery_type'] ?? null;

        if (!in_array($deliveryType, ['pickup', 'delivery'], true)) {
            throw ValidationException::withMessages([
                'delivery_type' => 'Modalidad de entrega no válida.',
            ]);
        }

        $deliveryFeeCents = 0;
        $deliveryDistanceKm = null;

        if ($deliveryType === 'delivery') {
            if (
                !isset(
                    $data['delivery_latitude'],
                    $data['delivery_longitude']
                )
            ) {
                throw ValidationException::withMessages([
                    'delivery' => 'Selecciona tu ubicación de entrega.',
                ]);
            }

            $delivery = $this->deliveryService->calculate(
                $settings,
                (float) $data['delivery_latitude'],
                (float) $data['delivery_longitude']
            );

            $deliveryFeeCents = $delivery['fee_cents'];
            $deliveryDistanceKm = $delivery['distance_km'];
        }

        $items = $data['items'] ?? [];

        if (!is_array($items) || count($items) < 1 || count($items) > 30) {
            throw ValidationException::withMessages([
                'items' => 'Selecciona entre 1 y 30 productos.',
            ]);
        }

        $lines = [];
        $subtotalCents = 0;

        foreach ($items as $item) {
            $line = $this->buildLine($item);
            $lines[] = $line;
            $subtotalCents += $line['line_total_cents'];
        }

        if ($subtotalCents <= 0) {
            throw ValidationException::withMessages([
                'items' => 'El subtotal debe ser mayor a cero.',
            ]);
        }

        $totalCents = $subtotalCents + $deliveryFeeCents;

        $paymentMethod = $data['payment_method'] ?? null;

        if (!in_array($paymentMethod, ['yape', 'plin', 'cash'], true)) {
            throw ValidationException::withMessages([
                'payment_method' => 'Método de pago no válido.',
            ]);
        }

        $cashType = null;
        $cashReceivedCents = null;
        $cashChangeCents = null;

        if ($paymentMethod === 'cash') {
            $cashType = $data['cash_payment_type'] ?? null;

            if (!in_array($cashType, ['exact', 'change'], true)) {
                throw ValidationException::withMessages([
                    'cash_payment_type' =>
                        'Indica si pagarás exacto o necesitas vuelto.',
                ]);
            }

            if ($cashType === 'exact') {
                $cashReceivedCents = $totalCents;
                $cashChangeCents = 0;
            } else {
                if (!isset($data['cash_received_amount'])) {
                    throw ValidationException::withMessages([
                        'cash_received_amount' =>
                            'Indica con cuánto dinero pagarás.',
                    ]);
                }

                $cashReceivedCents = $this->cents(
                    (string) $data['cash_received_amount']
                );

                if ($cashReceivedCents <= $totalCents) {
                    throw ValidationException::withMessages([
                        'cash_received_amount' =>
                            'El monto debe superar el total del pedido.',
                    ]);
                }

                $cashChangeCents = $cashReceivedCents - $totalCents;
            }
        }

        return [
            'currency' => 'PEN',
            'delivery_type' => $deliveryType,
            'delivery_distance_km' => $deliveryDistanceKm,
            'subtotal' => $this->money($subtotalCents),
            'delivery_fee' => $this->money($deliveryFeeCents),
            'discount_amount' => '0.00',
            'total' => $this->money($totalCents),
            'payment_method' => $paymentMethod,
            'cash_payment_type' => $cashType,
            'cash_received_amount' => $cashReceivedCents !== null
                ? $this->money($cashReceivedCents)
                : null,
            'cash_change_amount' => $cashChangeCents !== null
                ? $this->money($cashChangeCents)
                : null,
            'items' => $lines,
        ];
    }
}
