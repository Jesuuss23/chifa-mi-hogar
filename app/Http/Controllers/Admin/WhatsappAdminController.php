<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsappAdminNumber;
use App\Models\WhatsappCommandLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WhatsappAdminController extends Controller
{
    public function index(): View
    {
        $admins = WhatsappAdminNumber::query()
            ->orderBy('name')
            ->get();

        $logs = WhatsappCommandLog::query()
            ->latest('id')
            ->limit(20)
            ->get();

        return view('admin.whatsapp.index', compact(
            'admins',
            'logs'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        WhatsappAdminNumber::create($data);

        return redirect()
            ->route('admin.whatsapp.index')
            ->with('success', 'Administrador de WhatsApp registrado.');
    }

    public function update(
        Request $request,
        WhatsappAdminNumber $admin
    ): RedirectResponse {
        $data = $this->validateData($request, $admin);

        DB::transaction(function () use ($admin, $data) {
            $removingControl = $admin->is_active
                && $admin->can_control_store
                && (
                    !$data['is_active']
                    || !$data['can_control_store']
                );

            if ($removingControl) {
                $controllers = WhatsappAdminNumber::query()
                    ->where('is_active', true)
                    ->where('can_control_store', true)
                    ->lockForUpdate()
                    ->get();

                if ($controllers->count() <= 1) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'can_control_store' =>
                            'Debe existir al menos un administrador activo con permiso para controlar la tienda.',
                    ]);
                }
            }

            $admin->update($data);
        });

        return redirect()
            ->route('admin.whatsapp.index')
            ->with('success', 'Administrador actualizado.');
    }

    private function validateData(
        Request $request,
        ?WhatsappAdminNumber $admin = null
    ): array {
        $request->merge([
            'phone' => preg_replace(
                '/\D+/',
                '',
                (string) $request->input('phone', '')
            ),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],

            'phone' => [
                'required',
                'regex:/^519[0-9]{8}$/',
                Rule::unique('whatsapp_admin_numbers', 'phone')
                    ->ignore($admin?->id),
            ],

            'is_active' => ['required', 'boolean'],
            'can_control_store' => ['required', 'boolean'],
            'receive_orders' => ['required', 'boolean'],
        ], [
            'phone.regex' =>
                'Ingresa un celular peruano válido con código 51.',
            'phone.unique' =>
                'Este número ya está registrado.',
        ]);

        return $validated;
    }
}