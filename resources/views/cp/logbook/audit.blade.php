@extends('statamic-logbook::cp.logbook._layout', ['active' => 'audit'])

@section('panel')
@php
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

            <div class="md:col-span-2">
                <label class="block text-xs mb-1">Action</label>
                <select name="action" class="input-text">
                    <option value="">All</option>
                    @foreach(($actions ?? []) as $a)
                    <option value="{{ $a }}" @selected(($filters['action'] ?? '' )===$a)>{{ $a }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs mb-1">Subject</label>
                <select name="subject_type" class="input-text">
                    <option value="">All</option>
                    @foreach(($subjects ?? []) as $s)
                    <option value="{{ $s }}" @selected(($filters['subject_type'] ?? '' )===$s)>{{ $s }}</option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-2">
                <label class="block text-xs mb-1">Search</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="input-text" placeholder="title / handle contains...">
            </div>
        </div>

        <div class="flex gap-2 mt-3">
            <button class="btn-primary" type="submit">Apply</button>
            <a class="btn" href="{{ cp_route('utilities.logbook.audit') }}">Reset</a>
        </div>
    </div>
</form>

<div class="card p-0 overflow-x-auto">
    <table class="data-table">
        <thead>
            <tr>
                <th class="w-40">Time</th>
                <th class="w-56">Action</th>
                <th>Subject</th>
                <th class="w-40">User</th>
                <th class="w-28">Changes</th>
            </tr>
        </thead>

        <tbody>
            @forelse($logs as $row)
            @php
            $changesPretty = $row->changes
            ? json_encode(json_decode($row->changes, true) ?: [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)
            : null;

            $metaPretty = $row->meta
            ? json_encode(json_decode($row->meta, true) ?: [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)
            : null;

            $payload = json_encode([
            'changes' => $changesPretty ? json_decode($changesPretty, true) : null,
            'meta' => $metaPretty ? json_decode($metaPretty, true) : null,
            ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);

            $payloadB64 = $b64($payload);
            @endphp

            <tr>
                <td class="text-xs">{{ $row->created_at }}</td>

                <td class="text-xs font-mono">
                    {{ $row->action }}
                </td>

                <td class="text-sm">
                    <div class="font-medium">
                        {{ $row->subject_title ?? $row->subject_handle ?? '—' }}
                    </div>
                    <div class="text-xs text-gray-600 font-mono mt-1">
                        {{ $row->subject_type }} · {{ $row->subject_id ?? '—' }}
                    </div>
                </td>

                <td class="text-xs font-mono">
                    {{ $row->user_email ?? $row->user_id ?? '—' }}
                </td>

                <td class="text-xs">
                    @if($row->changes || $row->meta)
                    <button
                        type="button"
                        class="btn"
                        onclick="window.__logbookOpenModal('Audit Details', '{{ $payloadB64 }}')">
                        View
                    </button>
                    @else
                    —
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="p-4 text-sm text-gray-600">No audit logs found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $logs->links() }}
</div>
@endsection