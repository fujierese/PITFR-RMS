@php
    $filtersExpanded = $departmentFilter || $venueFilter || $priorityFilter || $dateFrom || $dateTo;
@endphp

<form method="GET" action="{{ $action }}" class="mt-6 rounded-[24px] border border-slate-200 bg-slate-50 p-4 shadow-sm sm:p-5 md:mt-8">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex min-w-0 flex-1 gap-3">
            <label class="min-w-0 flex-1">
                <span class="sr-only">Search requests</span>
                <input type="search" name="search" value="{{ $searchQuery }}" placeholder="Search requests" class="w-full rounded-3xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
            </label>
            <button type="submit" class="inline-flex shrink-0 items-center justify-center rounded-full bg-emerald-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700">Search</button>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <details class="relative" {{ $filtersExpanded ? 'open' : '' }}>
                <summary class="cursor-pointer list-none rounded-full border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-100">Advanced Filters</summary>
                <div class="absolute right-0 z-10 mt-3 w-[min(720px,calc(100vw-2rem))] rounded-[24px] border border-slate-200 bg-white p-4 shadow-xl sm:p-5">
                    <div class="grid gap-4 lg:grid-cols-5">
                        <div>
                            <label for="supply-department" class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Department</label>
                            <input id="supply-department" type="text" name="department" value="{{ $departmentFilter }}" placeholder="Department" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                        </div>
                        <div>
                            <label for="supply-venue" class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Venue</label>
                            <input id="supply-venue" type="text" name="venue" value="{{ $venueFilter }}" placeholder="Venue" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                        </div>
                        <div>
                            <label for="supply-priority" class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Priority</label>
                            <select id="supply-priority" name="priority" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                                <option value="">All priorities</option>
                                <option value="regular" {{ $priorityFilter === 'regular' ? 'selected' : '' }}>Regular</option>
                                <option value="institutional" {{ $priorityFilter === 'institutional' ? 'selected' : '' }}>Institutional</option>
                            </select>
                        </div>
                        <div>
                            <label for="supply-date-from" class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Date from</label>
                            <input id="supply-date-from" type="date" name="date_from" value="{{ $dateFrom }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                        </div>
                        <div>
                            <label for="supply-date-to" class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Date to</label>
                            <input id="supply-date-to" type="date" name="date_to" value="{{ $dateTo }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                        </div>
                    </div>
                    <button type="submit" class="mt-4 inline-flex items-center justify-center rounded-full bg-emerald-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700">Apply Filters</button>
                </div>
            </details>
            <a href="{{ $action }}" class="inline-flex items-center justify-center rounded-full border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-100">Clear</a>
        </div>
    </div>
</form>
