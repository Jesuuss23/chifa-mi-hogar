@if($referralCampaign && auth()->check() && auth()->user()->role === 'customer')
    <section class="mb-6 rounded-2xl border border-border bg-card p-4" aria-label="Invitación">
        @if($referralInvitation)
            <p>Código de invitación vinculado a tu cuenta.</p>
        @elseif($canApplyReferral)
            <details>
                <summary class="cursor-pointer font-semibold">¿Tienes un código de invitación?</summary>
                <form method="POST" action="{{ route('customer.referrals.store') }}" class="mt-3 flex flex-wrap items-end gap-3">
                    @csrf
                    <label class="min-w-0 flex-1" for="referral-code">Código
                        <input id="referral-code" name="referral_code" value="{{ old('referral_code') }}" required minlength="24" maxlength="24" pattern="[0-9a-fA-F]{24}" autocomplete="off" autocapitalize="characters" spellcheck="false" class="mt-1 w-full rounded-xl border p-3">
                    </label>
                    <button type="submit" class="rounded-full bg-primary px-5 py-3 text-primary-foreground">Vincular</button>
                    <p class="w-full text-sm">Se vincula una sola vez. No aplica descuentos a este pedido.</p>
                </form>
            </details>
        @endif
    </section>
@endif
