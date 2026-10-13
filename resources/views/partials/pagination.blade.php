@if ($paginator->hasPages())
    <nav aria-label="Paginación">
        <ul class="pagination">
            @if ($paginator->onFirstPage())
                <li class="disabled" aria-disabled="true"><span>Anterior</span></li>
            @else
                <li><a href="{{ $paginator->previousPageUrl() }}" rel="prev">Anterior</a></li>
            @endif

            <li class="disabled"><span>Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}</span></li>

            @if ($paginator->hasMorePages())
                <li><a href="{{ $paginator->nextPageUrl() }}" rel="next">Siguiente</a></li>
            @else
                <li class="disabled" aria-disabled="true"><span>Siguiente</span></li>
            @endif
        </ul>
    </nav>
@endif
