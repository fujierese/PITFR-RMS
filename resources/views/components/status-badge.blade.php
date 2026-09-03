@props([
    'status',
    'label' => null,
])

@php
    $statusKey = strtolower(str_replace([' ', '-'], '_', (string) $status));
    $statusClasses = [
        'pending' => 'bg-amber-100 text-amber-800 ring-amber-200',
        'approved' => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
        'rejected' => 'bg-rose-100 text-rose-800 ring-rose-200',
        'cancelled' => 'bg-slate-200 text-slate-700 ring-slate-300',
        'completed' => 'bg-sky-100 text-sky-800 ring-sky-200',
        'needs_reschedule' => 'bg-amber-100 text-amber-800 ring-amber-200',
        'active' => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
        'inactive' => 'bg-slate-200 text-slate-700 ring-slate-300',
        'returned' => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
        'overdue' => 'bg-rose-100 text-rose-800 ring-rose-200',
    ];
    $statusLabel = $label ?? ucfirst(str_replace('_', ' ', $statusKey));
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset ' . ($statusClasses[$statusKey] ?? 'bg-slate-100 text-slate-700 ring-slate-200')]) }}>{{ $statusLabel }}</span>
