@extends('statamic::layout')

@section('title', 'Logbook')

@section('content')
<div class="w-full max-w-none px-0">
    {{-- Header --}}
    <div class="flex items-start justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold leading-tight">Logbook</h1>
            <p class="text-sm text-gray-600 mt-1">
                System logs + user audit logs in one place.
            </p>
        </div>

        <div class="flex items-center gap-2">
            @isset($exportUrl)
            <a href="{{ $exportUrl }}" class="btn btn-primary flex items-center gap-2">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none">
                    <path d="M12 3v12" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                    <path d="M7 10l5 5 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    <path d="M5 21h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                </svg>
                <span>Export CSV</span>
            </a>
            @endisset
        </div>
    </div>

    {{-- Tabs --}}
    <div class="bg-white border rounded-lg shadow-sm overflow-hidden">
        <div class="border-b bg-gray-50 px-4">
            <nav class="flex gap-2 py-3">
                <a
                    href="{{ cp_route('utilities.logbook.system') }}"
                    class="px-3 py-2 rounded-md text-sm font-medium {{ $activeTab === 'system' ? 'bg-white shadow-sm border text-blue-700' : 'text-gray-700 hover:bg-white hover:shadow-sm' }}">
                    System Logs
                </a>
                <a
                    href="{{ cp_route('utilities.logbook.audit') }}"
                    class="px-3 py-2 rounded-md text-sm font-medium {{ $activeTab === 'audit' ? 'bg-white shadow-sm border text-blue-700' : 'text-gray-700 hover:bg-white hover:shadow-sm' }}">
                    Audit Logs
                </a>
            </nav>
        </div>

        <div class="p-4">
            @yield('logbook-body')
        </div>
    </div>
</div>

{{-- Global Modal --}}
<div id="logbook-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50" data-logbook-close></div>

    <div class="relative mx-auto mt-16 w-[95%] max-w-4xl">
        <div class="bg-white rounded-xl shadow-xl overflow-hidden border">
            <div class="flex items-center justify-between px-4 py-3 border-b bg-gray-50">
                <div class="min-w-0">
                    <div id="logbook-modal-title" class="text-sm font-semibold truncate">Details</div>
                    <div id="logbook-modal-subtitle" class="text-xs text-gray-600 truncate"></div>
                </div>
                <button type="button" class="btn btn-default" data-logbook-close>
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none">
                        <path d="M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                        <path d="M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                    </svg>
                </button>
            </div>

            <div class="p-4">
                <pre id="logbook-modal-body" class="text-xs bg-gray-900 text-gray-100 rounded-lg p-4 overflow-auto max-h-[65vh] whitespace-pre-wrap"></pre>
            </div>
        </div>
    </div>
</div>

{{-- Modal JS (no dependencies) --}}
@push('scripts')
<script>
    (function() {
        const modal = document.getElementById('logbook-modal');
        const titleEl = document.getElementById('logbook-modal-title');
        const subtitleEl = document.getElementById('logbook-modal-subtitle');
        const bodyEl = document.getElementById('logbook-modal-body');

        function openModal({
            title = 'Details',
            subtitle = '',
            payload = {}
        }) {
            titleEl.textContent = title;
            subtitleEl.textContent = subtitle;
            try {
                bodyEl.textContent = JSON.stringify(payload ?? {}, null, 2);
            } catch (e) {
                bodyEl.textContent = String(payload ?? '');
            }
            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function closeModal() {
            modal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        // expose globally (fixes your old console error)
        window.__logbookOpenModal = function(opts) {
            openModal(opts || {});
        };
        window.__logbookCloseModal = closeModal;

        modal.addEventListener('click', (e) => {
            if (e.target && e.target.matches('[data-logbook-close]')) closeModal();
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
        });
    })();
</script>
@endpush
@endsection