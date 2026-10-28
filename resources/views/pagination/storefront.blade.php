@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Paginación del catálogo" class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex w-full items-center justify-between gap-4 sm:hidden">
            @if ($paginator->onFirstPage())
                <span aria-disabled="true" class="border border-border px-4 py-3 text-foreground/75">Anterior</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="border border-border bg-card px-4 py-3 hover:bg-accent">Anterior</a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="border border-border bg-card px-4 py-3 hover:bg-accent">Siguiente</a>
            @else
                <span aria-disabled="true" class="border border-border px-4 py-3 text-foreground/75">Siguiente</span>
            @endif
        </div>

        <p class="hidden text-sm text-foreground/75 sm:block">
            Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}
        </p>

        <div class="hidden flex-wrap items-center gap-2 sm:flex">
            @unless ($paginator->onFirstPage())
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Página anterior"
                   class="inline-flex min-h-11 min-w-11 items-center justify-center border border-border bg-card px-3 hover:bg-accent">←</a>
            @endunless

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span aria-disabled="true" class="px-2">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" aria-label="Página {{ $page }}"
                                  class="inline-flex min-h-11 min-w-11 items-center justify-center border border-primary bg-primary px-3 font-bold text-primary-foreground">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" aria-label="Ir a la página {{ $page }}"
                               class="inline-flex min-h-11 min-w-11 items-center justify-center border border-border bg-card px-3 hover:bg-accent">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Página siguiente"
                   class="inline-flex min-h-11 min-w-11 items-center justify-center border border-border bg-card px-3 hover:bg-accent">→</a>
            @endif
        </div>
    </nav>
@endif
