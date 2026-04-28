@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" class="ys-storefront-pagination">
        <div class="ys-storefront-pagination-links">
            @if ($paginator->onFirstPage())
                <span class="ys-storefront-pagination-link is-disabled">Previous</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="ys-storefront-pagination-link">Previous</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="ys-storefront-pagination-gap">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page === $paginator->currentPage())
                            <span aria-current="page" class="ys-storefront-pagination-link is-active">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="ys-storefront-pagination-link">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="ys-storefront-pagination-link">Next</a>
            @else
                <span class="ys-storefront-pagination-link is-disabled">Next</span>
            @endif
        </div>
    </nav>
@endif
