<?php

namespace App\Http\Controllers;

use App\Models\FacilityRequest;

/**
 * Public verification page for a printed request form (issue APR-15).
 * Shows only what is already on the printed form's approval block:
 * control number, schedule, venue, status and the approval references.
 * No personal names, contact details or documents.
 */
class RequestVerificationController extends Controller
{
    public function __invoke(int $facilityRequest, string $code)
    {
        $request = FacilityRequest::find($facilityRequest);

        if (! $request || ! hash_equals($request->verificationCode(), $code)) {
            return response()->view('request.verify', ['request' => null], 404);
        }

        return response()->view('request.verify', [
            'request' => $request,
            'approvalRecords' => $request->digitalApprovalRecords(),
        ])->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
