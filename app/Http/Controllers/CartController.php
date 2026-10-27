<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\ComboService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Models\ProductRecommendation;
use App\Services\OrderService;

class CartController extends Controller
{
    public function index(Request $request)
    {
        $cart = $this->validatedCart($request);
        $subtotal = collect($cart)->sum('total');

        return view('storefront.cart', compact('cart', 'subtotal'));
    }

    public function store(Request $request)
    {
        if ($request->exists('units')) {
            return $this->storeConfiguredUnits($request);
        }
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'extras' => ['nullable', 'array'],
            'extras.*' => ['integer', 'distinct'],
        ]);

        $product = Product::with('category')
            ->where('is_available', true)
            ->whereHas('category', fn ($query) =>
                $query->where('is_active', true)
            )
            ->findOrFail($data['product_id']);

        $extraIds = collect($data['extras'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $validExtras = $product->extras()
            ->where('is_available', true)
            ->whereIn('extras.id', $extraIds)
            ->get();

        if ($validExtras->count() !== count($extraIds)) {
            throw ValidationException::withMessages([
                'extras' => 'Uno o más adicionales no están disponibles para este plato.',
            ]);
        }

        $cart = $request->session()->get('cart', []);

        // Una combinación distinta de adicionales es una línea distinta.
        $key = hash('sha256', $product->id . ':' . implode(',', $extraIds));

        $currentQuantity = $cart[$key]['quantity'] ?? 0;

        if ($currentQuantity + $data['quantity'] > 20) {
            throw ValidationException::withMessages([
                'quantity' => 'El máximo por combinación es 20 unidades.',
            ]);
        }

        $cart[$key] = [
            'product_id' => $product->id,
            'quantity' => $currentQuantity + $data['quantity'],
            'extra_ids' => $extraIds,
        ];

        $request->session()->put('cart', $cart);

        return redirect()->route('cart.index')
            ->with('success', 'Producto agregado al carrito.');
    }


    public function update(Request $request, string $key)
    {
        $cart = $request->session()->get('cart', []);

        if (!isset($cart[$key])) {
            abort(404);
        }

        /*
        * Compatibilidad con la actualización tradicional.
        * Conservamos el comportamiento anterior.
        */
        if ($request->input('edit_mode') !== 'configuration') {
            $data = $request->validate([
                'quantity' => [
                    'required', 'integer', 'min:1', 'max:20',
                ],
            ]);

            $cart[$key]['quantity'] = (int) $data['quantity'];

            $request->session()->put('cart', $cart);

            return redirect()->route('cart.index');
        }

        /*
        * Nueva edición de configuraciones.
        */
        $data = $request->validate([
            'edit_mode' => [
                'required', 'in:configuration',
            ],
            'edit_scope' => [
                'required', 'in:one,all',
            ],
            'extras' => [
                'nullable', 'array', 'max:200',
            ],
            'extras.*.id' => [
                'required', 'integer', 'distinct', 'min:1',
            ],
            'extras.*.quantity' => [
                'required', 'integer', 'min:0', 'max:50',
            ],
            'components' => [
                'nullable', 'array', 'max:50',
            ],
            'components.*.type' => [
                'required', 'in:product,extra',
            ],
            'components.*.id' => [
                'required', 'integer', 'min:1',
            ],
            'components.*.quantity' => [
                'required', 'integer', 'min:1', 'max:50',
            ],
        ]);

        $entry = $cart[$key];

        $originalQuantity = (int) ($entry['quantity'] ?? 0);

        if ($originalQuantity < 1 || $originalQuantity > 20) {
            throw ValidationException::withMessages([
                'cart' => 'La cantidad del carrito no es válida.',
            ]);
        }

        $type = $entry['type'] ?? 'product';

        /*
        * Preparar nueva configuración.
        */
        if ($type === 'combo') {

            $components = collect($data['components'] ?? [])
                ->map(fn ($component) => [
                    'type' => $component['type'],
                    'id' => (int) $component['id'],
                    'quantity' => (int) $component['quantity'],
                ])
                ->sortBy(fn ($component) =>
                    $component['type'] . ':' .
                    str_pad((string) $component['id'], 12, '0', STR_PAD_LEFT)
                )
                ->values()
                ->all();

            $comboId = (int) ($entry['combo_id'] ?? 0);

            // Validación completa de los componentes.
            app(\App\Services\ComboService::class)->buildLine([
                'type' => 'combo',
                'id' => $comboId,
                'quantity' => 1,
                'components' => $components,
            ]);

            $newEntry = [
                'type' => 'combo',
                'combo_id' => $comboId,
                'quantity' => 1,
                'components' => $components,
            ];

            $identity = [
                'type' => 'combo',
                'id' => $comboId,
                'components' => $components,
            ];

            $newKey = hash(
                'sha256',
                'edited-combo:' . json_encode(
                    $identity,
                    JSON_THROW_ON_ERROR
                )
            );

        } else {

            if (!in_array($type, ['product', 'recommendation'], true)) {
                throw ValidationException::withMessages([
                    'cart' => 'El tipo de artículo no es válido.',
                ]);
            }

            $productId = (int) ($entry['product_id'] ?? 0);

            $product = Product::query()
                ->whereKey($productId)
                ->where('is_available', true)
                ->whereHas('category', fn ($query) =>
                    $query->where('is_active', true)
                )
                ->first();

            if (!$product) {
                throw ValidationException::withMessages([
                    'cart' => 'El producto ya no está disponible.',
                ]);
            }

            $recommendationId = null;

            if ($type === 'recommendation') {
                $recommendationId = (int) (
                    $entry['recommendation_id'] ?? 0
                );

                $recommendation = \App\Models\ProductRecommendation::query()
                    ->whereKey($recommendationId)
                    ->where('product_id', $productId)
                    ->where('is_active', true)
                    ->first();

                if (!$recommendation) {
                    throw ValidationException::withMessages([
                        'cart' => 'La recomendación ya no está disponible.',
                    ]);
                }
            }

            /*
            * Los adicionales con cantidad cero se eliminan.
            */
            $extras = collect($data['extras'] ?? [])
                ->filter(fn ($extra) =>
                    (int) $extra['quantity'] > 0
                )
                ->map(fn ($extra) => [
                    'id' => (int) $extra['id'],
                    'quantity' => (int) $extra['quantity'],
                ])
                ->sortBy('id')
                ->values()
                ->all();

            if (count($extras) > 30) {
                throw ValidationException::withMessages([
                    'extras' => 'Puedes seleccionar como máximo 30 adicionales.',
                ]);
            }

            $id = $type === 'recommendation'
                ? $recommendationId
                : $productId;

            /*
            * OrderService valida los adicionales permitidos
            * y calcula los precios desde la base de datos.
            */
            app(\App\Services\OrderService::class)->previewLine([
                'type' => $type,
                'id' => $id,
                'quantity' => 1,
                'extras_mode' => 'replace',
                'extras' => $extras,
            ]);

            $newEntry = [
                'type' => $type,
                'product_id' => $productId,
                'recommendation_id' => $recommendationId,
                'quantity' => 1,
                'extras_mode' => 'replace',
                'extras' => $extras,
            ];

            /*
            * Misma generación de clave que storeConfiguredUnits().
            */
            $newKey = hash(
                'sha256',
                'configured:' . json_encode([
                    'type' => $type,
                    'id' => $id,
                    'extras' => $extras,
                ], JSON_THROW_ON_ERROR)
            );
        }

        /*
        * Si la configuración no cambió, no duplicar líneas.
        */
        if ($newKey === $key) {
            $newEntry['quantity'] = $originalQuantity;

            $cart[$key] = $newEntry;

            $request->session()->put('cart', $cart);

            return redirect()
                ->route('cart.index')
                ->with('success', 'Configuración actualizada.');
        }

        /*
        * Editar una unidad o todas las unidades agrupadas.
        */
        $movingQuantity = $data['edit_scope'] === 'all'
            ? $originalQuantity
            : 1;

        $existingQuantity = (int) (
            $cart[$newKey]['quantity'] ?? 0
        );

        if ($existingQuantity + $movingQuantity > 20) {
            throw ValidationException::withMessages([
                'cart' => 'El máximo por configuración es de 20 unidades.',
            ]);
        }

        /*
        * Restar las unidades de la configuración original.
        */
        $remainingQuantity = $originalQuantity - $movingQuantity;

        if ($remainingQuantity > 0) {
            $cart[$key]['quantity'] = $remainingQuantity;
        } else {
            unset($cart[$key]);
        }

        /*
        * Incorporar la nueva configuración.
        * Si ya existe una línea idéntica, se agrupan.
        */
        $newEntry['quantity'] =
            $existingQuantity + $movingQuantity;

        $cart[$newKey] = $newEntry;

        if (count($cart) > 30) {
            throw ValidationException::withMessages([
                'cart' => 'El carrito admite como máximo 30 configuraciones.',
            ]);
        }

        $request->session()->put('cart', $cart);

        return redirect()
            ->route('cart.index')
            ->with('success', 'Personalización actualizada correctamente.');
    }


    public function destroy(Request $request, string $key)
    {
        $cart = $request->session()->get('cart', []);

        unset($cart[$key]);

        $request->session()->put('cart', $cart);

        return redirect()->route('cart.index')
            ->with('success', 'Producto retirado del carrito.');
    }

    private function validatedCart(Request $request): array
    {
        $cart = $request->session()->get('cart', []);
        $result = [];
        $cleanCart = [];

        $comboService = app(ComboService::class);

        foreach ($cart as $key => $item) {

            // ================================================
            // COMBOS PERSONALIZADOS
            // ================================================
            if (($item['type'] ?? null) === 'combo') {

                try {
                    $quantity = max(
                        1,
                        min(20, (int) ($item['quantity'] ?? 1))
                    );

                    $line = $comboService->buildLine([
                        'type' => 'combo',
                        'id' => $item['combo_id'] ?? null,
                        'quantity' => $quantity,
                        'components' => $item['components'] ?? [],
                    ]);

                    $combo = \App\Models\Combo::with('mediaAsset')
                        ->find($line['id']);

                    if (!$combo) {
                        continue;
                    }

                    $cleanCart[$key] = [
                        'type' => 'combo',
                        'combo_id' => $combo->id,
                        'quantity' => $quantity,
                        'components' => $item['components'] ?? [],
                    ];

                    $result[$key] = [
                        'key' => $key,
                        'type' => 'combo',
                        'combo' => $combo,
                        'product' => null,
                        'extras' => collect(),
                        'components' => $line['components'],
                        'quantity' => $quantity,
                        'unit_price' => (
                            $line['base_cents']
                            + $line['extras_cents']
                        ) / 100,
                        'total' => $line['line_total_cents'] / 100,
                    ];

                } catch (ValidationException $exception) {
                    // El combo dejó de estar disponible
                    // o cambió la configuración del catálogo.
                    continue;
                }

                continue;
            }

            // ================================================
            // PLATOS TRADICIONALES
            // ================================================

            if (
                in_array(
                    $item['type'] ?? null,
                    ['product', 'recommendation'],
                    true
                )
                && ($item['extras_mode'] ?? null) === 'replace'
            ) {
                try {
                    $type = $item['type'];

                    $quantity = max(
                        1,
                        min(20, (int) ($item['quantity'] ?? 1))
                    );

                    $id = $type === 'recommendation'
                        ? (int) ($item['recommendation_id'] ?? 0)
                        : (int) ($item['product_id'] ?? 0);

                    $line = app(OrderService::class)->previewLine([
                        'type' => $type,
                        'id' => $id,
                        'quantity' => $quantity,
                        'extras_mode' => 'replace',
                        'extras' => $item['extras'] ?? [],
                    ]);

                    $product = Product::query()
                        ->with(['category', 'mediaAsset'])
                        ->whereKey($item['product_id'] ?? 0)
                        ->where('is_available', true)
                        ->whereHas('category', fn ($query) =>
                            $query->where('is_active', true)
                        )
                        ->first();

                    if (!$product) {
                        continue;
                    }

                    $recommendation = null;

                    if ($type === 'recommendation') {
                        $recommendation = ProductRecommendation::query()
                            ->with('mediaAsset')
                            ->whereKey($id)
                            ->where('product_id', $product->id)
                            ->where('is_active', true)
                            ->first();

                        if (!$recommendation) {
                            continue;
                        }
                    } elseif ($id !== (int) $product->id) {
                        continue;
                    }

                    $extras = collect($line['extras'])
                        ->map(function ($extra) {
                            return (object) [
                                'id' => $extra['id'],
                                'name' => $extra['name'],
                                'selected_quantity' => $extra['quantity'],
                                'unit_price' => $extra['unit_cents'] / 100,
                            ];
                        });

                    $cleanCart[$key] = [
                        'type' => $type,
                        'product_id' => $product->id,
                        'recommendation_id' => $recommendation?->id,
                        'quantity' => $quantity,
                        'extras_mode' => 'replace',
                        'extras' => $item['extras'] ?? [],
                    ];

                    $result[$key] = [
                        'key' => $key,
                        'type' => $type,
                        'product' => $product,
                        'recommendation' => $recommendation,
                        'combo' => null,
                        'extras' => $extras,
                        'components' => [],
                        'quantity' => $quantity,
                        'display_name' => $line['name'],
                        'unit_price' => (
                            $line['base_cents'] + $line['extras_cents']
                        ) / 100,
                        'total' => $line['line_total_cents'] / 100,
                    ];

                } catch (ValidationException $exception) {
                    // Conservar la política existente:
                    // omitir líneas que ya no son válidas.
                    continue;
                }

                continue;
            }

            $product = Product::with('category')
                ->where('is_available', true)
                ->whereHas('category', fn ($query) =>
                    $query->where('is_active', true)
                )
                ->find($item['product_id'] ?? null);

            if (!$product) {
                continue;
            }

            $requestedIds = collect($item['extra_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->sort()
                ->values()
                ->all();

            $extras = $product->extras()
                ->where('is_available', true)
                ->whereIn('extras.id', $requestedIds)
                ->get();

            if ($extras->count() !== count($requestedIds)) {
                continue;
            }

            $quantity = max(
                1,
                min(20, (int) ($item['quantity'] ?? 1))
            );

            $unitPrice = (float) $product->price
                + $extras->sum(
                    fn ($extra) => (float) $extra->price
                );

            $cleanCart[$key] = [
                'product_id' => $product->id,
                'quantity' => $quantity,
                'extra_ids' => $requestedIds,
            ];

            $result[$key] = [
                'key' => $key,
                'type' => 'product',
                'product' => $product,
                'combo' => null,
                'extras' => $extras,
                'components' => [],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total' => round($unitPrice * $quantity, 2),
            ];
        }

        $request->session()->put('cart', $cleanCart);

        return $result;
    }

    private function storeConfiguredUnits(Request $request)
    {
        $data = $request->validate([
            'product_id' => [
                'required', 'integer', 'exists:products,id',
            ],
            'quantity' => [
                'required', 'integer', 'min:1', 'max:20',
            ],
            'units' => [
                'required', 'array', 'min:1', 'max:20',
            ],
            'units.*.type' => [
                'required', 'in:product,recommendation',
            ],
            'units.*.id' => [
                'required', 'integer', 'min:1',
            ],
            'units.*.quantity' => [
                'required', 'integer', 'in:1',
            ],
            'units.*.mode' => [
                'required', 'in:keep,edit',
            ],
            'units.*.extras' => [
                'sometimes', 'array', 'max:30',
            ],
            'units.*.extras.*.id' => [
                'required', 'integer', 'min:1',
            ],
            'units.*.extras.*.quantity' => [
                'required', 'integer', 'min:1', 'max:50',
            ],
        ]);

        if (count($data['units']) !== (int) $data['quantity']) {
            throw ValidationException::withMessages([
                'quantity' => 'La cantidad de platos no coincide.',
            ]);
        }

        $product = Product::query()
            ->with('category')
            ->whereKey($data['product_id'])
            ->where('is_available', true)
            ->whereHas('category', function ($query) {
                $query->where('is_active', true);
            })
            ->firstOrFail();

        $orderService = app(OrderService::class);

        $cart = $request->session()->get('cart', []);

        foreach ($data['units'] as $unit) {
            $type = $unit['type'];
            $id = (int) $unit['id'];
            $mode = $unit['mode'];

            $recommendation = null;

            if ($type === 'product') {
                if ($id !== (int) $product->id) {
                    throw ValidationException::withMessages([
                        'units' => 'El plato seleccionado no corresponde al producto.',
                    ]);
                }
            } else {
                $recommendation = ProductRecommendation::query()
                    ->with('extras')
                    ->whereKey($id)
                    ->where('product_id', $product->id)
                    ->where('is_active', true)
                    ->first();

                if (!$recommendation) {
                    throw ValidationException::withMessages([
                        'units' => 'La recomendación seleccionada no está disponible.',
                    ]);
                }
            }

            $extras = [];

            // Mantener recomendación: recuperar los valores
            // originales desde el servidor, no desde JavaScript.
            if ($recommendation && $mode === 'keep') {
                foreach ($recommendation->extras as $extra) {
                    $extras[] = [
                        'id' => (int) $extra->id,
                        'quantity' => (int) $extra->pivot->quantity,
                    ];
                }
            } else {
                // Editar: el cliente envía la configuración final.
                foreach (($unit['extras'] ?? []) as $selected) {
                    $extras[] = [
                        'id' => (int) $selected['id'],
                        'quantity' => (int) $selected['quantity'],
                    ];
                }
            }

            // No aceptar adicionales duplicados.
            $ids = array_column($extras, 'id');

            if (count($ids) !== count(array_unique($ids))) {
                throw ValidationException::withMessages([
                    'units' => 'No se puede repetir un adicional en el mismo plato.',
                ]);
            }

            if (count($extras) > 30) {
                throw ValidationException::withMessages([
                    'units' => 'Demasiados adicionales para un plato.',
                ]);
            }

            usort(
                $extras,
                fn ($a, $b) => $a['id'] <=> $b['id']
            );

            // Validar configuración y precios contra el catálogo.
            // No usamos ningún precio enviado por el cliente.
            $orderService->previewLine([
                'type' => $type,
                'id' => $id,
                'quantity' => 1,
                'extras_mode' => 'replace',
                'extras' => $extras,
            ]);

            // La clave identifica una configuración específica.
            $key = hash(
                'sha256',
                'configured:' . json_encode([
                    'type' => $type,
                    'id' => $id,
                    'extras' => $extras,
                ], JSON_THROW_ON_ERROR)
            );

            $currentQuantity = (int) (
                $cart[$key]['quantity'] ?? 0
            );

            if ($currentQuantity >= 20) {
                throw ValidationException::withMessages([
                    'quantity' =>
                        'El máximo por configuración es 20 platos.',
                ]);
            }

            $cart[$key] = [
                'type' => $type,
                'product_id' => (int) $product->id,
                'recommendation_id' => $recommendation?->id,
                'quantity' => $currentQuantity + 1,
                'extras_mode' => 'replace',
                'extras' => $extras,
            ];
        }

        // Solo persistir si todas las unidades son válidas.
        $request->session()->put('cart', $cart);

        return redirect()
            ->route('cart.index')
            ->with('success', 'Tus platos fueron agregados al carrito.');
    }

}