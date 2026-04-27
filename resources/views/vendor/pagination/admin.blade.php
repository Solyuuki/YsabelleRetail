@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" class="ys-admin-pagination">
        <div class="ys-admin-pagination-summary">
            Showing {{ $paginator->firstItem() ?? 0 }}-{{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }}
        </div>

        <div class="ys-admin-pagination-links">
            @if ($paginator->onFirstPage())
                <span class="ys-admin-pagination-link is-disabled">Prev</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="ys-admin-pagination-link">Prev</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="ys-admin-pagination-gap">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page === $paginator->currentPage())
                            <span aria-current="page" class="ys-admin-pagination-link is-active">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="ys-admin-pagination-link">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="ys-admin-pagination-link">Next</a>
            @else
                <span class="ys-admin-pagination-link is-disabled">Next</span>
            @endif
        </div>
    </nav>
@endif
