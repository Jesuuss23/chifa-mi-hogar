@if(\App\Models\StoreSetting::current()->legalPagesAvailable())
    <p class="text-sm text-foreground/75">
        Utilizamos tus datos para gestionar la cuenta o el pedido que solicitas.
        Consulta <a class="font-semibold underline" href="{{ route('legal.privacy') }}">cómo tratamos tus datos</a>.
    </p>
@endif
