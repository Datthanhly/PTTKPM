@if($paginator->hasPages())
<nav class="clinic-pagination" aria-label="Phân trang">
    <p class="clinic-pagination-summary">
        @if(isset($elements))
            Hiển thị {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} trên tổng {{ $paginator->total() }} kết quả
        @else
            Trang {{ $paginator->currentPage() }}
        @endif
    </p>
    <ul class="clinic-pagination-links">
        <li>
            @if($paginator->onFirstPage())
                <span class="clinic-page-item is-disabled" aria-disabled="true">← Trước</span>
            @else
                <a class="clinic-page-item" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Trang trước">← Trước</a>
            @endif
        </li>
        @isset($elements)
            @foreach($elements as $element)
                @if(is_string($element))
                    <li><span class="clinic-page-ellipsis" aria-hidden="true">{{ $element }}</span></li>
                @else
                    @foreach($element as $page => $url)
                        <li>
                            @if($page === $paginator->currentPage())
                                <span class="clinic-page-item is-current" aria-current="page" aria-label="Trang {{ $page }}, trang hiện tại">{{ $page }}</span>
                            @else
                                <a class="clinic-page-item" href="{{ $url }}" aria-label="Trang {{ $page }}">{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach
        @endisset
        <li>
            @if($paginator->hasMorePages())
                <a class="clinic-page-item" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Trang sau">Sau →</a>
            @else
                <span class="clinic-page-item is-disabled" aria-disabled="true">Sau →</span>
            @endif
        </li>
    </ul>
</nav>
@endif
