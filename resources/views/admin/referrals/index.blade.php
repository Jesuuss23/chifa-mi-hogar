@extends('layouts.admin')
@section('title', 'Campañas de referidos')
@section('page-title', 'Referidos')
@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    <div class="rounded-2xl border bg-card p-5">
        <h1 class="text-2xl font-bold">Campañas de invitaciones</h1>
        <p class="mt-2">Primera etapa: registra invitaciones. Todavía no genera giros, cupones ni compras válidas.</p>
        <p class="mt-2 text-sm">Activa una campaña solo para probar esta etapa. El banner se administra por separado.</p>
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
</div>
@endsection
