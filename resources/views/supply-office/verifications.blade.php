@extends('layouts.app')

@section('title', 'Account Verification - Supply Office')

@section('content')
@php
    use App\Support\AccountVerificationDocuments as Docs;
    $categoryLabel = fn (?string $type) => $type === 'outsider' || $type === null ? 'Guest' : ucfirst($type);
@endphp
<div class="container mx-auto px-4 py-8">
    <div class="mx-auto max-w-6xl space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Identity and Affiliation Verification</h1>
            <p class="mt-1 text-sm text-gray-600">Review the ID and the Certificate of Registration or proof of current PIT affiliation together, then make one decision. The files are deleted after your decision; the result is kept.</p>
        </div>

        @foreach (['success' => 'border-emerald-200 bg-emerald-50 text-emerald-800', 'info' => 'border-blue-200 bg-blue-50 text-blue-800'] as $key => $classes)
            @if (session($key))
                <div class="rounded-lg border p-3 text-sm {{ $classes }}">{{ session($key) }}</div>
            @endif
        @endforeach
        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert">{{ $errors->first() }}</div>
        @endif

        <section class="rounded-lg bg-white p-6 shadow-md">
            <h2 class="text-lg font-semibold text-gray-800">Waiting for review ({{ $pending->count() }})</h2>

            @forelse ($pending as $account)
                @php
                    $claimed = $account->claimed_requestor_type ?? 'outsider';
                    $isMember = Docs::isMember($claimed);
                    $documents = [
                        ['label' => 'Identity', 'type' => $account->verificationIdTypeLabel() ?? 'ID', 'file' => $account->verification_id_file, 'url' => route('supply-office.verifications.file', $account)],
                    ];
                    if ($isMember || (Docs::enabled() && $account->verification_affiliation_file)) {
                        $documents[] = [
                            'label' => 'Affiliation',
                            'type' => $account->verificationAffiliationTypeLabel() ?? ($claimed === 'student' ? 'Certificate of Registration' : 'Proof of current affiliation'),
                            'file' => Docs::enabled() ? $account->verification_affiliation_file : null,
                            'url' => Docs::enabled() ? route('supply-office.verifications.affiliation-file', $account) : null,
                        ];
                    }
                @endphp
                <article class="mt-4 rounded-xl border border-gray-200 p-4" data-verification-review="{{ $account->id }}">
                    <dl class="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-[130px_1fr_130px_1fr]">
                        <dt class="text-gray-500">Name</dt><dd class="font-semibold text-gray-900">{{ $account->name }}</dd>
                        <dt class="text-gray-500">Email</dt><dd class="break-all">{{ $account->username }}</dd>
                        <dt class="text-gray-500">Account type</dt><dd class="font-medium">{{ $categoryLabel($claimed) }}</dd>
                        <dt class="text-gray-500">Submitted</dt><dd>{{ $account->verification_submitted_at?->format('M j, Y g:i A') ?? '—' }}</dd>
                        @if ($account->verification_document_period)
                            <dt class="text-gray-500">{{ $claimed === 'student' ? 'COR period' : 'Document date' }}</dt><dd>{{ $account->verification_document_period }}</dd>
                        @endif
                        @if ($account->department || $account->office_or_organization)
                            <dt class="text-gray-500">{{ $isMember ? 'Department' : 'Organization' }}</dt><dd>{{ $isMember ? $account->department : $account->office_or_organization }}</dd>
                        @endif
                    </dl>

                    <div class="mt-4 grid gap-3 {{ count($documents) > 1 ? 'md:grid-cols-2' : '' }}">
                        @foreach ($documents as $document)
                            <figure class="rounded-lg border border-gray-200 bg-gray-50 p-2">
                                <figcaption class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $document['label'] }} · <span class="normal-case tracking-normal text-gray-700">{{ $document['type'] }}</span></figcaption>
                                @if ($document['file'] && str_ends_with(strtolower($document['file']), '.pdf'))
                                    <a href="{{ $document['url'] }}" target="_blank" rel="noopener" class="block p-6 text-center text-sm font-semibold text-blue-700 hover:underline">Open the uploaded document (PDF)</a>
                                @elseif ($document['file'])
                                    <a href="{{ $document['url'] }}" target="_blank" rel="noopener">
                                        <img src="{{ $document['url'] }}" alt="{{ $document['label'] }} document of {{ $account->name }}" class="max-h-64 w-full rounded object-contain" loading="lazy">
                                    </a>
                                @else
                                    <p class="p-6 text-center text-sm text-amber-800">Not submitted. Request this document before approving.</p>
                                @endif
                            </figure>
                        @endforeach
                    </div>

                    <div class="mt-4 grid gap-3 lg:grid-cols-3">
                        <form method="POST" action="{{ route('supply-office.verifications.decide', $account) }}" class="space-y-2 rounded-lg border border-emerald-200 bg-emerald-50/60 p-3">
                            @csrf
                            <input type="hidden" name="decision" value="approve">
                            <label class="block text-xs font-semibold text-gray-600" for="type_{{ $account->id }}">Approve as</label>
                            <select id="type_{{ $account->id }}" name="requestor_type" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                @foreach (\App\Models\User::CLAIMABLE_REQUESTOR_TYPES as $type)
                                    <option value="{{ $type }}" @selected($claimed === $type)>{{ $categoryLabel($type) }}</option>
                                @endforeach
                            </select>
                            <label class="flex items-start gap-2 text-xs text-gray-700">
                                <input type="checkbox" name="documents_checked" value="1" required class="mt-0.5 rounded border-gray-300 text-emerald-700">
                                <span>I checked the identity{{ $isMember ? ' and the affiliation' : '' }} document{{ $isMember ? 's' : '' }}: the name matches{{ $isMember ? ' and the document shows current PIT ' . ($claimed === 'student' ? 'enrolment' : 'service or employment') : '' }}.</span>
                            </label>
                            <button type="submit" class="w-full rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Approve Account</button>
                        </form>

                        <form method="POST" action="{{ route('supply-office.verifications.decide', $account) }}" class="space-y-2 rounded-lg border border-amber-200 bg-amber-50/60 p-3">
                            @csrf
                            <input type="hidden" name="decision" value="request_documents">
                            <label class="block text-xs font-semibold text-gray-600" for="more_{{ $account->id }}">What to send or replace</label>
                            <textarea id="more_{{ $account->id }}" name="reason" required maxlength="500" rows="2" placeholder="e.g. Upload your COR for the current semester" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></textarea>
                            <button type="submit" class="w-full rounded-lg border border-amber-400 bg-white px-4 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-50">Request Additional Documents</button>
                        </form>

                        <form method="POST" action="{{ route('supply-office.verifications.decide', $account) }}" class="space-y-2 rounded-lg border border-red-200 bg-red-50/50 p-3">
                            @csrf
                            <input type="hidden" name="decision" value="reject">
                            <label class="block text-xs font-semibold text-gray-600" for="reason_{{ $account->id }}">Reason for rejecting</label>
                            <textarea id="reason_{{ $account->id }}" name="reason" required maxlength="500" rows="2" placeholder="e.g. The name does not match the account" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></textarea>
                            <button type="submit" class="w-full rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Reject Application</button>
                        </form>
                    </div>
                </article>
            @empty
                <p class="mt-3 text-sm text-gray-500">No applications are waiting for review.</p>
            @endforelse
        </section>

        @if ($awaitingRequestor->isNotEmpty())
            <section class="rounded-lg bg-white p-6 shadow-md">
                <h2 class="text-lg font-semibold text-gray-800">Waiting for additional documents ({{ $awaitingRequestor->count() }})</h2>
                <p class="mt-1 text-sm text-gray-600">These accounts stay pending until the requestor uploads the documents again.</p>
                <ul class="mt-3 divide-y divide-gray-100 text-sm">
                    @foreach ($awaitingRequestor as $account)
                        <li class="py-2"><span class="font-semibold">{{ $account->name }}</span> · {{ $categoryLabel($account->claimed_requestor_type) }} · requested {{ $account->verified_at?->format('M j, Y g:i A') }}: <span class="text-gray-700">{{ $account->verification_note }}</span></li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($history->isNotEmpty())
            <section class="rounded-lg bg-white p-6 shadow-md">
                <h2 class="text-lg font-semibold text-gray-800">Decision history</h2>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs uppercase text-gray-500">
                            <tr><th class="py-2 pr-4">Account</th><th class="py-2 pr-4">Decision</th><th class="py-2 pr-4">Details</th><th class="py-2 pr-4">Reviewed by</th><th class="py-2">When</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($history as $entry)
                                <tr class="align-top">
                                    <td class="py-2 pr-4">{{ $entry->targetUser?->name ?? 'Deleted account' }}</td>
                                    <td class="py-2 pr-4 font-medium">{{ \App\Http\Controllers\AccountVerificationController::DECISION_ACTIONS[$entry->action] ?? $entry->action }}</td>
                                    <td class="py-2 pr-4 text-gray-600">{{ $entry->details }}</td>
                                    <td class="py-2 pr-4">{{ $entry->actor?->name ?? '—' }}</td>
                                    <td class="py-2 whitespace-nowrap">{{ $entry->created_at?->format('M j, Y g:i A') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
</div>
@endsection
