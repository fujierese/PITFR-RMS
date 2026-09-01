@extends('layouts.app')
@section('title', 'Facility Calendar Dashboard')

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
<style>
    .fc-header-toolbar {
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    .fc-button,
    .fc-button-primary {
        background-color: #f3f4f6 !important;
        border: 1px solid #d1d5db !important;
        color: #374151 !important;
        border-radius: 8px !important;
        padding: 0.65rem 1rem !important;
        font-weight: 600 !important;
        box-shadow: none !important;
        text-transform: none !important;
        transition: all 0.18s ease !important;
    }
    .fc-button:hover,
    .fc-button-primary:hover {
        background-color: #e5e7eb !important;
        color: #111827 !important;
        border-color: #cbd5e1 !important;
    }
    .fc-button-primary.fc-button-active,
    .fc-button-primary:active,
    .fc-button.fc-button-active {
        background-color: #059669 !important;
        border-color: #059669 !important;
        color: #ffffff !important;
    }
    .fc-button-primary.fc-button-active:hover,
    .fc-button-primary:active:hover,
    .fc-button.fc-button-active:hover {
        background-color: #047857 !important;
        border-color: #047857 !important;
    }
    .fc-event {
        cursor: pointer;
        font-size: 0.875rem;
    }
    .fc-event:hover {
        opacity: 0.8;
        transform: scale(1.02);
    }
    .calendar-container {
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        padding: 2rem;
        margin-bottom: 2rem;
    }
    .dashboard-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 2rem;
        border-radius: 12px;
        margin-bottom: 2rem;
    }
    .role-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.875rem;
        font-weight: 500;
    }
    .role-admin { background: #EF4444; color: white; }
    .role-custodian { background: #F59E0B; color: white; }
    .role-requestor { background: #10B981; color: white; }
</style>
@endpush

@section('content')
<div class="max-w-7xl mx-auto p-4">
    {{-- Dashboard Header --}}
    <div class="dashboard-header">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold mb-2">Facility Calendar Dashboard</h1>
                <p class="text-lg opacity-90">Welcome back, {{ auth()->user()->name }}!</p>
                <p class="text-sm opacity-75 mt-1">View and manage all facility reservations</p>
            </div>
            <div class="text-right">
                <span class="role-badge role-{{ $role ?? 'requestor' }}">
                    {{ ucfirst($role ?? 'requestor') }}
                </span>
                <p class="text-sm mt-2 opacity-75">{{ now()->format('l, F j, Y') }}</p>
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        @if($role === 'requestor' || $role === 'admin')
        <a href="{{ route('requestor.index') }}" class="bg-blue-600 text-white p-4 rounded-lg hover:bg-blue-700 transition">
            <div class="flex items-center">
                <svg class="w-8 h-8 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                <div>
                    <h3 class="font-semibold">New Request</h3>
                    <p class="text-sm opacity-90">Create facility request</p>
                </div>
            </div>
        </a>
        @endif

        @if($role === 'admin')
        <a href="{{ route('supply-office.index') }}" class="bg-green-600 text-white p-4 rounded-lg hover:bg-green-700 transition">
            <div class="flex items-center">
                <svg class="w-8 h-8 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                <div>
                    <h3 class="font-semibold">Supply Office Panel</h3>
                    <p class="text-sm opacity-90">Manage all requests and approvals.</p>
                </div>
            </div>
        </a>
        @endif

        @if($role === 'custodian')
        <a href="{{ route('custodian.index') }}" class="bg-orange-600 text-white p-4 rounded-lg hover:bg-orange-700 transition">
            <div class="flex items-center">
                <svg class="w-8 h-8 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <div>
                    <h3 class="font-semibold">Custodian Panel</h3>
                    <p class="text-sm opacity-90">Manage equipment/venue</p>
                </div>
            </div>
        </a>
        @endif
    </div>

    {{-- Main Calendar --}}
    <div class="calendar-container">
        <div id="calendar" class="w-full" style="min-height: 800px;"></div>
    </div>

    {{-- Legend --}}
    <div class="bg-white rounded-lg p-4 shadow">
        <h3 class="font-semibold mb-3">Status Legend</h3>
        <div class="flex flex-wrap gap-4">
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 bg-green-500 rounded"></div>
                <span>Approved</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 bg-yellow-500 rounded"></div>
                <span>Pending</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 bg-red-500 rounded"></div>
                <span>Rejected</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 bg-gray-500 rounded"></div>
                <span>Other</span>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    const isPublicCalendar = @json(!auth()->check());

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        height: 'auto',
        eventTimeFormat: {
            hour: 'numeric',
            minute: '2-digit',
            meridiem: 'short'
        },
        events: function(fetchInfo, successCallback, failureCallback) {
            fetch('{{ route('calendar.events') }}')
                .then(response => response.json())
                .then(data => {
                    console.log('Calendar event payload:', data);
                    const mappedEvents = data.map(event => {
                        let startDatetime = event.start_datetime || event.start || '';
                        let endDatetime = event.end_datetime || event.end || '';

                        if (startDatetime.indexOf(' ') !== -1) {
                            startDatetime = startDatetime.replace(' ', 'T');
                        }
                        if (endDatetime.indexOf(' ') !== -1) {
                            endDatetime = endDatetime.replace(' ', 'T');
                        }

                        const isTimed = /\dT\d{2}:\d{2}/.test(startDatetime);

                        return {
                            id: event.id,
                            title: event.title,
                            start: startDatetime,
                            end: endDatetime,
                            allDay: !isTimed,
                            backgroundColor: event.backgroundColor || '#059669',
                            borderColor: event.borderColor || '#059669',
                            textColor: event.textColor || '#ffffff',
                            extendedProps: event.extendedProps || {},
                        };
                    });
                    successCallback(mappedEvents);
                })
                .catch(error => {
                    console.error('Failed to load calendar events:', error);
                    failureCallback(error);
                });
        },
        eventClick: function(info) {
            if (isPublicCalendar) {
                return;
            }

            const requestId = info.event.id || info.event.extendedProps.facilityRequestId;
            if (requestId) {
                window.location.href = '{{ url("/request") }}/' + requestId;
            }
        }
    });

    calendar.render();

    // Global functions for modal
    window.closeModal = function() {
        const modal = document.getElementById('eventModal');
        if (modal) modal.remove();
    };

    window.approveRequest = function(eventId) {
        Swal.fire({
            title: 'Final Approval',
            text: 'Are you sure you want to approve this request? This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Approve it!',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#059669',
            cancelButtonColor: '#9CA3AF',
            reverseButtons: false
        }).then((result) => {
            if (result.isConfirmed) {
                performAction('approve', eventId);
            }
        });
    };

    window.rejectRequest = function(eventId) {
        Swal.fire({
            title: 'Reject Request',
            text: 'Are you sure you want to reject this request?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Reject it!',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#DC2626',
            cancelButtonColor: '#9CA3AF',
            reverseButtons: false
        }).then((result) => {
            if (result.isConfirmed) {
                performAction('reject', eventId);
            }
        });
    };

    window.approveVenue = function(eventId) {
        Swal.fire({
            title: 'Approve Venue',
            text: 'Are you sure you want to approve the venue for this request?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Approve it!',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#059669',
            cancelButtonColor: '#9CA3AF',
            reverseButtons: false
        }).then((result) => {
            if (result.isConfirmed) {
                performAction('approve', eventId, 'venue');
            }
        });
    };

    window.rejectVenue = function(eventId) {
        Swal.fire({
            title: 'Reject Venue',
            text: 'Are you sure you want to reject the venue for this request?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Reject it!',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#DC2626',
            cancelButtonColor: '#9CA3AF',
            reverseButtons: false
        }).then((result) => {
            if (result.isConfirmed) {
                performAction('reject', eventId, 'venue');
            }
        });
    };

    window.approveEquipment = function(eventId) {
        Swal.fire({
            title: 'Approve Equipment',
            text: 'Are you sure you want to approve the equipment for this request?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Approve it!',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#059669',
            cancelButtonColor: '#9CA3AF',
            reverseButtons: false
        }).then((result) => {
            if (result.isConfirmed) {
                performAction('approve', eventId, 'equipment');
            }
        });
    };

    window.rejectEquipment = function(eventId) {
        Swal.fire({
            title: 'Reject Equipment',
            text: 'Are you sure you want to reject the equipment for this request?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Reject it!',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#DC2626',
            cancelButtonColor: '#9CA3AF',
            reverseButtons: false
        }).then((result) => {
            if (result.isConfirmed) {
                performAction('reject', eventId, 'equipment');
            }
        });
    };

    window.markAsReturned = function(eventId) {
        Swal.fire({
            title: 'Mark as Returned',
            text: 'Are you sure you want to mark this equipment as returned?',
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Yes, Mark Returned',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#059669',
            cancelButtonColor: '#9CA3AF',
            reverseButtons: false
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(`/calendar/return/${eventId}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    Swal.fire({
                        title: 'Success!',
                        text: data.message,
                        icon: 'success',
                        confirmButtonColor: '#059669',
                        timer: 2000,
                        timerProgressBar: true
                    });
                    calendar.refetchEvents();
                    closeModal();
                })
                .catch(err => Swal.fire({
                    title: 'Error',
                    text: 'Error: ' + err,
                    icon: 'error',
                    confirmButtonColor: '#059669'
                }));
            }
        });
    };

    function performAction(action, eventId, type = null) {
        const url = type
            ? `/calendar/${action}/${eventId}?type=${type}`
            : `/calendar/${action}/${eventId}`;

        fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            Swal.fire({
                title: 'Success',
                text: data.message,
                icon: 'success',
                confirmButtonColor: '#059669'
            });
            calendar.refetchEvents();
            closeModal();
        })
        .catch(err => Swal.fire({
            title: 'Error',
            text: 'Error: ' + err,
            icon: 'error',
            confirmButtonColor: '#059669'
        }));
    }

    function getStatusBadgeClass(status) {
        switch(status?.toLowerCase()) {
            case 'approved': return 'bg-green-100 text-green-800';
            case 'pending': return 'bg-yellow-100 text-yellow-800';
            case 'rejected': return 'bg-red-100 text-red-800';
            default: return 'bg-gray-100 text-gray-800';
        }
    }
});
</script>
@endpush