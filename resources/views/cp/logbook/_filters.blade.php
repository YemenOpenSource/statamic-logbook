<form method="get" class="mb-4">
    <div class="bg-gray-50 border rounded-lg p-3 flex flex-wrap items-end gap-3">
        <div class="min-w-[140px]">
            <label class="text-xs text-gray-600 block mb-1">From</label>
            <input type="date" name="from" value="{{ request('from') }}" class="input-text w-full">
        </div>

        <div class="min-w-[140px]">
            <label class="text-xs text-gray-600 block mb-1">To</label>
            <input type="date" name="to" value="{{ request('to') }}" class="input-text w-full">
        </div>

        @isset($levels)
        <div class="min-w-[140px]">
            <label class="text-xs text-gray-600 block mb-1">Level</label>
            <select name="level" class="input-text w-full">
                <option value="">All</option>
                @foreach($levels as $lvl)
                <option value="{{ $lvl }}" @selected(request('level')===$lvl)>{{ strtoupper($lvl) }}</option>
                @endforeach
            </select>
        </div>
        @endisset

        @isset($channels)
        <div class="min-w-[160px]">
            <label class="text-xs text-gray-600 block mb-1">Channel</label>
            <select name="channel" class="input-text w-full">
                <option value="">All</option>
                @foreach($channels as $ch)
                <option value="{{ $ch }}" @selected(request('channel')===$ch)>{{ $ch }}</option>
                @endforeach
            </select>
        </div>
        @endisset

        <div class="flex-1 min-w-[220px]">
            <label class="text-xs text-gray-600 block mb-1">Search</label>
            <div class="flex gap-2">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="message contains…" class="input-text w-full">
                <button class="btn btn-primary flex items-center gap-2" type="submit">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none">
                        <path d="M3 5h18l-7 8v6l-4 2v-8L3 5z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                    </svg>
                    Apply
                </button>
                <a class="btn btn-default flex items-center gap-2" href="{{ $resetUrl ?? url()->current() }}">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none">
                        <path d="M3 12a9 9 0 0 1 9-9 9 9 0 0 1 9 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                        <path d="M3 4v8h8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    Reset
                </a>
            </div>
        </div>
    </div>
</form>