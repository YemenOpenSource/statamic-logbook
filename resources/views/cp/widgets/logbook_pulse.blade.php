{{--
    Logbook — Live pulse feed widget
    --------------------------------------------------------------
    See logbook_cards.blade.php header for the rationale behind
    the lb-* class namespace. Filters operate client-side via
    event delegation on data-lb-filter / data-lb-type / data-lb-sev
    attributes — no utility class toggles outside the lb-hidden /
    lb-pill--active pair below.
--}}
@php
    $pid = $pulseId ?? 'lb_pulse_'.preg_replace('/\W/', '', uniqid('', true));
@endphp

<div id="{{ $pid }}" class="logbook-pulse-root" v-pre>
    <div class="lb-header">
        <div>
            <p class="lb-header__kicker">Logbook</p>
            <h2 class="lb-header__title">Live feed</h2>
            <p class="lb-header__meta">System + audit · filter without reload</p>
        </div>
        <a href="{{ cp_route('utilities.logbook.system') }}" class="lb-header__link">
            View all <span>→</span>
        </a>
    </div>

    <div class="lb-pulse-filters" role="tablist" aria-label="Feed filter">
        @foreach(['all' => 'All', 'errors' => 'Errors', 'info' => 'System', 'audit' => 'Audit'] as $key => $label)
            <button
                type="button"
                data-lb-filter="{{ $key }}"
                class="lb-pill @if($key === 'all') lb-pill--active @endif"
            >{{ $label }}</button>
        @endforeach
    </div>

    <div class="lb-feed">
        @forelse($items as $item)
            @php
                $isAudit = $item['type'] === 'audit';
                $typeLabel = $isAudit ? 'AUDIT' : 'SYSTEM';
                $meta = $item['meta'] ?? '';
                $levelLine = '';
                $chan = $meta;
                if (!$isAudit && str_contains($meta, '·')) {
                    $parts = array_map('trim', explode('·', $meta, 2));
                    $levelLine = $parts[0] ?? '';
                    $chan = $parts[1] ?? '';
                }
            @endphp
            <div
                class="lb-feed__row logbook-pulse-row"
                data-lb-type="{{ $item['type'] }}"
                data-lb-sev="{{ $item['severity'] }}"
            >
                <div class="lb-feed__inner">
                    <span class="lb-tag {{ $isAudit ? 'lb-tag--audit' : 'lb-tag--system' }}">
                        {{ $typeLabel }}
                    </span>
                    <div class="lb-feed__body">
                        <span class="lb-feed__time">{{ $item['at']->diffForHumans() }}</span>
                        <p class="lb-feed__label">{{ $item['label'] }}</p>
                        <div class="lb-feed__sub">
                            @if($isAudit)
                                <span class="lb-feed__sub--mono">{{ $meta }}</span>
                            @else
                                @if($levelLine !== '')
                                    @php $lvl = strtoupper($levelLine); @endphp
                                    <span class="@if(str_contains($lvl, 'ERROR')) lb-feed__level--error @elseif(str_contains($lvl, 'DEBUG')) lb-feed__level--debug @endif">{{ $lvl }}</span>
                                    <span>·</span>
                                @endif
                                <span class="lb-feed__sub--mono">{{ $chan }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="lb-feed__empty">No recent events yet.</div>
        @endforelse
    </div>
</div>
{{--
    Filter interaction handled by the addon-shipped
    resources/dist/statamic-logbook.js (loaded via $scripts on
    LogbookServiceProvider). Inline <script> tags inside widget
    HTML are stripped by Statamic 6's Vue DynamicHtmlRenderer.
--}}
