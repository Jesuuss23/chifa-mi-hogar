@extends('layouts.admin')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-bold">Redes sociales</h1>
        <p class="text-gray-500">
            Configura las redes que aparecerán en tu página web.
        </p>
    </div>

    @if(session('success'))
        <div class="rounded-lg bg-green-100 p-4 text-green-800">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-lg bg-red-100 p-4 text-red-800">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST"
          action="{{ route('admin.social-links.update') }}"
          class="bg-white rounded-xl shadow p-6 space-y-5">
        @csrf
        @method('PUT')

        @foreach($platforms as $key => $label)
            @php
                $link = $links->get($key);
            @endphp

            <div class="space-y-2 border-b pb-4">
                <div class="flex items-center justify-between">
                    <label class="font-semibold">{{ $label }}</label>

                    <label class="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            name="links[{{ $key }}][is_active]"
                            value="1"
                            @checked(old(
                                "links.$key.is_active",
                                $link?->is_active
                            ))
                        >
                        Mostrar
                    </label>
                </div>

                <input
                    type="url"
                    name="links[{{ $key }}][url]"
                    value="{{ old("links.$key.url", $link?->url) }}"
                    placeholder="https://..."
                    class="w-full rounded-lg border-gray-300"
                >
            </div>
        @endforeach

        <button type="submit"
                class="rounded-lg bg-blue-700 px-6 py-3 text-white font-semibold">
            Guardar redes sociales
        </button>
    </form>
</div>
@endsection