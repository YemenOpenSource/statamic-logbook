@extends('statamic::layout')

@section('title', __('Logbook'))

@section('content')
<div class="flex items-center justify-between mb-4">
    <div>
        <h1 class="mb-1">{{ __('Logbook') }}</h1>
        <div class="text-xs text-gray-600">
            {{ __('System logs + user audit logs in one place.') }}
        </div>
    </div>
</div>

{{-- full width container --}}
<div class="w-full">
    <div class="card p-0 overflow-hidden">
        <div class="flex items-center gap-4 border-b px-4">
            <a
                class="py-3 text-sm font-medium @if($active === 'system') text-blue border-b-2 border-blue @else text-gray-700 @endif"
                href="{{ cp_route('utilities.logbook.system') }}">
                {{ __('System Logs') }}
            </a>

            <a
                class="py-3 text-sm font-medium @if($active === 'audit') text-blue border-b-2 border-blue @else text-gray-700 @endif"
                href="{{ cp_route('utilities.logbook.audit') }}">
                {{ __('Audit Logs') }}
            </a>
        </div>

        <div class="p-4">
            @yield('panel')
        </div>
    </div>
</div>

{{-- Global Modal --}}
<div id="logbook-modal" class="hidden fixed inset-0 z-50">
    <div id="logbook-modal-backdrop" class="absolute inset-0 bg-black opacity-50"></div>

    <div class="relative h-full w-full flex items-center justify-center p-6">
        <div class="card w-full max-w-5xl p-0 overflow-hidden">
            <div class="flex items-center justify-between border-b px-4 py-3">
                <div class="font-semibold text-sm" id="logbook-modal-title">Details</div>

                <button type="button" class="btn" id="logbook-modal-close">
                    Close
                </button>
            </div>

            <div class="p-4">
                <pre id="logbook-modal-body" class="text-xs whitespace-pre-wrap break-words max-h-[70vh] overflow-auto"></pre>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    (function() {
        const modal = document.getElementById('logbook-modal');
        const backdrop = document.getElementById('logbook-modal-backdrop');
        const titleEl = document.getElementById('logbook-modal-title');
        const bodyEl = document.getElementById('logbook-modal-body');
        const closeBtn = document.getElementById('logbook-modal-close');

        function openModal(title, base64Payload) {
            titleEl.textContent = title || 'Details';

            let text = '';
            try {
                text = base64Payload ? atob(base64Payload) : '';
            } catch (e) {
                text = '[Failed to decode payload]';
            }

            bodyEl.textContent = text || '—';
            modal.classList.remove('hidden');
        }

        function closeModal() {
            modal.classList.add('hidden');
            bodyEl.textContent = '';
        }

        // expose globally (buttons use it)
        window.__logbookOpenModal = openModal;

        closeBtn?.addEventListener('click', closeModal);
        backdrop?.addEventListener('click', closeModal);

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
        });
    })();
</script>
@endsection