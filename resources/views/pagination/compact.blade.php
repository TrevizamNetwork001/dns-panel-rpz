@if ($paginator->hasPages())
    <nav class="pagination-simple" role="navigation" aria-label="Paginação">
        @if ($paginator->onFirstPage())
            <span class="pagination-disabled" aria-disabled="true">← Anterior</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev">← Anterior</a>
        @endif

        <span>Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}</span>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next">Próxima →</a>
        @else
            <span class="pagination-disabled" aria-disabled="true">Próxima →</span>
        @endif
    </nav>
@endif
