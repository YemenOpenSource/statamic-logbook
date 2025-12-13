@extends('statamic-logbook::cp.logbook._layout', [
'activeTab' => 'system',
'exportUrl' => cp_route('utilities.logbook.system.export')
])

@section('logbook-body')
@include('statamic-logbook::cp.logbook._filters', [
'levels' => $levels ?? [],
'channels' => $channels ?? [],
'resetUrl' => cp_route('utilities.logbook.system')
])

<div class="overflow-hidden border rounded-lg">
    <table class="data-table w-full">
        <thead class="bg-white sticky top-0">
            <tr>
                <th class="w-[180px]">Time</th>
                <th class="w-[120px]">Level</th>
                <th>Message</th>
                <th class="w-[160px]">User</th>
                <th class="w-[320px]">Request</th>
                <th class="w-[120px] text-right">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
            @php
            $ctx = $row->context ? json_decode($row->context, true) : null;
            $hasCtx = is_array($ctx) && !empty($ctx);
            $level = strtolower($row->level);
            @endphp
            <tr class="hover:bg-gray-50">
                <td class="text-xs text-gray-700 whitespace-nowrap">
                    {{ $row->created_at }}
                </td>

                <td>
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold
                        {{ in_array($level, ['error','critical','alert','emergency']) ? 'bg-red-100 text-red-700' : '' }}
                        {{ $level === 'warning' ? 'bg-yellow-100 text-yellow-700' : '' }}
                        {{ in_array($level, ['info','notice']) ? 'bg-blue-100 text-blue-700' : '' }}
                        {{ $level === 'debug' ? 'bg-gray-100 text-gray-700' : '' }}
                    ">
                        {{ strtoupper($level) }}
                    </span>
                </td>

                <td class="text-sm text-gray-900">
                    {{ $row->message }}
                </td>

                <td class="text-xs text-gray-700">
                    {{ $row->user_id ?: '—' }}
                </td>

                <td class="text-xs text-gray-700">
                    <div class="truncate">
                        {{ $row->method ?: '—' }} {{ $row->url ?: '' }}
                    </div>
                </td>

                <td class="text-right">
                    <div class="inline-flex items-center gap-1">
                        @if($hasCtx)
                        <button
                            type="button"
                            class="btn btn-default"
                            title="View context"
                            onclick="window.__logbookOpenModal({
                                  title: 'System Context',
                                  subtitle: '{{ addslashes($row->message) }}',
                                  payload: {{ json_encode($ctx ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}
                                })">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none">
                                <path d="M9 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M15 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="p-8 text-center text-sm text-gray-600">
                    No system logs found for this filter.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if(method_exists($rows, 'links'))
<div class="mt-4">
    {{ $rows->withQueryString()->links() }}
</div>
@endif
@endsection