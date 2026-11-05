<?php

namespace App\Services;

use Illuminate\Http\Request;

class CustomerCheckoutReturn
{
    private const KEY = 'customer_checkout_return';

    public function capture(Request $request): void
    {
        if (! $request->query->has('continue')) {
            return;
        }
        $request->session()->forget(self::KEY);
        if ($request->query('continue') === 'checkout' && $this->hasCart($request)) {
            $request->session()->put(self::KEY, now()->timestamp);
        }
    }

    public function active(Request $request): bool
    {
        $started = $request->session()->get(self::KEY);
        return is_int($started) && now()->timestamp >= $started && now()->timestamp - $started <= 1800;
    }

    public function destination(Request $request): ?string
    {
        $active = $this->active($request);
        $request->session()->forget(self::KEY);
        if (! $active) {
            return null;
        }
        // Never accept URLs or route names from query/form/OAuth parameters.
        $request->session()->forget('url.intended');
        return $this->hasCart($request) ? 'cart.checkout' : 'cart.index';
    }

    private function hasCart(Request $request): bool
    {
        $cart = $request->session()->get('cart');
        return is_array($cart) && count($cart) > 0;
    }
}
