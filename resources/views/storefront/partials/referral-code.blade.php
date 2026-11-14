@if($referralCampaign && auth()->check() && auth()->user()->role === 'customer')
    <section class="mb-6 rounded-2xl border border-border bg-card p-4" aria-label="Invitación">
        @if($referralInvitation)
            @if($referralInvitation->used_at)
                <p>Ya utilizaste tu descuento de invitación.</p>
            @elseif($referralInvitation->reserved_order_id)
                <p>Tu descuento está reservado en un pedido pendiente.</p>
            @elseif((int) $referralInvitation->campaign_id === $referralCampaign->id)
                <p>Código vinculado: {{ $referralCampaign->guest_percent }}% sobre productos elegibles, hasta S/ {{ number_format($referralCampaign->maximum_discount_cents / 100, 2) }}.</p>
                <p class="mt-1 text-sm">Mínimo elegible S/ {{ number_format($referralCampaign->minimum_cents / 100, 2) }}. {{ $referralCampaign->include_combos ? 'Incluye combos.' : 'Sin combos.' }} Sin delivery. Verás el descuento al calcular tu pedido.</p>
            @else
                <p>Tu invitación pertenece a una campaña anterior.</p>
            @endif
        @elseif($canApplyReferral)
            <details>
                <summary class="cursor-pointer font-semibold">¿Tienes un código de invitación?</summary>
                <form method="POST" action="{{ route('customer.referrals.store') }}" class="mt-3 flex flex-wrap items-end gap-3">
                    @csrf
                    <label class="min-w-0 flex-1" for="referral-code">Código
                        <input id="referral-code" name="referral_code" value="{{ old('referral_code') }}" required minlength="24" maxlength="24" pattern="[0-9a-fA-F]{24}" autocomplete="off" autocapitalize="characters" spellcheck="false" class="mt-1 w-full rounded-xl border p-3">
                    </label>
                    <button type="submit" class="rounded-full bg-primary px-5 py-3 text-primary-foreground">Vincular</button>
                    <p class="w-full text-sm">Antes de tu primer pedido. {{ $referralCampaign->guest_percent }}% sobre productos elegibles, hasta S/ {{ number_format($referralCampaign->maximum_discount_cents / 100, 2) }}.
                        Mínimo S/ {{ number_format($referralCampaign->minimum_cents / 100, 2) }}. {{ $referralCampaign->include_combos ? 'Incluye combos.' : 'Sin combos.' }} Sin delivery. Un uso por cuenta.</p>
                </form>
            </details>
        @endif
    </section>
@endif
