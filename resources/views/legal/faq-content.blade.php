<section class="space-y-4 rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:p-8" aria-label="Reservation frequently asked questions">
    @foreach ([
        ['Who can submit a request?', 'Registered users with an authorized requestor account can submit a facility or equipment request. Keep your contact and organization details up to date.'],
        ['When is my reservation confirmed?', 'Submitting a request does not reserve the venue by itself. Venue and equipment custodians review it, then the Supply Office gives final approval. Check the request details and notifications for progress.'],
        ['Does an urgent request skip approval?', 'No. Urgent requests still need human review. They do not automatically replace or cancel another approved reservation. Only an authorized Supply Office administrator may approve a conflict override and must provide a reason.'],
        ['How do I check my request?', 'Open the Requests area in your dashboard or use the link in a notification. The request details show its status, approval steps, and activity history.'],
        ['Can I cancel a request?', 'A requestor can cancel a request while it is pending. Once it is approved or has moved to another final state, contact the Supply Office for help.'],
        ['What documents can I upload?', 'The request form indicates which supporting document is needed for your request type. Accepted uploads are PDF, JPEG, or PNG and must be no larger than 10 MB. Do not upload unrelated sensitive information.'],
        ['What happens to borrowed equipment?', 'The assigned equipment custodian records quantities returned, damaged, or missing. Return equipment according to the instructions from your custodian.'],
        ['Who can answer a question about my personal information?', 'The privacy contact and official privacy procedures for this draft policy still need confirmation by Palompon Institute of Technology. See the Privacy Policy draft for the fields that must be completed.'],
    ] as [$question, $answer])
        <details class="group rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
            <summary class="cursor-pointer list-none pr-8 text-base font-semibold text-slate-900 marker:hidden focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
                {{ $question }}
                <span class="float-right text-emerald-700 transition group-open:rotate-45" aria-hidden="true">+</span>
            </summary>
            <p class="mt-3 max-w-4xl text-sm leading-6 text-slate-700">{{ $answer }}</p>
        </details>
    @endforeach
</section>
