@php
    $venueSchedules = collect($requestItem->getVenueScheduleSummaries())
        ->sortBy(fn (array $schedule) => $requestItem->getVenueScheduleRange($schedule['venue'])['start']->timestamp)
        ->values();
    $venueEquipment = $requestItem->getVenueEquipmentAllocations();
    $normalizedAssignedVenues = collect($assignedVenueNames)->map(fn ($name) => mb_strtolower(trim($name)));
    $normalizedAssignedEquipment = collect($assignedEquipmentNames)->map(fn ($name) => mb_strtolower(trim($name)));
@endphp

<div class="space-y-3">
    <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Venue schedules <span class="font-normal normal-case tracking-normal">(earliest first)</span></p>
        <div class="mt-1.5 space-y-1.5">
            @forelse($venueSchedules as $schedule)
                @php
                    $isAssignedVenue = $normalizedAssignedVenues->contains(mb_strtolower(trim($schedule['venue'])));
                @endphp
                <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1 text-sm">
                    <span class="font-medium text-slate-800">{{ $schedule['venue'] }}</span>
                    <span class="text-slate-600">{{ $schedule['label'] }}</span>
                    @if($custodianType === 'venue')
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $isAssignedVenue ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                            {{ $isAssignedVenue ? 'Your venue' : 'Other venue in request' }}
                        </span>
                    @endif
                </div>
            @empty
                <p class="text-sm text-slate-500">No venue schedule listed</p>
            @endforelse
        </div>
    </div>

    <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Equipment requested</p>
        <div class="mt-1.5 space-y-2">
            @php
                $renderedEquipment = false;
            @endphp
            @foreach($venueSchedules as $schedule)
                @php
                    $venueName = $schedule['venue'];
                    $venueItems = $venueEquipment[$venueName] ?? [];
                    $venueItems = collect($venueItems)
                        ->filter(fn ($quantity, $name) => $custodianType !== 'equipment'
                            || $normalizedAssignedEquipment->contains(mb_strtolower(trim((string) $name))));
                @endphp
                @if($venueItems->isNotEmpty())
                    @php
                        $renderedEquipment = true;
                    @endphp
                    <div class="rounded-lg border border-slate-100 bg-slate-50/70 px-3 py-2">
                        <p class="text-xs font-semibold text-slate-700">{{ $venueName }}</p>
                        <ul class="mt-1 flex flex-wrap gap-1.5">
                            @foreach($venueItems as $equipmentName => $quantity)
                                <li class="rounded-md bg-white px-2 py-1 text-xs text-slate-700 ring-1 ring-slate-200">
                                    {{ $equipmentName }} <span class="font-semibold text-slate-500">× {{ $quantity }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endforeach

            @if(!$renderedEquipment)
                @php
                    $equipmentQuantities = collect($requestItem->getEquipmentQuantities())
                        ->filter(fn ($quantity, $name) => $custodianType !== 'equipment'
                            || $normalizedAssignedEquipment->contains(mb_strtolower(trim((string) $name))));
                    $equipmentItems = $equipmentQuantities->isNotEmpty()
                        ? $equipmentQuantities
                        : collect($requestItem->getEquipmentItems())
                            ->filter(fn ($name) => $custodianType !== 'equipment'
                                || $normalizedAssignedEquipment->contains(mb_strtolower(trim((string) $name))))
                            ->mapWithKeys(fn ($name) => [$name => 1]);
                @endphp
                @if($equipmentItems->isNotEmpty())
                    <ul class="flex flex-wrap gap-1.5">
                        @foreach($equipmentItems as $equipmentName => $quantity)
                            <li class="rounded-md bg-slate-50 px-2 py-1 text-xs text-slate-700 ring-1 ring-slate-200">
                                {{ $equipmentName }} <span class="font-semibold text-slate-500">× {{ $quantity }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm text-slate-500">{{ $custodianType === 'equipment' ? 'No equipment assigned to you in this request' : 'None requested' }}</p>
                @endif
            @endif
        </div>
    </div>
</div>
