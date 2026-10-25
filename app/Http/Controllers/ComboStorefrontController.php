<?php

namespace App\Http\Controllers;

use App\Models\Combo;

class ComboStorefrontController extends Controller
{
    public function index()
    {
        $combos = Combo::query()
            ->with(['mediaAsset', 'items.product', 'items.extra'])
            ->where('is_available', true)
            ->where('show_in_menu', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(12);

        return view('storefront.combos.index', compact('combos'));
    }

    public function show(Combo $combo)
    {
        abort_unless(
            $combo->is_available && $combo->show_in_menu,
            404
        );

        $combo->load([
            'mediaAsset',
            'items.product',
            'items.extra',
        ]);

        return view('storefront.combos.show', compact('combo'));
    }
}
