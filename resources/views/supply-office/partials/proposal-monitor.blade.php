{{-- Additional Part 2: Activity Proposal monitoring on the Supply Office dashboard. --}}
        @php
            $proposalRows = app(\App\Http\Controllers\ActivityProposalController::class)->rows();
            $proposalCounts = [
                'for_verification' => $proposalRows->where('document', 'for_verification')->count(),
                'requires_correction' => $proposalRows->where('document', 'requires_correction')->count(),
                'draft_submitted' => $proposalRows->where('document', 'draft_submitted')->count(),
                'overdue' => $proposalRows->where('compliance', 'overdue')->count(),
            ];
            $proposalAttention = $proposalRows->filter(fn ($r) => $r['compliance'] === 'overdue' || $r['document'] === 'for_verification')->take(5);
        @endphp
        <section class="mb-6 rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200/50" aria-labelledby="proposal-monitor-title" data-proposal-monitor>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="proposal-monitor-title" class="text-xl font-semibold text-slate-900">Activity Proposals</h2>
                <a href="{{ route('supply-office.activity-proposals') }}" class="text-sm font-semibold text-emerald-700 hover:underline">View all</a>
            </div>
            <div class="mt-4 grid gap-3 sm:grid-cols-4">
                @foreach(['for_verification' => 'For Verification', 'overdue' => 'Overdue', 'requires_correction' => 'Requires Correction', 'draft_submitted' => 'Draft Submitted'] as $key => $label)
                    <a href="{{ route('supply-office.activity-proposals', ['filter' => $key]) }}" class="rounded-2xl border p-4 {{ $key === 'overdue' && $proposalCounts[$key] > 0 ? 'border-rose-200 bg-rose-50' : 'border-slate-200 bg-slate-50' }} hover:border-emerald-300">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</p>
                        <p class="mt-1 text-2xl font-bold text-slate-900">{{ $proposalCounts[$key] }}</p>
                    </a>
                @endforeach
            </div>
            @if($proposalAttention->isNotEmpty())
                <ul class="mt-4 divide-y divide-slate-100 text-sm">
                    @foreach($proposalAttention as $row)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <span><span class="font-semibold text-slate-900">{{ $row['request']->control_number }}</span> · {{ \Illuminate\Support\Str::limit($row['request']->name_of_activity, 40) }}</span>
                            <a href="{{ route('request.show', $row['request']->id) }}#activity-proposal" class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $row['compliance'] === 'overdue' ? 'bg-rose-100 text-rose-800' : 'bg-sky-100 text-sky-800' }}">{{ $row['compliance'] === 'overdue' ? 'Overdue' : 'Verify' }}</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
