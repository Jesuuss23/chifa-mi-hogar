<?php

namespace App\Http\Controllers;

use App\Models\Combo;

class ComboStorefrontController extends Controller
{
    public function index()
    {
        $combos = Combo::query()
            ->with(['mediaAsset', 'items.product', 'items.extra'])
            ->availableNow()
            ->where('show_in_menu', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(12);

        return view('storefront.combos.index', compact('combos'));
    }

    public function show(Combo $combo)
    {
        abort_unless(
            $combo->show_in_menu && Combo::query()->whereKey($combo->id)->availableNow()->exists(),
            404
        );

        $combo->load([
            'mediaAsset',
            'items.product.mediaAsset',
            'items.extra.mediaAsset',
        ]);

        return view('storefront.combos.show', compact('combo'));
    }
}
