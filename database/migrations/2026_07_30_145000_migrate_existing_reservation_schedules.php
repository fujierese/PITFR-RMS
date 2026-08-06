<?php

use App\Models\FacilityRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $requests = FacilityRequest::all();

        foreach ($requests as $request) {
            $startDate = $request->start_date;
            $startTime = $request->start_time;
            $endDate = $request->end_date ?? $request->start_date;
            $endTime = $request->end_time ?? $request->start_time;

            $startDatetime = $this->normalizeScheduleValue($startDate, $startTime);
            $endDatetime = $this->normalizeScheduleValue($endDate, $endTime, $startDatetime);

            if (!$startDatetime || !$endDatetime) {
                continue;
            }

            if ($endDatetime->lte($startDatetime)) {
                $endDatetime = $startDatetime->copy()->addDay();
            }

            DB::table('reservation_schedules')->insert([
                'facility_request_id' => $request->id,
                'start_datetime' => $startDatetime->toDateTimeString(),
                'end_datetime' => $endDatetime->toDateTimeString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function normalizeScheduleValue($dateValue, $timeValue, ?\Carbon\Carbon $fallback = null): ?\Carbon\Carbon
    {
        if (!$dateValue && !$timeValue) {
            return null;
        }

        $datePart = $dateValue instanceof \Carbon\Carbon ? $dateValue->toDateString() : (string) $dateValue;
        $timePart = $timeValue instanceof \Carbon\Carbon ? $timeValue->toTimeString() : (string) $timeValue;

        if ($datePart === '' && $timePart === '') {
            return null;
        }

        $candidate = trim($datePart . ' ' . $timePart);
        if ($candidate === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(?::\d{2})?$/', $candidate)) {
            return \Carbon\Carbon::parse($candidate);
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $datePart) && preg_match('/^\d{2}:\d{2}(?::\d{2})?$/', $timePart)) {
            return \Carbon\Carbon::parse($datePart . ' ' . $timePart);
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $candidate)) {
            return \Carbon\Carbon::parse($candidate);
        }

        if (preg_match('/^\d{2}:\d{2}(?::\d{2})?$/', $candidate)) {
            return \Carbon\Carbon::parse($fallback?->toDateString() . ' ' . $candidate);
        }

        return \Carbon\Carbon::parse($candidate);
    }

    public function down(): void
    {
        DB::table('reservation_schedules')->delete();
    }
};
