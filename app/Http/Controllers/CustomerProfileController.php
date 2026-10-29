<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerProfileController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'regex:/^9[0-9]{8}$/'],
            'delivery_address' => ['nullable', 'required_with:delivery_reference,delivery_latitude,delivery_longitude', 'string', 'max:500'],
            'delivery_reference' => ['nullable', 'string', 'max:500'],
            'delivery_latitude' => ['nullable', 'required_with:delivery_address,delivery_longitude', 'numeric', 'between:-90,90'],
            'delivery_longitude' => ['nullable', 'required_with:delivery_address,delivery_latitude', 'numeric', 'between:-180,180'],
        ], [
            'delivery_latitude.required_with' => 'Marca la ubicación de tu dirección en el mapa.',
            'delivery_longitude.required_with' => 'Marca la ubicación de tu dirección en el mapa.',
            'delivery_address.required_with' => 'Escribe la dirección correspondiente al punto del mapa.',
            'phone.regex' => 'Ingresa un celular de 9 dígitos que empiece por 9.',
        ]);

        DB::transaction(function () use ($request, $data) {
            $user = $request->user();
            $user->name = $data['name'];
            $user->save();
            // Nunca aceptar user_id, role, email o password de este formulario.
            $user->customerProfile()->updateOrCreate([], [
                'phone' => $data['phone'],
                'delivery_address' => $data['delivery_address'] ?? null,
                'delivery_reference' => $data['delivery_reference'] ?? null,
                'delivery_latitude' => $data['delivery_latitude'] ?? null,
                'delivery_longitude' => $data['delivery_longitude'] ?? null,
            ]);
        });

        return redirect()->route('customer.account')->with('profile_saved', true);
    }
}
