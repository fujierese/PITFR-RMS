{{-- Follow-up fixes: shared search and filter bar (see components/search-filter-bar). --}}
<x-search-filter-bar
    class="mt-6 md:mt-8"
    :action="$action"
    :search="(string) ($searchQuery ?? '')"
    placeholder="Search requests"
    :filters="[
        ['type' => 'select', 'name' => 'review', 'label' => 'Review status', 'value' => (string) request('review', ''), 'options' => ['' => 'Any review status', 'Venue review' => ['venue_pending' => 'Waiting for venue check', 'venue_approved' => 'Venue available', 'venue_reschedule' => 'Venue needs new schedule'], 'Supply Office decision' => ['supply_pending' => 'Waiting for Supply Office', 'supply_confirmed' => 'Availability confirmed', 'supply_unavailable' => 'Marked unavailable'], 'Equipment review' => ['equipment_pending' => 'Waiting for equipment review', 'equipment_approved' => 'Equipment approved', 'equipment_declined' => 'Equipment declined']]],
        ['type' => 'text', 'name' => 'venue', 'label' => 'Venue', 'value' => (string) request('venue', ''), 'placeholder' => 'Venue'],
        ['type' => 'text', 'name' => 'equipment', 'label' => 'Equipment', 'value' => (string) request('equipment', ''), 'placeholder' => 'Equipment'],
        ['type' => 'text', 'name' => 'department', 'label' => 'Department', 'value' => (string) request('department', ''), 'placeholder' => 'Department'],
        ['type' => 'select', 'name' => 'priority', 'label' => 'Priority', 'value' => (string) request('priority', ''), 'options' => ['' => 'All priorities', 'regular' => 'Regular', 'institutional' => 'Institutional']],
        ['type' => 'date', 'name' => 'date_from', 'label' => 'Date from', 'value' => (string) request('date_from', '')],
        ['type' => 'date', 'name' => 'date_to', 'label' => 'Date to', 'value' => (string) request('date_to', '')],
    ]"
/>
