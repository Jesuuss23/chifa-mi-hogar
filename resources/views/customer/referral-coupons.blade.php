@if(auth()->user()?->role === 'customer')
    @php($availableReferralCoupons = app(\App\Services\ReferralDiscountService::class)->availableCoupons(auth()->user()))
    @if($availableReferralCoupons->isNotEmpty())
        <section class="my-4 space-y-3 rounded-2xl border border-border bg-card p-4" aria-label="Mis cupones">
            <h2 class="font-bold">Mis cupones</h2>
            @if($selectable)
                <label for="referral_coupon_id" class="block text-sm">Usar en este pedido</label>
                <select id="referral_coupon_id" name="referral_coupon_id" class="w-full rounded-xl border border-border bg-card p-3">
                    <option value="">Guardar mis cupones</option>
                    @foreach($availableReferralCoupons as $coupon)
                        <option value="{{ $coupon->id }}" @selected((string) old('referral_coupon_id') === (string) $coupon->id)>
                            {{ $coupon->percent }}% · hasta S/ {{ number_format($coupon->maximum_discount_cents / 100, 2) }} · vence {{ $coupon->expires_at->timezone('America/Lima')->format('d/m/Y H:i') }}
                        </option>
                    @endforeach
                </select>
                <p class="text-xs">Un descuento por pedido. El cupón sustituye al descuento de invitación.</p>
            @endif
            @foreach($availableReferralCoupons as $coupon)
                <p class="text-sm">{{ $coupon->percent }}% · mínimo elegible S/ {{ number_format($coupon->minimum_cents / 100, 2) }} · tope S/ {{ number_format($coupon->maximum_discount_cents / 100, 2) }}.
                    {{ $coupon->include_combos ? 'Incluye combos.' : 'Sin combos.' }} Sin delivery.
                    Vence {{ $coupon->expires_at->timezone('America/Lima')->format('d/m/Y H:i') }} (Lima).
                </p>
            @endforeach
        </section>
    @endif
@endif
