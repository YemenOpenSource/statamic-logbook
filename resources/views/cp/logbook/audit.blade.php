@extends('statamic-logbook::cp.logbook._layout', [
'activeTab' => 'audit',
'exportUrl' => cp_route('utilities.logbook.audit.export')
])

@section('logbook-body')
@include('statamic-logbook::cp.logbook._filters', [
'resetUrl' => cp_route('utilities.logbook.audit')
])

<div class="overflow-hidden border rounded-lg">
    <table class="data-table w-full">
        <thead class="bg-white sticky top-0">
            <tr>
                <th class="w-[180px]">Time</th>
                <th class="w-[140px]">Action</th>
                <th>Target</th>
                <th class="w-[160px]">User</th>
                <th class="w-[120px] text-right">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
            @php
            $changes = $log->changes ? json_decode($log->changes, true) : null;
            $meta = $log->meta ? json_decode($log->meta, true) : null;
            $hasChanges = is_array($changes) && !empty($changes);
            $hasMeta = is_array($meta) && !empty($meta);
            @endphp

            <tr class="hover:bg-gray-50">
                <td class="text-xs text-gray-700 whitespace-nowrap">{{ $log->created_at }}</td>

                <td>
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-purple-100 text-purple-700">
                        {{ $log->action }}
                    </span>
                </td>

                <td class="text-sm text-gray-900">
                    <div class="font-medium">{{ $log->subject_type }}</div>
                    <div class="text-xs text-gray-600">{{ $log->subject_id }}</div>
                </td>

                <td class="text-xs text-gray-700">
                    {{ $log->user_id ?: '—' }}
                </td>

                <td class="text-right">
                    <div class="inline-flex items-center gap-1">
                        @if($hasChanges)
                        <button
                            type="button"
                            class="btn btn-default"
                            title="View changes"
                            onclick="window.__logbookOpenModal({
                                  title: 'Audit Changes',
                                  subtitle: '{{ addslashes($log->action) }} • {{ addslashes($log->subject_type) }}',
                                  payload: {{ json_encode($changes ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}
                                })">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none">
                                <path d="M12 20h9" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                        @endif

                        @if($hasMeta)
                        <button
                            type="button"
                            class="btn btn-default"
                            title="View meta"
                            onclick="window.__logbookOpenModal({
                                  title: 'Audit Meta',
                                  subtitle: '{{ addslashes($log->action) }}',
                                  payload: {{ json_encode($meta ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}
                                })">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none">
                                <path d="M12 22a10 10 0 1 0-10-10 10 10 0 0 0 10 10z" stroke="currentColor" stroke-width="2" />
                                <path d="M12 16v-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                <path d="M12 8h.01" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                            </svg>
                        </button>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="p-8 text-center text-sm text-gray-600">
                    No audit logs found for this filter.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if(method_exists($logs, 'links'))
<div class="mt-4">
    {{ $logs->withQueryString()->links() }}
</div>
@endif
@endsection