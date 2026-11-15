<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

final class CartLimits
{
    public const MAX_LINES = 30;

    public static function ensureCapacity(array $cart): void
    {
        if (count($cart) > self::MAX_LINES) {
            throw ValidationException::withMessages([
                'cart' => 'El carrito admite como máximo 30 configuraciones.',
            ]);
        }
    }
}
