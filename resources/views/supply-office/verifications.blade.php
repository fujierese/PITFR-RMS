@extends('layouts.app')

@section('title', 'Account Verification - Supply Office')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mx-auto max-w-6xl space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Account Verification</h1>
            <p class="mt-1 text-sm text-gray-600">
                Check each uploaded ID against the account name and category. The ID image is deleted as soon as you approve or reject it; only the ID type and your decision are kept.
            </p>
        </div>

        @foreach (['success' => 'border-emerald-200 bg-emerald-50 text-emerald-800', 'info' => 'border-blue-200 bg-blue-50 text-blue-800'] as $key => $classes)
            @if (session($key))
                <div class="rounded-lg border p-3 text-sm {{ $classes }}">{{ session($key) }}</div>
            @endif
        @endforeach
        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        <section class="rounded-lg bg-white p-6 shadow-md">
            <h2 class="text-lg font-semibold text-gray-800">Waiting for review ({{ $pending->count() }})</h2>

            @forelse ($pending as $account)
                <div class="mt-4 grid gap-4 rounded-xl border border-gray-200 p-4 lg:grid-cols-[1fr_320px]">
                    <div class="space-y-3 text-sm">
                        <dl class="grid grid-cols-[130px_1fr] gap-x-3 gap-y-1">
                            <dt class="text-gray-500">Name</dt><dd class="font-semibold text-gray-900">{{ $account->name }}</dd>
                            <dt class="text-gray-500">Email</dt><dd class="break-all">{{ $account->username }}</dd>
                            <dt class="text-gray-500">Claims to be</dt><dd class="font-medium">{{ ucfirst($account->claimed_requestor_type ?? 'outsider') }}</dd>
                            <dt class="text-gray-500">ID type</dt><dd>{{ $account->verificationIdTypeLabel() ?? '—' }}</dd>
                            <dt class="text-gray-500">Submitted</dt><dd>{{ $account->verification_submitted_at?->format('M j, Y g:i A') ?? '—' }}</dd>
                        </dl>

                        <form method="POST" action="{{ route('supply-office.verifications.decide', $account) }}" class="flex flex-wrap items-end gap-2">
                            @csrf
                            <input type="hidden" name="decision" value="approve">
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-500" for="type_{{ $account->id }}">Verify as</label>
                                <select id="type_{{ $account->id }}" name="requestor_type" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                    @foreach (\App\Models\User::CLAIMABLE_REQUESTOR_TYPES as $type)
                                        <option value="{{ $type }}" @selected(($account->claimed_requestor_type ?? 'outsider') === $type)>{{ ucfirst($type) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Approve</button>
                        </form>

                        <form method="POST" action="{{ route('supply-office.verifications.decide', $account) }}" class="flex flex-wrap items-end gap-2">
                            @csrf
                            <input type="hidden" name="decision" value="reject">
                            <div class="min-w-[220px] flex-1">
                                <label class="mb-1 block text-xs font-semibold text-gray-500" for="reason_{{ $account->id }}">Reason for rejecting</label>
                                <input id="reason_{{ $account->id }}" name="reason" required maxlength="500" placeholder="e.g. Photo is blurry; name does not match" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            </div>
                            <button type="submit" class="rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Reject</button>
                        </form>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-2">
                        @if ($account->verification_id_file && str_ends_with(strtolower($account->verification_id_file), '.pdf'))
                            <a href="{{ route('supply-office.verifications.file', $account) }}" target="_blank" rel="noopener" class="block p-6 text-center text-sm font-semibold text-blue-700 hover:underline">Open the uploaded ID (PDF)</a>
                        @elseif ($account->verification_id_file)
                            <a href="{{ route('supply-office.verifications.file', $account) }}" target="_blank" rel="noopener">
                                <img src="{{ route('supply-office.verifications.file', $account) }}" alt="Uploaded ID of {{ $account->name }}" class="max-h-56 w-full rounded object-contain" loading="lazy">
                            </a>
                        @else
                            <p class="p-6 text-center text-sm text-gray-500">No file found.</p>
                        @endif
                    </div>
                </div>
            @empty
                <p class="mt-3 text-sm text-gray-500">No IDs are waiting for review.</p>
            @endforelse
        </section>

        @if ($recent->isNotEmpty())
            <section class="rounded-lg bg-white p-6 shadow-md">
                <h2 class="text-lg font-semibold text-gray-800">Recent decisions</h2>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs uppercase text-gray-500">
                            <tr><th class="py-2 pr-4">Name</th><th class="py-2 pr-4">Result</th><th class="py-2 pr-4">Category</th><th class="py-2 pr-4">ID type</th><th class="py-2">Decided</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($recent as $account)
                                <tr>
                                    <td class="py-2 pr-4">{{ $account->name }}</td>
                                    <td class="py-2 pr-4">{{ $account->verification_status === 'verified' ? 'Verified' : 'Rejected: ' . $account->verification_note }}</td>
                                    <td class="py-2 pr-4">{{ ucfirst($account->requestor_type) }}</td>
                                    <td class="py-2 pr-4">{{ $account->verificationIdTypeLabel() ?? '—' }}</td>
                                    <td class="py-2">{{ $account->verified_at?->format('M j, Y g:i A') }}</td>
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
