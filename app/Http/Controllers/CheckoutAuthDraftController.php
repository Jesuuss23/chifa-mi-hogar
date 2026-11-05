<?php

namespace App\Http\Controllers;

use App\Services\CheckoutAuthDraft;
use Illuminate\Http\Request;

class CheckoutAuthDraftController extends Controller
{
    public function store(Request $request, string $destination, CheckoutAuthDraft $draft)
    {
        abort_unless(in_array($destination, ['login', 'register'], true), 404);
        if (empty($request->session()->get('cart', []))) {
            $request->session()->forget(CheckoutAuthDraft::KEY);
            return redirect()->route('cart.index');
        }
        $request->validate(['checkout_token' => ['required', 'uuid']]);
        $token = $request->session()->get('checkout_token');
        abort_unless(is_string($token) && hash_equals($token, $request->input('checkout_token')), 419);

        // Incomplete text is allowed here; quote/confirm retain their full validation.
        $data = $request->validate([
            'customer_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'customer_phone' => ['sometimes', 'nullable', 'string', 'max:9'],
            'customer_email' => ['sometimes', 'nullable', 'string', 'max:150'],
            'delivery_address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'delivery_reference' => ['sometimes', 'nullable', 'string', 'max:500'],
            'delivery_latitude' => ['sometimes', 'nullable', 'string', 'max:32'],
            'delivery_longitude' => ['sometimes', 'nullable', 'string', 'max:32'],
            'customer_notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'payment_method' => ['sometimes', 'nullable', 'string', 'in:cash,yape'],
            'cash_payment_type' => ['sometimes', 'nullable', 'string', 'in:exact,change'],
            'cash_received_amount' => ['sometimes', 'nullable', 'string', 'max:20'],
        ]);
        $draft->save($request, $data);
        return redirect()->route($destination === 'login' ? 'login' : 'customer.register', ['continue' => 'checkout']);
    }
}
