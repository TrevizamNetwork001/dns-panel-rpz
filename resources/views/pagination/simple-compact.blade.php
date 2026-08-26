@if ($paginator->hasPages())
    <nav class="pagination-simple" role="navigation" aria-label="Paginação dos domínios">
        @if ($paginator->onFirstPage())
            <span class="pagination-button pagination-disabled" aria-disabled="true">Anterior</span>
        @else
            <a class="pagination-button" href="{{ $paginator->previousPageUrl() }}" rel="prev">Anterior</a>
        @endif

        <span class="pagination-current" aria-current="page">Página {{ $paginator->currentPage() }}</span>

        @if ($paginator->hasMorePages())
            <a class="pagination-button" href="{{ $paginator->nextPageUrl() }}" rel="next">Próxima</a>
        @else
            <span class="pagination-button pagination-disabled" aria-disabled="true">Próxima</span>
        @endif
    </nav>
@endif
