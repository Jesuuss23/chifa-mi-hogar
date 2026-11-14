@if($referralCampaign)
<section class="rounded-2xl border border-border bg-card p-5" aria-labelledby="invitation-title">
    <h2 id="invitation-title" class="text-xl font-bold">Tu código de invitación</h2>
    <label for="my-referral-code" class="mt-2 block text-sm">Cópialo y compártelo</label>
    <input id="my-referral-code" readonly value="{{ $referralCode }}" class="mt-2 w-full rounded-xl border p-3 font-mono" autocomplete="off">
    <p class="mt-2 text-sm">{{ $referralCampaign->name }} · Registro de invitaciones, sin premios activos todavía.</p>
</section>
@endif
