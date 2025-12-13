<div class="card p-3">
    <div class="flex items-center justify-between mb-2">
        <div class="font-semibold text-sm">Logbook</div>
        <a href="{{ cp_route('utilities.logbook.system') }}" class="text-xs text-blue">
            View logs →
        </a>
    </div>

    <div class="grid grid-cols-2 gap-3 text-sm">
        <div>
            <div class="text-xs text-gray-600">System (24h)</div>
            <div class="text-xl font-semibold">{{ $systemTotal24h }}</div>
        </div>

        <div>
            <div class="text-xs text-gray-600">Errors (24h)</div>
            <div class="text-xl font-semibold text-red-600">{{ $systemErrors24h }}</div>
        </div>

        <div>
            <div class="text-xs text-gray-600">Audit (24h)</div>
            <div class="text-xl font-semibold">{{ $auditTotal24h }}</div>
        </div>

        <div>
            <div class="text-xs text-gray-600">Top action (7d)</div>
            <div class="text-xs font-mono">
                {{ $topAction7d->action ?? '—' }}
                <span class="text-gray-600">
                    ({{ $topAction7d->c ?? 0 }})
                </span>
            </div>
        </div>
    </div>
</div>