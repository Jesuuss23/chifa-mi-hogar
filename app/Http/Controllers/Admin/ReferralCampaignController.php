<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReferralCampaign;
use App\Services\ReferralService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReferralCampaignController extends Controller
{
    public function index()
    {
        $campaigns = ReferralCampaign::latest('id')->paginate(15);
        $counts = DB::table('referral_invitations')->selectRaw('campaign_id, count(*) as total')
            ->groupBy('campaign_id')->pluck('total', 'campaign_id');
        $coupons = \App\Models\ReferralCoupon::with('user')->latest('id')->paginate(15, ['*'], 'coupons_page');
        $pendingApprovals = \App\Models\Order::where('payment_status', 'verified')->where('status', '!=', 'cancelled')->where('total', '>', 0)
            ->whereIn('id', DB::table('referral_order_discounts')->whereNotNull('invitation_id')->whereNull('released_at')->where('terms->inviter_percent', '>', 0)->select('order_id'))
            ->whereNotIn('id', DB::table('referral_coupons')->select('source_order_id'))
            ->whereHas('paymentVerifier', fn ($q) => $q->where('role', 'delivery'))->latest()->paginate(15, ['*'], 'approvals_page');
        return view('admin.referrals.index', compact('campaigns', 'counts', 'coupons', 'pendingApprovals'));
    }

    public function store(Request $request, ReferralService $referrals)
    {
        $data = $request->validate([
            'creation_key' => ['required', 'uuid'], 'name' => ['required', 'string', 'max:100'],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
        ]);
        DB::transaction(function () use ($data, $request, $referrals) {
            $referrals->lockProgram();
            if (ReferralCampaign::where('creation_key', $data['creation_key'])->exists()) {
                return;
            }
            $campaign = ReferralCampaign::create([
                'creation_key' => $data['creation_key'], 'name' => $data['name'], 'status' => 'draft',
                'starts_at' => Carbon::parse($data['starts_at'], 'America/Lima')->setTimezone(config('app.timezone')),
                'ends_at' => Carbon::parse($data['ends_at'], 'America/Lima')->setTimezone(config('app.timezone')),
                'created_by' => $request->user()->id,
            ]);
            $this->event($campaign, $request, 'created');
        }, 3);
        return back()->with('success', 'Campaña guardada como borrador.');
    }

    public function update(Request $request, ReferralCampaign $campaign, ReferralService $referrals)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'paused', 'ended'])]]);
        DB::transaction(function () use ($data, $request, $campaign, $referrals) {
            $referrals->lockProgram();
            $campaign->refresh();
            if ($campaign->status === $data['status']) {
                return;
            }
            if ($campaign->status === 'ended' || ($data['status'] === 'active' && $campaign->ends_at->lte(now()))) {
                throw ValidationException::withMessages(['status' => 'Una campaña finalizada no puede reactivarse.']);
            }
            if ($data['status'] === 'active' && ReferralCampaign::where('status', 'active')->where('id', '!=', $campaign->id)->exists()) {
                throw ValidationException::withMessages(['status' => 'Pausa o finaliza la campaña activa antes de activar otra.']);
            }
            $campaign->update(['status' => $data['status']]);
            $this->event($campaign, $request, $data['status']);
        }, 3);
        return back()->with('success', 'Estado de campaña actualizado.');
    }

    public function terms(Request $request, ReferralCampaign $campaign, ReferralService $referrals)
    {
        $data = $request->validate([
            'guest_percent' => ['required', 'integer', 'between:0,100'],
            'inviter_percent' => ['required', 'integer', 'between:0,100'],
            'minimum_amount' => ['required', 'numeric', 'decimal:0,2', 'between:0,10000'],
            'maximum_discount_amount' => ['required', 'numeric', 'decimal:0,2', 'between:0,1000'],
            'coupon_days' => ['required', 'integer', 'between:1,365'],
            'include_combos' => ['required', 'boolean'],
        ]);
        foreach (['minimum_amount' => 'minimum_cents', 'maximum_discount_amount' => 'maximum_discount_cents'] as $input => $column) {
            [$whole, $fraction] = array_pad(explode('.', (string) $data[$input], 2), 2, '0');
            $data[$column] = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
            unset($data[$input]);
        }
        if (($data['guest_percent'] > 0 || $data['inviter_percent'] > 0) && $data['maximum_discount_cents'] < 1) {
            throw ValidationException::withMessages(['maximum_discount_amount' => 'Indica un tope mayor que cero para habilitar descuentos.']);
        }
        if ($data['inviter_percent'] > 0 && $data['guest_percent'] < 1) {
            throw ValidationException::withMessages(['guest_percent' => 'Habilita el descuento del invitado para generar cupones.']);
        }
        DB::transaction(function () use ($request, $campaign, $referrals, $data) {
            $referrals->lockProgram();
            $campaign->refresh();
            if (!in_array($campaign->status, ['draft', 'paused'], true)) {
                throw ValidationException::withMessages(['campaign' => 'Pausa la campaña antes de cambiar sus condiciones.']);
            }
            $campaign->update($data);
            $this->event($campaign, $request, 'terms_updated');
        }, 3);
        return redirect()->route('admin.referrals.index')->with('success', 'Condiciones guardadas. Los pedidos y cupones anteriores conservan sus condiciones.');
    }

    public function approve(Request $request, \App\Models\Order $order, \App\Services\ReferralDiscountService $discounts)
    {
        DB::transaction(function () use ($request, $order, $discounts) {
            $locked = \App\Models\Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->payment_status !== 'verified' || $locked->status === 'cancelled') {
                throw ValidationException::withMessages(['order' => 'El pedido debe tener el pago verificado.']);
            }
            $discounts->award($locked, $request->user()->id);
        }, 3);
        return redirect()->route('admin.referrals.index')->with('success', 'Referido revisado. Si cumple las condiciones, su cupón está disponible.');
    }

    private function event(ReferralCampaign $campaign, Request $request, string $action): void
    {
        DB::table('referral_campaign_events')->insert([
            'campaign_id' => $campaign->id, 'actor_id' => $request->user()->id,
            'action' => $action, 'created_at' => now(),
        ]);
    }
}
