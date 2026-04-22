{{--
    Logbook — Volume trends (stacked bars) widget
    --------------------------------------------------------------
    Premium-minimal redesign (v2). Columns proportion their stack
    segments via `flex: <count> 1 0`; the overall bar height is a
    percentage of the widest day in-window. CSS-only — no chart
    library is pulled in.
--}}
@php
    $n = max(1, count($bars));
    $totals = [];
    foreach ($bars as $b) {
        $info = $b['system_info'] ?? max(0, ($b['system'] ?? 0) - ($b['errors'] ?? 0));
        $totals[] = (int) ($b['errors'] ?? 0) + (int) $info + (int) ($b['audit'] ?? 0);
    }
    $maxTotal = max($totals) ?: 1;
    $sumAll   = array_sum($totals);
    $sumErr   = array_sum(array_map(fn ($b) => (int) ($b['errors'] ?? 0), $bars));
@endphp

<div>
    <div class="lb-header">
        <div>
            <p class="lb-header__kicker">Logbook</p>
            <h2 class="lb-header__title">Volume by day</h2>
            <p class="lb-header__meta">Stacked column height = daily total · hover a column for the breakdown</p>
        </div>
        <a href="{{ cp_route('utilities.logbook.system') }}" class="lb-header__link">
            Explore <span aria-hidden="true">→</span>
        </a>
    </div>

    <div class="lb-chart" style="margin-top: 0.75rem;">
        <div class="lb-legend">
            <span class="lb-legend__chip lb-legend__chip--errors">Errors</span>
            <span class="lb-legend__chip lb-legend__chip--system">System</span>
            <span class="lb-legend__chip lb-legend__chip--audit">Audit</span>
            @if($sumAll > 0)
                <span class="lb-legend__chip" style="color: var(--lb-text-muted);" title="Window total · errors in window">
                    {{ number_format($sumAll) }} events · {{ number_format($sumErr) }} errors
                </span>
            @endif
        </div>

        @if($sumAll === 0)
            <div class="lb-empty">
                <div class="lb-empty__icon" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="12" width="4" height="8" rx="1"/><rect x="10" y="7" width="4" height="13" rx="1"/><rect x="17" y="3" width="4" height="17" rx="1"/></svg>
                </div>
                <p class="lb-empty__title">No volume in window</p>
                <p class="lb-empty__hint">Bars will populate as system &amp; audit events land.</p>
            </div>
        @else
            <div class="lb-bars" style="grid-template-columns: repeat({{ $n }}, minmax(0, 1fr));">
                @foreach($bars as $bar)
                    @php
                        $e = (int) ($bar['errors'] ?? 0);
                        $s = (int) ($bar['system_info'] ?? max(0, ($bar['system'] ?? 0) - $e));
                        $a = (int) ($bar['audit'] ?? 0);
                        $total = $e + $s + $a;
                        $pct = $maxTotal > 0 ? ($total / $maxTotal) * 100 : 0;
                        $tip = "$e errors · $s system · $a audit";
                    @endphp
                    <div class="lb-bar" title="{{ $bar['label'] }} · {{ $tip }}">
                        <div class="lb-bar__shell">
                            @if($total > 0)
                                <div class="lb-bar__stack" style="height: {{ max($pct, 8) }}%;">
                                    @if($e > 0)
                                        <div class="lb-bar__seg lb-bar__seg--errors" style="flex: {{ $e }} 1 0;"></div>
                                    @endif
                                    @if($s > 0)
                                        <div class="lb-bar__seg lb-bar__seg--system" style="flex: {{ $s }} 1 0;"></div>
                                    @endif
                                    @if($a > 0)
                                        <div class="lb-bar__seg lb-bar__seg--audit" style="flex: {{ $a }} 1 0;"></div>
                                    @endif
                                </div>
                            @else
                                <div class="lb-bar__zero"></div>
                            @endif
                        </div>
                        <span class="lb-bar__label">{{ $bar['label'] }}</span>
                        <span class="lb-bar__nums">{{ $e }} · {{ $s }} · {{ $a }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
