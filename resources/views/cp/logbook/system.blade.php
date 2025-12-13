@extends('statamic-logbook::cp.logbook._layout', ['active' => 'system'])

@section('panel')
@php
// small helper to base64 encode safely
$b64 = fn($s) => base64_encode((string) $s);
@endphp

<form method="GET" class="mb-4">
    <div class="card p-3">
        <div class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
            <div>
                <label class="block text-xs mb-1">From</label>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="input-text">
            </div>

            <div>
                <label class="block text-xs mb-1">To</label>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="input-text">
            </div>

            <div>
                <label class="block text-xs mb-1">Level</label>
                <select name="level" class="input-text">
                    <option value="">All</option>
                    @foreach(($levels ?? []) as $lvl)
                    <option value="{{ $lvl }}" @selected(($filters['level'] ?? '' )===$lvl)>{{ $lvl }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs mb-1">Channel</label>
                <select name="channel" class="input-text">
                    <option value="">All</option>
                    @foreach(($channels ?? []) as $ch)
                    <option value="{{ $ch }}" @selected(($filters['channel'] ?? '' )===$ch)>{{ $ch }}</option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-2">
                <label class="block text-xs mb-1">Search</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="input-text" placeholder="message contains...">
            </div>
        </div>

        <div class="flex gap-2 mt-3">
            <button class="btn-primary" type="submit">Apply</button>
            <a class="btn" href="{{ cp_route('utilities.logbook.system') }}">Reset</a>
        </div>
    </div>
</form>

<div class="card p-0 overflow-x-auto">
    <table class="data-table">
        <thead>
            <tr>
                <th class="w-40">Time</th>
                <th class="w-24">Level</th>
                <th>Message</th>
                <th class="w-40">User</th>
                <th class="w-64">Request</th>
                <th class="w-28">Details</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $row)
            @php
            $ctx = $row->context ? $b64($row->context) : null;
            $req = json_encode([
            'request_id' => $row->request_id,
            'method' => $row->method,
            'url' => $row->url,
            'ip' => $row->ip,
            'user_agent' => $row->user_agent,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

            $reqB64 = $b64($req);
            @endphp

            <tr>
                <td class="text-xs">{{ $row->created_at }}</td>

                <td class="text-xs font-mono">
                    <span class="badge-pill">{{ $row->level }}</span>
                </td>

                <td class="text-sm">
                    <div class="font-medium">{{ $row->message }}</div>
                    <div class="text-xs text-gray-600 font-mono mt-1">
                        {{ $row->channel ?: '—' }}
                    </div>
                </td>

                <td class="text-xs font-mono">{{ $row->user_id ?: '—' }}</td>

                <td class="text-xs font-mono">
                    <div>{{ $row->request_id ?: '—' }}</div>
                    @if($row->method || $row->url)
                    <div class="text-gray-600">{{ $row->method }} {{ $row->url }}</div>
                    @endif
                </td>

                <td class="text-xs">
                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="btn"
                            onclick="window.__logbookOpenModal('Request Details', '{{ $reqB64 }}')">
                            Request
                        </button>

                        @if($ctx)
                        <button
                            type="button"
                            class="btn"
                            onclick="window.__logbookOpenModal('Context', '{{ $ctx }}')">
                            Context
                        </button>
                        @else
                        <span class="text-gray-600">—</span>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="p-4 text-sm text-gray-600">No logs found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $logs->links() }}
</div>
@endsection