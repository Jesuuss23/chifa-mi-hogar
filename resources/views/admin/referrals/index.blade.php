@extends('layouts.admin')
@section('title', 'Campañas de referidos')
@section('page-title', 'Referidos')
@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    <div class="rounded-2xl border bg-card p-5">
        <h1 class="text-2xl font-bold">Campañas de invitaciones</h1>
        <p class="mt-2">Dos beneficios: descuento para el invitado y cupón para quien comparte su código.</p>
        <p class="mt-2 text-sm">Configura los porcentajes y el tope antes de activar. Los descuentos no incluyen delivery ni se acumulan entre sí.</p>
    </div>
    @if(session('success'))<p role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<div role="alert" class="rounded-xl border p-4">{{ $errors->first() }}</div>@endif
    <form action="{{ route('admin.referrals.store') }}" method="POST" class="space-y-4 rounded-2xl border bg-card p-5">
        @csrf
        <h2 class="text-xl font-bold">Nueva campaña</h2>
        <input type="hidden" name="creation_key" value="{{ old('creation_key', (string) \Illuminate\Support\Str::uuid()) }}">
        <label class="block">Nombre<input class="mt-1 block w-full rounded-xl border p-3" name="name" required maxlength="100" value="{{ old('name') }}"></label>
        <div class="grid gap-4 sm:grid-cols-2">
            <label>Inicio (hora de Lima)<input class="mt-1 block w-full rounded-xl border p-3" type="datetime-local" name="starts_at" required value="{{ old('starts_at') }}"></label>
            <label>Fin (hora de Lima)<input class="mt-1 block w-full rounded-xl border p-3" type="datetime-local" name="ends_at" required value="{{ old('ends_at') }}"></label>
        </div>
        <button class="rounded-full bg-primary px-5 py-3 text-primary-foreground">Guardar borrador</button>
    </form>
    @foreach($campaigns as $campaign)
        <article class="space-y-3 rounded-2xl border bg-card p-5">
            <h2 class="text-xl font-bold">{{ $campaign->name }}</h2>
            <p>{{ ['draft' => 'Borrador', 'active' => 'Activa', 'paused' => 'Pausada', 'ended' => 'Finalizada'][$campaign->status] }} · {{ $counts[$campaign->id] ?? 0 }} invitaciones registradas</p>
            <p class="text-sm">{{ $campaign->starts_at->timezone('America/Lima')->format('d/m/Y H:i') }} — {{ $campaign->ends_at->timezone('America/Lima')->format('d/m/Y H:i') }} (Lima)</p>
            @if($campaign->ends_at->lte(now()))<p>Plazo vencido: no acepta nuevas invitaciones.</p>@endif
            <p class="text-sm">Invitado: {{ $campaign->guest_percent }}% · Quien invita: {{ $campaign->inviter_percent }}% · Tope S/ {{ number_format($campaign->maximum_discount_cents / 100, 2) }}.
                {{ $campaign->include_combos ? 'Con combos.' : 'Sin combos.' }}</p>
            @if(!$campaign->guest_percent || !$campaign->maximum_discount_cents)
                <p class="text-sm">Descuentos deshabilitados hasta configurar porcentaje y tope.</p>
            @endif
            @if(in_array($campaign->status, ['draft', 'paused'], true))
                <details class="rounded-xl border p-4">
                    <summary class="cursor-pointer font-semibold">Configurar descuentos</summary>
                    <form action="{{ route('admin.referrals.terms', $campaign) }}" method="POST" class="mt-4 space-y-4">
                        @csrf @method('PATCH')
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label>Descuento del invitado (%)
                                <input class="mt-1 block w-full rounded-xl border p-3" type="number" name="guest_percent" min="0" max="100" required value="{{ $campaign->guest_percent }}">
                            </label>
                            <label>Cupón de quien invita (%)
                                <input class="mt-1 block w-full rounded-xl border p-3" type="number" name="inviter_percent" min="0" max="100" required value="{{ $campaign->inviter_percent }}">
                            </label>
                            <label>Compra mínima elegible (S/)
                                <input class="mt-1 block w-full rounded-xl border p-3" type="number" name="minimum_amount" min="0" max="10000" step="0.01" required value="{{ number_format($campaign->minimum_cents / 100, 2, '.', '') }}">
                            </label>
                            <label>Descuento máximo por pedido (S/)
                                <input class="mt-1 block w-full rounded-xl border p-3" type="number" name="maximum_discount_amount" min="0" max="1000" step="0.01" required value="{{ number_format($campaign->maximum_discount_cents / 100, 2, '.', '') }}">
                            </label>
                            <label>Vigencia del cupón (días)
                                <input class="mt-1 block w-full rounded-xl border p-3" type="number" name="coupon_days" min="1" max="365" required value="{{ $campaign->coupon_days }}">
                            </label>
                        </div>
                        <input type="hidden" name="include_combos" value="0">
                        <label class="flex items-center gap-3"><input type="checkbox" name="include_combos" value="1" @checked($campaign->include_combos)> Aplicar también a combos</label>
                        <p class="text-sm">Mínimo, tope y combos aplican a ambos beneficios. El porcentaje se calcula sobre productos elegibles y sus adicionales. No incluye delivery. Cero en ambos porcentajes deshabilita los beneficios.</p>
                        <button class="rounded-full bg-primary px-5 py-3 text-primary-foreground">Guardar condiciones</button>
                    </form>
                </details>
            @elseif($campaign->status === 'active')
                <p class="text-sm">Pausa para editar las condiciones. Los pedidos y cupones emitidos no cambian.</p>
            @endif
            @if($campaign->status !== 'ended')
                <form action="{{ route('admin.referrals.update', $campaign) }}" method="POST" class="flex flex-wrap gap-3">
                    @csrf @method('PATCH')
                    @if($campaign->status !== 'active' && $campaign->ends_at->gt(now()))<button name="status" value="active" class="rounded-full bg-primary px-5 py-3 text-primary-foreground">Activar</button>@endif
                    @if($campaign->status === 'active')<button name="status" value="paused" class="rounded-full border px-5 py-3">Pausar</button>@endif
                    <button name="status" value="ended" class="rounded-full border px-5 py-3">Finalizar</button>
                </form>
            @endif
        </article>
    @endforeach
    {{ $campaigns->links() }}
    <section class="space-y-3 rounded-2xl border bg-card p-5">
        <h2 class="text-xl font-bold">Cobros de reparto por revisar</h2>
        <p class="text-sm">Aprueba el referido después de revisar el cobro confirmado por el repartidor.</p>
        @forelse($pendingApprovals as $paidOrder)
            <form method="POST" action="{{ route('admin.referrals.approve', $paidOrder) }}" class="flex flex-wrap items-center gap-3 border-t py-3">
                @csrf
                <a class="underline" href="{{ route('admin.orders.show', $paidOrder) }}">{{ $paidOrder->code }}</a>
                <span>S/ {{ $paidOrder->total }}</span>
                <button class="rounded-full bg-primary px-4 py-2 text-primary-foreground">Validar referido</button>
            </form>
        @empty <p class="text-sm">Sin pendientes.</p> @endforelse
        {{ $pendingApprovals->links() }}
    </section>
    <section class="space-y-3 rounded-2xl border bg-card p-5">
        <h2 class="text-xl font-bold">Cupones emitidos</h2>
        @forelse($coupons as $coupon)
            <article class="border-t py-3">
                <p class="font-semibold">{{ $coupon->user?->name }} · {{ $coupon->percent }}% · tope S/ {{ number_format($coupon->maximum_discount_cents / 100, 2) }}</p>
                <p class="text-sm">{{ $coupon->used_at ? 'Usado' : ($coupon->reserved_order_id ? 'Reservado en pedido' : ($coupon->expires_at->lte(now()) ? 'Vencido' : 'Disponible')) }} · vence {{ $coupon->expires_at->timezone('America/Lima')->format('d/m/Y H:i') }}</p>
                <a class="text-sm underline" href="{{ route('admin.orders.show', $coupon->source_order_id) }}">Ver compra que lo generó</a>
            </article>
        @empty <p class="text-sm">Aún no hay cupones.</p> @endforelse
        {{ $coupons->links() }}
    </section>
</div>
@endsection
