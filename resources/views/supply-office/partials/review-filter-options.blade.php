{{-- Review status filter (venue review, Supply Office decision, equipment review). --}}
<select @if($id) id="{{ $id }}" @endif name="review" class="{{ $class }}">
    <option value="">Any review status</option>
    <optgroup label="Venue review">
        <option value="venue_pending" @selected($selected === 'venue_pending')>Waiting for venue check</option>
        <option value="venue_approved" @selected($selected === 'venue_approved')>Venue available</option>
        <option value="venue_reschedule" @selected($selected === 'venue_reschedule')>Venue needs new schedule</option>
    </optgroup>
    <optgroup label="Supply Office decision">
        <option value="supply_pending" @selected($selected === 'supply_pending')>Waiting for Supply Office</option>
        <option value="supply_confirmed" @selected($selected === 'supply_confirmed')>Availability confirmed</option>
        <option value="supply_unavailable" @selected($selected === 'supply_unavailable')>Marked unavailable</option>
    </optgroup>
    <optgroup label="Equipment review">
        <option value="equipment_pending" @selected($selected === 'equipment_pending')>Waiting for equipment review</option>
        <option value="equipment_approved" @selected($selected === 'equipment_approved')>Equipment approved</option>
        <option value="equipment_declined" @selected($selected === 'equipment_declined')>Equipment declined</option>
    </optgroup>
</select>
