@extends('statamic::layout')

@section('title', 'Logbook')

@section('content')
<style>
    .content .container {
        max-width: 100% !important;
    }
</style>

<div class="mb-4">
    <h1 class="mb-1">Logbook</h1>
    <div class="text-xs text-gray-600">
        System logs & user audit logs
    </div>
</div>

<div class="card p-0 overflow-hidden w-full">
    <div class="flex items-center gap-6 border-b px-4">
        <a href="{{ cp_route('utilities.logbook.system') }}"
            class="py-3 text-sm font-medium {{ $active === 'system' ? 'text-blue border-b-2 border-blue' : 'text-gray-700' }}">
            System Logs
        </a>

        <a href="{{ cp_route('utilities.logbook.audit') }}"
            class="py-3 text-sm font-medium {{ $active === 'audit' ? 'text-blue border-b-2 border-blue' : 'text-gray-700' }}">
            Audit Logs
        </a>
    </div>

    <div class="p-4">
        @yield('panel')
    </div>
</div>

{{-- Modal --}}
<div id="logbook-modal" class="hidden fixed inset-0 z-50">
    <div class="absolute inset-0 bg-black/50"></div>

    <div class="relative flex items-center justify-center h-full p-6">
        <div class="card w-full max-w-5xl p-0">
            <div class="flex justify-between items-center border-b px-4 py-3">
                <div id="logbook-modal-title" class="font-semibold text-sm">Details</div>
                <button class="btn" onclick="__logbookClose()">Close</button>
            </div>

            <pre id="logbook-modal-body"
                class="p-4 text-xs whitespace-pre-wrap break-words max-h-[70vh] overflow-auto"></pre>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function __logbookOpenModal(title, payload) {
        document.getElementById('logbook-modal-title').textContent = title;
        document.getElementById('logbook-modal-body').textContent = payload ? atob(payload) : '—';
        document.getElementById('logbook-modal').classList.remove('hidden');
    }

    function __logbookClose() {
        document.getElementById('logbook-modal').classList.add('hidden');
    }
</script>
@endsection