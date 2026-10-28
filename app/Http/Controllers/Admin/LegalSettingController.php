<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StoreSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LegalSettingController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $publish = $request->boolean('legal_pages_published');
        $required = Rule::requiredIf($publish);
        $data = $request->validate([
            'legal_pages_published' => ['required', 'boolean'],
            'legal_address' => [$required, 'nullable', 'string', 'max:500'],
            'privacy_hosting' => [$required, 'nullable', 'string', 'max:3000'],
            'privacy_retention' => [$required, 'nullable', 'string', 'max:3000'],
            'privacy_database' => [$required, 'nullable', 'string', 'max:3000'],
        ]);
        $settings = StoreSetting::current();
        if ($publish && (!filled($settings->business_owner)
            || !filled($settings->privacy_email) || !filled($settings->website_url))) {
            throw ValidationException::withMessages([
                'legal_pages_published' => 'Completa primero el titular, correo y sitio web en Identidad del negocio.',
            ]);
        }
        $data['legal_pages_published'] = $publish;
        $settings->update($data);

        return redirect()->route('admin.settings.edit')
            ->with('success', $publish ? 'Páginas informativas publicadas.' : 'Borrador guardado. Las páginas no están publicadas.');
    }
}
