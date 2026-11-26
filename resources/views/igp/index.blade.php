@extends('layouts.app')
@section('title', 'IGP Dashboard')

@section('content')
@php
    use App\Support\IgpInquiries;
    $sections = IgpInquiries::SECTIONS;
    $visible = $section !== '' ? [$section => $sections[$section]] : $sections;
    $actionLabel = ['new' => 'Review and forward', 'approved' => 'Inform outsider', 'ongoing' => 'Coordinate'];
@endphp
<div class="mx-auto max-w-7xl space-y-6">
    <x-page-header eyebrow="IGP" title="Reservation Management" description="Review outsider inquiries, forward them to the venue custodian, and coordinate institutional requirements after the Supply Office approves. The Supply Office makes every reservation decision; payment is arranged outside PITFR." />

    <x-search-filter-bar :action="route('igp.index')" :search="$search ?? ''" :hidden="$section !== '' ? ['section' => $section] : []" placeholder="Search control number, activity, client, organization, venue or date" />
    @if(($search ?? '') !== '')
        <p class="text-sm text-slate-600" aria-live="polite">Showing inquiries matching “{{ $search }}”. <a href="{{ route('igp.index', $section !== '' ? ['section' => $section] : []) }}" class="font-semibold text-emerald-700 hover:underline">Show all</a></p>
    @endif

    {{-- Part 4: the four sections, matching the sidebar. --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" data-igp-cards>
        @foreach($sections as $key => $info)
            <a href="{{ route('igp.index', ['section' => $key]) }}" @if($section === $key) aria-current="page" @endif
               class="rounded-2xl border p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md {{ $section === $key ? 'border-sky-400 bg-sky-50' : 'border-slate-200 bg-white' }}" data-igp-card="{{ $key }}">
                <p class="text-sm font-semibold text-slate-700">{{ $info['label'] }}</p>
                <p class="mt-1 text-3xl font-bold text-slate-900">{{ $counts[$key] ?? 0 }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $key === 'closed' ? 'in the last 60 days' : 'need IGP action' }}</p>
            </a>
        @endforeach
    </div>
    @if($section !== '')
        <a href="{{ route('igp.index') }}" class="inline-block text-sm font-semibold text-sky-700 hover:underline">Show all sections</a>
    @endif

    @foreach($visible as $key => $info)
        @php $items = $groups[$key]; @endphp
        <section id="igp-{{ $key }}" aria-labelledby="igp-{{ $key }}-heading" class="scroll-mt-24 rounded-[28px] border border-slate-200 bg-white p-5 shadow-sm sm:p-6" data-igp-section="{{ $key }}">
            <div class="border-b border-slate-200 pb-3">
                <h2 id="igp-{{ $key }}-heading" class="text-lg font-semibold text-slate-900">{{ $info['label'] }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $info['hint'] }}</p>
            </div>

            @forelse($items as $inquiry)
                @include('igp.partials.inquiry-row', ['inquiry' => $inquiry, 'button' => IgpInquiries::needsIgp($inquiry) ? ($actionLabel[$key] ?? 'View details') : 'View details'])
            @empty
                <p class="py-4 text-sm text-slate-500">{{ ($search ?? '') !== '' ? 'No inquiries here match your search.' : 'Nothing here right now.' }}</p>
            @endforelse

            @if($key === 'new' && $groups['in_review']->isNotEmpty())
                {{-- Forwarded inquiries: visible for progress only, no IGP action and not counted. --}}
                <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4" data-igp-in-review>
                    <h3 class="text-sm font-semibold text-slate-800">Forwarded for review ({{ $groups['in_review']->count() }})</h3>
                    <p class="mt-0.5 text-xs text-slate-500">With the venue custodian or the Supply Office. No action is needed from IGP; you are notified when the Supply Office decides.</p>
                    @foreach($groups['in_review'] as $inquiry)
                        @include('igp.partials.inquiry-row', ['inquiry' => $inquiry, 'button' => 'View progress'])
                    @endforeach
                </div>
            @endif
        </section>
    @endforeach
</div>
@endsection
