{{--
    Custom Laravel paginator markup that matches the addon design
    tokens. Drop-in replacement for the default "bootstrap-4" paginator
    — invoked from _pagination.blade.php via ->links('…_paginator').

    The default Laravel bootstrap-4 paginator emits Bootstrap classes
    we don't ship, so rows above were rendering as plain text links.
    This version emits .lb-page-btn chip buttons and a compact
    "Prev / 1 · 2 · … / Next" shape.
--}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="lb-pager">
        {{-- Previous --}}
        @if ($paginator->onFirstPage())
            <span class="lb-page-btn lb-page-btn--disabled" aria-disabled="true" aria-label="@lang('pagination.previous')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                <span class="lb-page-btn__label">Prev</span>
            </span>
        @else
            <a class="lb-page-btn" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="@lang('pagination.previous')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                <span class="lb-page-btn__label">Prev</span>
            </a>
        @endif

        {{-- Numeric window --}}
        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="lb-page-btn lb-page-btn--dots" aria-hidden="true">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="lb-page-btn lb-page-btn--active" aria-current="page">{{ $page }}</span>
                    @else
                        <a class="lb-page-btn" href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Next --}}
        @if ($paginator->hasMorePages())
            <a class="lb-page-btn" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="@lang('pagination.next')">
                <span class="lb-page-btn__label">Next</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
            </a>
        @else
            <span class="lb-page-btn lb-page-btn--disabled" aria-disabled="true" aria-label="@lang('pagination.next')">
                <span class="lb-page-btn__label">Next</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
            </span>
        @endif
    </nav>
@endif
