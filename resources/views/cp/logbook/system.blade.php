@extends('statamic-logbook::cp.logbook._layout', ['active' => 'system'])

@php
    $b64 = fn($v) => base64_encode((string) $v);

    $levelColor = fn($l) => match (strtolower((string) $l)) {
        'emergency', 'alert', 'critical', 'error' => 'lb-badge lb-badge--error',
        'warning'                                 => 'lb-badge lb-badge--warn',
        'notice', 'info'                          => 'lb-badge lb-badge--info',
        'debug'                                   => 'lb-badge lb-badge--debug',
        default                                   => 'lb-badge lb-badge--muted',
    };
@endphp

@section('panel')
@if(isset($stats))
<div class="lb-stat-grid">
    <div class="lb-stat">
        <p class="lb-stat__label">Last 24h</p>
        <p class="lb-stat__value">{{ $stats['total_24h'] ?? 0 }}</p>
        <p class="lb-stat__meta">Total logs</p>
    </div>

    <div class="lb-stat">
        <p class="lb-stat__label">Errors/Critical (24h)</p>
        <p class="lb-stat__value">{{ $stats['errors_24h'] ?? 0 }}</p>
        <p class="lb-stat__meta">High severity</p>
    </div>

    <div class="lb-stat">
        <p class="lb-stat__label">Warnings (24h)</p>
        <p class="lb-stat__value">{{ $stats['warnings_24h'] ?? 0 }}</p>
        <p class="lb-stat__meta">Investigate</p>
    </div>

    <div class="lb-stat">
        <p class="lb-stat__label">Top levels (7d)</p>
        <div class="lb-stat__breakdown">
            @forelse(($stats['top_levels_7d'] ?? []) as $it)
                <div class="lb-stat__breakdown-row">
                    <span class="lb-stat__breakdown-key">{{ $it['level'] }}</span>
                    <span class="lb-stat__breakdown-val">{{ $it['count'] }}</span>
                </div>
            @empty
                <div class="lb-stat__breakdown-val">—</div>
            @endforelse
        </div>
    </div>
</div>
@endif

<form method="GET" class="lb-filter">
    <div class="lb-filter__row">
        <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="lb-input lb-field-sm" aria-label="From date">
        <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="lb-input lb-field-sm" aria-label="To date">
        <select name="level" class="lb-input lb-field-sm" aria-label="Level">
            <option value="">All levels</option>
            @foreach($levels as $lvl)
                <option value="{{ $lvl }}" @selected(($filters['level'] ?? '') === $lvl)>{{ $lvl }}</option>
            @endforeach
        </select>
    </div>
    <div class="lb-filter__row">
        <input type="text"
               name="q"
               value="{{ $filters['q'] ?? '' }}"
               class="lb-input lb-filter__search"
               placeholder="Search message">
        <button class="lb-btn lb-btn--primary" type="submit">Apply</button>
        <a class="lb-btn" href="{{ cp_route('utilities.logbook.system') }}">Reset</a>
        <a class="lb-btn" href="{{ cp_route('utilities.logbook.system.export', request()->query()) }}">Export CSV</a>
    </div>
</form>

<div class="lb-box lb-box--scroll-x">
    <table class="lb-table">
        <thead>
            <tr>
                <th>Time</th>
                <th>Level</th>
                <th>Message</th>
                <th>User</th>
                <th style="width: 140px;">Details</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $row)
                <tr>
                    <td class="lb-table__time">{{ $row->created_at }}</td>
                    <td>
                        <span class="{{ $levelColor($row->level) }}">
                            {{ strtoupper($row->level) }}
                        </span>
                    </td>
                    <td class="lb-message-cell">
                        <div class="truncate" title="{{ $row->message }}">{{ $row->message }}</div>
                    </td>
                    <td class="lb-table__muted">{{ $row->user_id ?? '—' }}</td>
                    <td>
                        <div style="display: flex; gap: 0.35rem;">
                            @if($row->context)
                                <button type="button"
                                        class="lb-btn"
                                        data-lb-modal-title="Context"
                                        data-lb-modal-payload="{{ $b64($row->context) }}"
                                        data-lb-modal-subtitle="{{ $row->message }}"
                                        title="View context">Context</button>
                            @endif

                            @if($row->request_id)
                                @php
                                    $req = json_encode([
                                        'request_id' => $row->request_id,
                                        'method'     => $row->method,
                                        'url'        => $row->url,
                                        'ip'         => $row->ip,
                                        'user_agent' => $row->user_agent,
                                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                                @endphp
                                <button type="button"
                                        class="lb-btn"
                                        data-lb-modal-title="Request"
                                        data-lb-modal-payload="{{ $b64($req) }}"
                                        data-lb-modal-subtitle="{{ $row->method }} {{ $row->url }}"
                                        title="View request">Request</button>
                            @endif

                            @if(! $row->context && ! $row->request_id)
                                <span class="lb-table__muted">—</span>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="lb-table__muted" style="text-align: center; padding: 1.25rem;">No logs found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if(method_exists($logs, 'links'))
    <div class="lb-pagination">{{ $logs->withQueryString()->links() }}</div>
@endif
@endsection
