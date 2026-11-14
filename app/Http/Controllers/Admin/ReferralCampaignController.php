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
        return view('admin.referrals.index', compact('campaigns', 'counts'));
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

    private function event(ReferralCampaign $campaign, Request $request, string $action): void
    {
        DB::table('referral_campaign_events')->insert([
            'campaign_id' => $campaign->id, 'actor_id' => $request->user()->id,
            'action' => $action, 'created_at' => now(),
        ]);
    }
}
