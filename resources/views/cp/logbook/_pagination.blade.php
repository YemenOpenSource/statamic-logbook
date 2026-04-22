{{--
    Shared pagination row: Laravel paginator + per-page selector + result
    range readout. Used by both system.blade.php and audit.blade.php to
    keep the footer treatment consistent.

    Inputs:
      $logs            — paginator instance (required)
      $perPage         — currently applied per_page (int)
      $perPageOptions  — allowed per_page values (array of int)
      $route           — the route to the current page (for per_page form action)
--}}
@php
    $hasPaginator = isset($logs) && method_exists($logs, 'total');
    $from = $hasPaginator ? $logs->firstItem() : null;
    $to   = $hasPaginator ? $logs->lastItem()  : null;
    $total = $hasPaginator ? $logs->total()    : null;

    // Carry over every current query param EXCEPT per_page (that's
    // what the selector submits) and page (per-page change should
    // reset to page 1 naturally).
    $carry = collect(request()->query())
        ->except(['per_page', 'page'])
        ->all();
@endphp

@if($hasPaginator && $total > 0)
    <div class="lb-pagination-bar">
        <div class="lb-pagination-bar__meta">
            <span class="lb-pagination-bar__range">
                Showing
                <strong>{{ number_format((int) $from) }}</strong>–<strong>{{ number_format((int) $to) }}</strong>
                of <strong>{{ number_format((int) $total) }}</strong>
            </span>

            <form method="GET"
                  action="{{ $route }}"
                  class="lb-pagination-bar__perpage"
                  data-lb-perpage-form>
                @foreach($carry as $k => $v)
                    @if(is_array($v))
                        @foreach($v as $vv)
                            <input type="hidden" name="{{ $k }}[]" value="{{ $vv }}">
                        @endforeach
                    @else
                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                    @endif
                @endforeach
                <label for="lb-perpage-select" class="lb-pagination-bar__label">Rows per page</label>
                <select id="lb-perpage-select"
                        name="per_page"
                        class="lb-input lb-field-sm"
                        data-lb-perpage-select>
                    @foreach($perPageOptions as $opt)
                        <option value="{{ $opt }}" @selected((int) $perPage === (int) $opt)>{{ $opt }}</option>
                    @endforeach
                </select>
                <noscript>
                    <button type="submit" class="lb-btn lb-btn--sm">Apply</button>
                </noscript>
            </form>
        </div>

        @if(method_exists($logs, 'links'))
            <div class="lb-pagination-bar__nav">
                {{ $logs->withQueryString()->onEachSide(1)->links('statamic-logbook::cp.logbook._paginator') }}
            </div>
        @endif
    </div>
@endif
