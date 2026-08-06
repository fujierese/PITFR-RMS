<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class RequestFormFeedbackTest extends TestCase
{
    public function test_request_form_shows_external_requestor_guidance_and_summary(): void
    {
        $user = new User([
            'id' => 1,
            'role' => 'requestor',
            'requestor_type' => 'outsider',
            'name' => 'Juan Dela Cruz',
            'department' => 'Private Citizen',
        ]);

        $html = view('requestor.partials.request_form', [
            'controlNumber' => 'FER-2026-001',
            'venueOptions' => ['Gymnasium'],
            'equipment' => collect(),
            'errors' => new \Illuminate\Support\ViewErrorBag(),
            'requestorType' => 'outsider',
        ])->render();

        $this->assertStringContainsString('External Requestor Guidance', $html);
        $this->assertStringContainsString('Applicable rental fees and payment procedures', $html);
        $this->assertStringContainsString('Selected Items Summary', $html);
        $this->assertStringContainsString('Request Progress', $html);
        $this->assertStringContainsString('Draft autosave', $html);
        $this->assertStringContainsString('Official Request Form', $html);
        $this->assertStringContainsString('Submission checklist', $html);
    }

    public function test_request_form_shows_default_equipment_options_when_database_is_empty(): void
    {
        $html = view('requestor.partials.request_form', [
            'controlNumber' => 'FER-2026-001',
            'venueOptions' => ['Gymnasium'],
            'equipment' => collect(),
            'errors' => new \Illuminate\Support\ViewErrorBag(),
            'requestorType' => 'outsider',
        ])->render();

        $this->assertStringContainsString('Sound System', $html);
        $this->assertStringContainsString('Microphones', $html);
    }
}
