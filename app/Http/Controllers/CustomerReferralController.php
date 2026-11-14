<?php

namespace App\Http\Controllers;

use App\Services\ReferralService;
use Illuminate\Http\Request;

class CustomerReferralController extends Controller
{
    public function store(Request $request, ReferralService $referrals)
    {
        $data = $request->validate([
            'referral_code' => ['required', 'string', 'regex:/\A[0-9a-fA-F]{24}\z/'],
        ]);
        $referrals->apply($request->user(), $data['referral_code']);
        return redirect()->route('cart.index')->with('success', 'Código de invitación vinculado.');
    }
}
