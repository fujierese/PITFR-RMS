<?php

namespace App\Http\Controllers;

use App\Models\ActivityProposalVersion;
use App\Models\FacilityRequest;
use App\Services\ActivityProposals;
use App\Services\DocumentUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Additional Part 2: Activity Proposal submission, verification and deadlines.
 * Separate from reservation approval: nothing here changes the reservation status.
 */
class ActivityProposalController extends Controller
{
    public function __construct(private readonly ActivityProposals $proposals)
    {
    }

    /** Requestor uploads a new version (Final Copy, or another draft while the current one is a draft). */
    public function upload(Request $request, int $id)
    {
        $facilityRequest = FacilityRequest::findOrFail($id);
        $user = Auth::user();
        abort_unless($user && (int) $facilityRequest->requested_by_id === (int) $user->id, 403);

        if (! in_array($facilityRequest->status, ['pending', 'approved', 'needs_reschedule'], true)) {
            return back()->withErrors(['proposal_file' => 'Proposals can no longer be uploaded for this request.']);
        }
        $current = $this->proposals->documentStatus($facilityRequest);
        if (in_array($current, ['for_verification', 'verified'], true)) {
            return back()->withErrors(['proposal_file' => $current === 'verified'
                ? 'Your activity proposal is already verified.'
                : 'Your Final Copy is waiting for Supply Office verification.']);
        }

        $validated = $request->validate([
            'proposal_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'submission_type' => ['required', 'in:draft,final'],
        ], [
            'proposal_file.required' => 'Choose the activity proposal file to upload.',
            'submission_type.required' => 'Select whether this is a Draft or the Final Copy.',
        ]);
        if ($current === 'requires_correction' && $validated['submission_type'] !== 'final') {
            return back()->withErrors(['submission_type' => 'A corrected proposal must be submitted as the Final Copy.']);
        }

        $result = app(DocumentUploadService::class)->uploadDocument($request->file('proposal_file'), 'activity_proposal', $facilityRequest->control_number);
        if (! ($result['success'] ?? false)) {
            return back()->withErrors(['proposal_file' => $result['error'] ?? 'The file could not be uploaded.']);
        }
        $this->proposals->recordSubmission($facilityRequest, $result['filename'], $validated['submission_type'], $user, $request->file('proposal_file')->getClientOriginalName());

        return redirect()->to(route('request.show', $facilityRequest->id) . '#activity-proposal')
            ->with('success', $validated['submission_type'] === 'final'
                ? 'Final Copy submitted. The Supply Office will verify it.'
                : 'Draft uploaded. Submit the Final Copy before the deadline.');
    }

