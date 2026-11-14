@if($referralCampaign)
<section class="rounded-2xl border border-border bg-card p-5" aria-labelledby="invitation-title">
    <h2 id="invitation-title" class="text-xl font-bold">Tu código de invitación</h2>
    <label for="my-referral-code" class="mt-2 block text-sm">Cópialo y compártelo</label>
    <input id="my-referral-code" readonly value="{{ $referralCode }}" class="mt-2 w-full rounded-xl border p-3 font-mono" autocomplete="off">
    <p class="mt-2 text-sm">{{ $referralCampaign->name }} · @if($referralCampaign->guest_percent && $referralCampaign->maximum_discount_cents)
        Tu invitado recibe {{ $referralCampaign->guest_percent }}%.
        @if($referralCampaign->inviter_percent)
            Tú recibes un cupón de {{ $referralCampaign->inviter_percent }}% al validar su pago.
        @endif
        Tope S/ {{ number_format($referralCampaign->maximum_discount_cents / 100, 2) }}, mínimo elegible S/ {{ number_format($referralCampaign->minimum_cents / 100, 2) }}.
        {{ $referralCampaign->include_combos ? 'Incluye combos.' : 'No incluye combos.' }} No incluye delivery.
        @else Descuentos aún no habilitados. @endif</p>
</section>
@endif

@include('customer.referral-coupons', ['selectable' => false])
