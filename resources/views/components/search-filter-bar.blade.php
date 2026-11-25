{{--
    Follow-up fixes: one search and filter bar for every list page, based on the
    Requestor "My Requests" bar. Filters expand inline in the same card and apply as
    soon as they change (no Search / Apply buttons); "Clear" resets everything.

    Props:
      action       form URL (GET)
      search       current search text
      placeholder  search placeholder for the page
      filters      list of ['type' => 'select'|'date'|'text', 'name', 'label', 'value', 'options' => [value => label], 'placeholder', 'default']
      hidden       extra query values kept on every search, e.g. ['filter' => 'pending']
      clearUrl     where "Clear" goes (default: action with only the hidden values)
--}}
@props([
    'action',
    'search' => '',
    'placeholder' => 'Search',
    'filters' => [],
    'hidden' => [],
    'clearUrl' => null,
    'searchLabel' => 'Search',
])
@php
    $barId = 'sfb-' . substr(md5($action . json_encode(array_column($filters, 'name'))), 0, 8);
    $expanded = collect($filters)->contains(fn ($f) => (string) ($f['value'] ?? '') !== '' && (string) ($f['value'] ?? '') !== (string) ($f['default'] ?? ''));
    $clearUrl ??= $action . ($hidden !== [] ? (str_contains($action, '?') ? '&' : '?') . http_build_query($hidden) : '');
    $fieldClass = 'mt-2 w-full min-w-0 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100';
    $selectClass = $fieldClass . " appearance-none bg-[url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 20 20%22 fill=%22none%22 stroke=%22%2364758b%22 stroke-width=%221.5%22%3E%3Cpath d=%22M5 7.5l5 5 5-5%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22/%3E%3C/svg%3E')] bg-[length:16px_16px] bg-[position:right_0.9rem_center] bg-no-repeat pr-10";
    $columns = match (true) {
        count($filters) >= 5 => 'lg:grid-cols-5',
        count($filters) === 4 => 'lg:grid-cols-4',
        count($filters) === 3 => 'lg:grid-cols-3',
        default => 'lg:grid-cols-2',
    };
@endphp
<form method="GET" action="{{ $action }}" data-search-filter-bar id="{{ $barId }}" {{ $attributes->merge(['class' => 'rounded-[24px] border border-slate-200 bg-slate-50 p-4 shadow-sm sm:p-5 md:rounded-[28px]']) }}>
    @foreach($hidden as $hiddenName => $hiddenValue)
        <input type="hidden" name="{{ $hiddenName }}" value="{{ $hiddenValue }}">
    @endforeach
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="min-w-0 flex-1">
            <label for="{{ $barId }}-search" class="sr-only">{{ $searchLabel }}</label>
            <input type="search" id="{{ $barId }}-search" name="search" value="{{ $search }}" maxlength="200" placeholder="{{ $placeholder }}" class="w-full rounded-3xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
        </div>
        <div class="flex flex-wrap items-center gap-3">
            @if($filters !== [])
                <button type="button" data-sfb-toggle aria-expanded="{{ $expanded ? 'true' : 'false' }}" aria-controls="{{ $barId }}-panel" class="inline-flex items-center gap-2 rounded-full border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-100">
                    Advanced Filters
                    <svg data-sfb-icon class="h-4 w-4 transition-transform duration-200 {{ $expanded ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
            @endif
            <a href="{{ $clearUrl }}" data-sfb-clear class="inline-flex items-center justify-center rounded-full border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-100">Clear</a>
        </div>
    </div>

    @if($filters !== [])
        <div id="{{ $barId }}-panel" data-sfb-panel class="mt-4 grid gap-4 sm:grid-cols-2 {{ $columns }} {{ $expanded ? '' : 'hidden' }}">
            @foreach($filters as $filter)
                @php $fieldId = $barId . '-' . $filter['name']; @endphp
                <div class="min-w-0">
                    <label for="{{ $fieldId }}" class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">{{ $filter['label'] }}</label>
                    @if(($filter['type'] ?? 'select') === 'select')
                        <select id="{{ $fieldId }}" name="{{ $filter['name'] }}" data-sfb-auto class="{{ $selectClass }}">
                            @foreach($filter['options'] ?? [] as $optionValue => $optionLabel)
                                @if(is_array($optionLabel))
                                    {{-- Grouped options: 'Group label' => [value => label] --}}
                                    <optgroup label="{{ $optionValue }}">
                                        @foreach($optionLabel as $groupValue => $groupLabel)
                                            <option value="{{ $groupValue }}" @selected((string) ($filter['value'] ?? '') === (string) $groupValue)>{{ $groupLabel }}</option>
                                        @endforeach
                                    </optgroup>
                                @else
                                    <option value="{{ $optionValue }}" @selected((string) ($filter['value'] ?? '') === (string) $optionValue)>{{ $optionLabel }}</option>
                                @endif
                            @endforeach
                        </select>
                    @elseif($filter['type'] === 'date')
                        <input id="{{ $fieldId }}" type="date" name="{{ $filter['name'] }}" value="{{ $filter['value'] ?? '' }}" data-sfb-auto class="{{ $fieldClass }}">
                    @else
                        <input id="{{ $fieldId }}" type="text" name="{{ $filter['name'] }}" value="{{ $filter['value'] ?? '' }}" placeholder="{{ $filter['placeholder'] ?? '' }}" maxlength="200" data-sfb-text class="{{ $fieldClass }}">
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</form>

@once
    <script>
        // Shared behaviour: filters apply as they change; text filters after a short pause.
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-search-filter-bar]').forEach((form) => {
                const submit = () => {
                    form.querySelector('input[name="page"]')?.remove();
                    form.requestSubmit ? form.requestSubmit() : form.submit();
                };
                form.querySelector('[data-sfb-toggle]')?.addEventListener('click', (event) => {
                    const button = event.currentTarget;
                    const panel = form.querySelector('[data-sfb-panel]');
                    const open = panel.classList.toggle('hidden') === false;
                    button.setAttribute('aria-expanded', open ? 'true' : 'false');
                    form.querySelector('[data-sfb-icon]')?.classList.toggle('rotate-180', open);
                });
                form.querySelectorAll('[data-sfb-auto]').forEach((field) => field.addEventListener('change', submit));
                form.querySelectorAll('[data-sfb-text]').forEach((field) => {
                    let timer = null;
                    field.addEventListener('input', () => {
                        clearTimeout(timer);
                        timer = setTimeout(submit, 600);
                    });
                });
            });
        });
    </script>
@endonce
