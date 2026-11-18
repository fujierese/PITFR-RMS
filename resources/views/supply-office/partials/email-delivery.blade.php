{{-- NTF-02: whether notification emails are flowing, without opening server logs. --}}
@php
    $mail = \App\Support\MailQueue::status();
    $ago = static fn ($time) => $time ? $time->setTimezone(config('app.timezone'))->format('M j, Y g:i A') . ' (' . $time->diffForHumans() . ')' : 'Never recorded';
@endphp
<section aria-labelledby="email-delivery-heading" class="mt-6 rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 pb-4">
        <div>
            <h2 id="email-delivery-heading" class="text-lg font-semibold text-slate-900">Email delivery</h2>
            <p class="mt-1 text-sm text-slate-500">Notification emails are sent in the background so pages stay fast. Failed emails are retried twice before they are listed here.</p>
        </div>
        @if($mail['worker_running'])
            <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">Background sending active</span>
        @else
            <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-900">Sending immediately (no background worker)</span>
        @endif
    </div>

    @unless($mail['worker_running'])
        <p class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
            The scheduled task has not run in the last 5 minutes, so emails are sent while each page loads. Ask the server administrator to confirm the cron job
            <code class="rounded bg-white px-1 py-0.5 text-xs">php artisan schedule:run</code> runs every minute.
        </p>
    @endunless

    <dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
            <dt class="text-xs font-medium text-slate-500">Waiting to send</dt>
            <dd class="mt-1 text-xl font-semibold text-slate-900">{{ $mail['queued'] }}</dd>
            @if($mail['oldest_queued'])
                <dd class="text-xs text-slate-500">Oldest queued {{ $mail['oldest_queued']->diffForHumans() }}</dd>
            @endif
        </div>
        <div class="rounded-xl border px-4 py-3 {{ $mail['failed'] > 0 ? 'border-red-200 bg-red-50' : 'border-slate-200 bg-slate-50' }}">
            <dt class="text-xs font-medium {{ $mail['failed'] > 0 ? 'text-red-700' : 'text-slate-500' }}">Failed after retries (30 days)</dt>
            <dd class="mt-1 text-xl font-semibold {{ $mail['failed'] > 0 ? 'text-red-800' : 'text-slate-900' }}">{{ $mail['failed'] }}</dd>
        </div>
        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
            <dt class="text-xs font-medium text-slate-500">Last email sent</dt>
            <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $ago($mail['last_sent']) }}</dd>
        </div>
        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
            <dt class="text-xs font-medium text-slate-500">Last failure</dt>
            <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $ago($mail['last_failed']) }}</dd>
            @if($mail['last_failure_reason'])
                <dd class="mt-1 break-words text-xs text-slate-500">{{ $mail['last_failure_reason'] }}</dd>
            @endif
        </div>
    </dl>
    <p class="mt-3 text-xs text-slate-500">Background worker last ran: {{ $ago($mail['last_heartbeat']) }}. If emails arrive late even when this shows active, check the mail provider's sending limits.</p>
</section>
