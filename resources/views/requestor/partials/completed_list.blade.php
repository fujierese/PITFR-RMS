@if($completedRequests->isEmpty())
<div class="py-16 text-center">
    <div class="text-5xl mb-4">✅</div>
    <p class="text-gray-400 font-semibold text-lg">No completed requests yet</p>
    <p class="text-gray-300 text-sm mt-1">Completed requests will appear here after equipment is returned</p>
</div>
@else
<div class="space-y-4">
    @foreach($completedRequests as $req)
    <a href="{{ route('request.show', $req->id) }}" class="block">
    <div class="cursor-pointer rounded-2xl border border-gray-100 bg-white p-4 transition hover:border-emerald-200 hover:shadow-md sm:p-5">

        {{-- Top Row --}}
        <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h3 class="font-bold text-gray-800 text-base">{{ $req->name_of_activity }}</h3>
                <p class="text-xs text-gray-400 mt-0.5">{{ $req->department }}</p>
            </div>
            <div class="flex flex-col items-end gap-1">
                <span class="shrink-0 px-3 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700">
                    ✅ Completed
                </span>
                <span class="text-xs text-gray-500">
                    Returned: {{ $req->equipment_returned_date->format('M j, Y') }}
                </span>
            </div>
        </div>

        {{-- Meta Info --}}
        <div class="mb-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="bg-gray-50 rounded-xl p-3">
                <p class="text-xs text-gray-400 mb-0.5">Control No.</p>
                <p class="text-xs font-semibold text-gray-700">{{ $req->control_number }}</p>
            </div>
            <div class="bg-gray-50 rounded-xl p-3">
                <p class="text-xs text-gray-400 mb-0.5">Date</p>
                <p class="text-xs font-semibold text-gray-700">{{ $req->start_date->format('M j, Y') }}</p>
            </div>
            <div class="bg-gray-50 rounded-xl p-3">
                <p class="text-xs text-gray-400 mb-0.5">Time</p>
                <p class="text-xs font-semibold text-gray-700">
                    {{ \Carbon\Carbon::parse($req->start_time)->format('g:i A') }}
                    @if($req->end_time)
                        — {{ \Carbon\Carbon::parse($req->end_time)->format('g:i A') }}
                    @endif
                </p>
            </div>
            <div class="bg-gray-50 rounded-xl p-3">
                <p class="text-xs text-gray-400 mb-0.5">Participants</p>
                <p class="text-xs font-semibold text-gray-700">{{ $req->expected_participants }}</p>
            </div>
        </div>

        {{-- Approval Status --}}
        <div class="mb-3 flex flex-wrap gap-2">
            <span class="flex items-center gap-1 text-xs px-2.5 py-1 rounded-full font-medium bg-green-100 text-green-700">
                🏛️ Venue: {{ ucfirst($req->venue_status) }}
            </span>
            <span class="flex items-center gap-1 text-xs px-2.5 py-1 rounded-full font-medium bg-green-100 text-green-700">
                🔧 Equipment: {{ ucfirst($req->equipment_status) }}
            </span>
            <span class="flex items-center gap-1 text-xs px-2.5 py-1 rounded-full font-medium bg-green-100 text-green-700">
                ✅ Final: {{ ucfirst($req->status) }}
            </span>
            <span class="flex items-center gap-1 text-xs px-2.5 py-1 rounded-full font-medium bg-emerald-100 text-emerald-700">
                🔄 Return: Completed
            </span>
        </div>

        {{-- Venues --}}
        @php $completedVenues = $req->getVenueNames(); @endphp
        @if(!empty($completedVenues))
        <div class="mb-2">
            <p class="text-xs text-gray-400 mb-1">Venues</p>
            <div class="flex flex-wrap gap-1.5">
                @foreach($completedVenues as $v)
                    <span class="bg-emerald-50 text-emerald-700 text-xs font-medium px-2.5 py-0.5 rounded-full border border-emerald-100">
                        📍 {{ $v }}
                    </span>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Equipment --}}
        @php $completedEquipment = $req->getEquipmentItems(); $completedQuantities = $req->getEquipmentQuantities(); @endphp
        @if(!empty($completedEquipment))
        <div class="mb-3">
            <p class="text-xs text-gray-400 mb-1">Equipment</p>
            <div class="flex flex-wrap gap-1.5">
                @foreach($completedEquipment as $e)
                    <span class="bg-purple-50 text-purple-700 text-xs font-medium px-2.5 py-0.5 rounded-full border border-purple-100">
                        🔧 {{ $e }}
                        @if(!empty($completedQuantities[$e]))
                            <span class="font-bold">(×{{ $completedQuantities[$e] }})</span>
                        @endif
                    </span>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Return Notes --}}
        @if($req->equipment_return_notes)
        <div class="bg-emerald-50 border border-emerald-100 rounded-xl px-3 py-2">
            <p class="text-xs text-emerald-600"><strong>Return Notes:</strong> {{ $req->equipment_return_notes }}</p>
        </div>
        @endif

    </div>
    </a>
    @endforeach
</div>
@endif