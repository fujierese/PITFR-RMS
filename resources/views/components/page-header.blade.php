@props([
    'title',
    'description' => null,
    'eyebrow' => null,
    'accent' => 'emerald',
])

@php
    $accentClasses = [
        'emerald' => 'text-emerald-600',
        'amber' => 'text-amber-700',
        'rose' => 'text-rose-700',
        'sky' => 'text-sky-700',
        'slate' => 'text-slate-600',
    ];
    $accentClass = $accentClasses[$accent] ?? $accentClasses['emerald'];
@endphp

<section class="rounded-[24px] border border-slate-200 bg-white p-4 shadow-[0_20px_60px_rgba(15,23,42,0.08)] sm:p-6 md:rounded-[32px] lg:p-8">
    <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div class="min-w-0 max-w-2xl">
            @if ($eyebrow)
                <p class="text-sm font-semibold uppercase tracking-[0.3em] {{ $accentClass }}">{{ $eyebrow }}</p>
            @endif
            <h1 class="mt-3 break-words text-2xl font-semibold text-slate-950 sm:text-3xl md:text-4xl">{{ $title }}</h1>
            @if ($description)
                <p class="mt-3 text-sm leading-6 text-slate-600">{{ $description }}</p>
            @endif
        </div>
        @isset($actions)
            <div class="flex flex-wrap gap-2">
                {{ $actions }}
            </div>
        @endisset
    </div>
</section>
