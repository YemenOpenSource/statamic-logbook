@extends('statamic-logbook::cp.logbook._layout', ['active' => 'audit'])

@php
    $b64 = fn($v) => base64_encode((string) $v);

    $actionColor = fn($a) => match (true) {
        str_contains((string) $a, 'deleted')                                             => 'lb-badge lb-badge--delete',
        str_contains((string) $a, 'created')                                             => 'lb-badge lb-badge--create',
        str_contains((string) $a, 'updated') || str_contains((string) $a, 'saved')      => 'lb-badge lb-badge--update',
        default                                                                          => 'lb-badge lb-badge--muted',
    };
@endphp

@section('panel')
@if(isset($stats))
<div class="lb-stat-grid">
    <div class="lb-stat">
        <p class="lb-stat__label">Last 24h</p>
        <p class="lb-stat__value">{{ $stats['total_24h'] ?? 0 }}</p>
        <p class="lb-stat__meta">Total audit actions</p>
    </div>

    <div class="lb-stat">
        <p class="lb-stat__label">Top actions (7d)</p>
        <div class="lb-stat__breakdown">
            @forelse(($stats['top_actions_7d'] ?? []) as $it)
                <div class="lb-stat__breakdown-row">
                    <span class="lb-stat__breakdown-key" title="{{ $it['action'] }}">{{ $it['action'] }}</span>
                    <span class="lb-stat__breakdown-val">{{ $it['count'] }}</span>
                </div>
            @empty
                <div class="lb-stat__breakdown-val">—</div>
            @endforelse
        </div>
    </div>

    <div class="lb-stat">
        <p class="lb-stat__label">Top users (7d)</p>
        <div class="lb-stat__breakdown">
            @forelse(($stats['top_users_7d'] ?? []) as $it)
                <div class="lb-stat__breakdown-row">
                    <span class="truncate" title="{{ $it['user'] }}">{{ $it['user'] }}</span>
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
        <select name="action" class="lb-input lb-field-md" aria-label="Action">
            <option value="">All actions</option>
            @foreach($actions as $a)
                <option value="{{ $a }}" @selected(($filters['action'] ?? '') === $a)>{{ $a }}</option>
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
        <a class="lb-btn" href="{{ cp_route('utilities.logbook.audit') }}">Reset</a>
        <a class="lb-btn" href="{{ cp_route('utilities.logbook.audit.export', request()->query()) }}">Export CSV</a>
    </div>
</form>

<div class="lb-box lb-box--scroll-x">
    <table class="lb-table">
        <thead>
            <tr>
                <th>Time</th>
                <th>Action</th>
                <th>Subject</th>
                <th>User</th>
                <th style="width: 140px;">Changes</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $row)
                <tr>
                    <td class="lb-table__time">{{ $row->created_at }}</td>
                    <td>
                        <span class="{{ $actionColor($row->action) }}">
                            {{ $row->action }}
                        </span>
                    </td>
                    <td>
                        <div style="font-weight: 500;">{{ $row->subject_title ?? $row->subject_handle }}</div>
                        <div class="lb-table__muted">{{ $row->subject_type }} · {{ $row->subject_id }}</div>
                    </td>
                    <td class="lb-table__muted">{{ $row->user_email ?? $row->user_id ?? '—' }}</td>
                    <td>
                        @if($row->changes)
                            @php
                                $payload  = json_encode(json_decode($row->changes, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                                $subtitle = ($row->action ?? '').' · '.($row->subject_type ?? '');
                            @endphp
                            <button type="button"
                                    class="lb-btn"
                                    data-lb-modal-title="Audit Changes"
                                    data-lb-modal-payload="{{ $b64($payload) }}"
                                    data-lb-modal-subtitle="{{ $subtitle }}"
                                    title="View changes">View</button>
                        @else
                            <span class="lb-table__muted">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="lb-table__muted" style="text-align: center; padding: 1.25rem;">No audit logs found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if(method_exists($logs, 'links'))
    <div class="lb-pagination">{{ $logs->withQueryString()->links() }}</div>
@endif
@endsection