    /** Open a stored version (anyone allowed to view the request). */
    public function file(int $id, int $version)
    {
        $facilityRequest = FacilityRequest::findOrFail($id);
        $this->authorize('view', $facilityRequest);
        $record = ActivityProposalVersion::query()->where('facility_request_id', $facilityRequest->id)->whereKey($version)->firstOrFail();
        $name = basename($record->file_name);
        abort_if($name === '' || $name !== $record->file_name, 404);
        $path = 'documents/activity_proposal/' . $name;
        abort_unless(Storage::disk('local')->exists($path), 404);
        $mime = match (strtolower(pathinfo($name, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => 'application/octet-stream',
        };
        $download = 'v' . $record->version_number . '-' . ($record->original_name ? basename($record->original_name) : $name);

        return Storage::disk('local')->response($path, $download, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function review(Request $request, int $id)
    {
        $facilityRequest = FacilityRequest::findOrFail($id);
        $admin = Auth::user();
        abort_unless($admin && $admin->isSupplyOffice(), 403);

        $validated = $request->validate([
            'result' => ['required', 'in:verified,requires_correction'],
            'remarks' => ['required_if:result,requires_correction', 'nullable', 'string', 'max:2000'],
            'correction_deadline' => ['nullable', 'date', 'after_or_equal:today'],
        ], [
            'remarks.required_if' => 'Write the correction instructions for the requestor.',
            'correction_deadline.after_or_equal' => 'The correction deadline cannot be in the past.',
        ]);

        if ($validated['result'] === 'verified') {
            $this->proposals->verify($facilityRequest, $admin, $validated['remarks'] ?? null);
            $message = 'Activity proposal verified.';
        } else {
            $this->proposals->requireCorrection($facilityRequest, $admin, $validated['remarks'], $validated['correction_deadline'] ?? null);
            $message = 'Correction requested. The requestor was notified.';
        }

        return redirect()->to(route('request.show', $facilityRequest->id) . '#activity-proposal')->with('success', $message);
    }

    public function deadline(Request $request, int $id)
    {
        $facilityRequest = FacilityRequest::findOrFail($id);
        $admin = Auth::user();
        abort_unless($admin && $admin->isSupplyOffice(), 403);

        $validated = $request->validate([
            'deadline' => ['required', 'date', 'after_or_equal:today'],
            'reason' => [$facilityRequest->proposal_deadline ? 'required' : 'nullable', 'nullable', 'string', 'max:1000'],
        ], [
            'deadline.after_or_equal' => 'The deadline cannot be in the past.',
            'reason.required' => 'Give a reason for changing the deadline.',
        ]);
        $this->proposals->setDeadline($facilityRequest, $validated['deadline'], $admin, $validated['reason'] ?? null);

        return redirect()->to(route('request.show', $facilityRequest->id) . '#activity-proposal')->with('success', 'Deadline saved. The requestor was notified.');
    }

    public function followUp(Request $request, int $id)
    {
        $facilityRequest = FacilityRequest::findOrFail($id);
        $admin = Auth::user();
        abort_unless($admin && $admin->isSupplyOffice(), 403);
        $validated = $request->validate(['remarks' => ['required', 'string', 'min:3', 'max:2000']]);
        $this->proposals->recordFollowUp($facilityRequest, $admin, $validated['remarks']);

        return redirect()->to(route('request.show', $facilityRequest->id) . '#activity-proposal')->with('success', 'Follow-up recorded.');
    }

    /** Supply Office monitoring list. */
    public function index(Request $request)
    {
        abort_unless(Auth::user()?->isSupplyOffice(), 403);
        $filter = (string) $request->query('filter', 'all');
        $filters = ['all', 'draft_submitted', 'for_verification', 'requires_correction', 'verified', 'overdue'];
        if (! in_array($filter, $filters, true)) {
            $filter = 'all';
        }
        $rows = $this->rows();
        $counts = collect($filters)->mapWithKeys(fn ($f) => [$f => $f === 'all' ? $rows->count() : $rows->filter(fn ($r) => $this->matches($r, $f))->count()]);
        $rows = $filter === 'all' ? $rows : $rows->filter(fn ($r) => $this->matches($r, $filter));

        return view('supply-office.activity-proposals', [
            'rows' => $rows->values(),
            'filter' => $filter,
            'counts' => $counts,
        ]);
    }

    private function matches(array $row, string $filter): bool
    {
        return $filter === 'overdue' ? $row['compliance'] === 'overdue' : $row['document'] === $filter;
    }

    /** @return \Illuminate\Support\Collection<int, array> */
    public function rows(): \Illuminate\Support\Collection
    {
        return FacilityRequest::query()
            ->whereIn('status', ['pending', 'approved', 'needs_reschedule'])
            ->where(fn ($q) => $q->whereNotNull('proposal_deadline')->orWhereIn('id', ActivityProposalVersion::query()->select('facility_request_id')))
            ->with('requester')
            ->orderBy('start_date')
            ->get()
            ->map(function (FacilityRequest $request): array {
                $summary = $this->proposals->summary($request);

                return $summary + ['request' => $request];
            });
    }
}
