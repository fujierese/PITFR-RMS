<?php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RequestorController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CustodianController;
use App\Http\Controllers\SupplyOfficeController;
use App\Http\Controllers\RequestActionController;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\FacilityRequestApiController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CalendarController;

// Legacy browser API endpoints. These expose operational request data and must
// remain authenticated even when retained for backwards compatibility.
Route::middleware('auth')->prefix('api-test')->group(function () {
    Route::get('facility-requests', [FacilityRequestApiController::class, 'index']);
    Route::post('facility-requests', [FacilityRequestApiController::class, 'store']);
    Route::get('facility-requests/{facility_request}', [FacilityRequestApiController::class, 'show']);
    Route::put('facility-requests/{facility_request}', [FacilityRequestApiController::class, 'update']);
    Route::delete('facility-requests/{facility_request}', [FacilityRequestApiController::class, 'destroy']);
    Route::post('facility-requests/{facility_request}/approve', [FacilityRequestApiController::class, 'approve']);
    Route::post('facility-requests/{facility_request}/reject', [FacilityRequestApiController::class, 'reject']);
    Route::post('facility-requests/{facility_request}/cancel', [FacilityRequestApiController::class, 'cancel']);
    Route::post('facility-requests/{facility_request}/return-equipment', [FacilityRequestApiController::class, 'returnEquipment']);
    Route::get('equipment-availability', [FacilityRequestApiController::class, 'equipmentAvailability']);
    Route::get('venue-availability', [FacilityRequestApiController::class, 'venueAvailability']);
});

// Public routes
Route::get('/', function() {
    if (Auth::check()) {
        $user = Auth::user();
        if (in_array($user->role, ['admin', 'facility_admin'], true)) {
            return redirect()->route('supply-office.index');
        } elseif ($user->role === 'custodian' || str_starts_with($user->role, 'custodian')) {
            return redirect()->route('custodian.index');
        } else {
            return redirect()->route('requestor.index');
        }
    }
    return view('welcome');
})->name('home');
Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
Route::get('/login',   [AuthController::class, 'showLogin'])->name('login');
Route::post('/login',  [AuthController::class, 'login']);
Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');
Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
Route::get('/auth/google/redirect', [AuthController::class, 'redirectToGoogle'])->name('google.redirect');
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('google.callback');

// Requestor registration
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->name('register.post');
Route::get('/register/departments/{college}', [AuthController::class, 'departments'])->name('register.departments');
Route::get('/register/verify', [AuthController::class, 'showVerify'])->name('register.verify');
Route::post('/register/verify', [AuthController::class, 'verify'])->name('register.verify.post');
Route::post('/register/verify/resend', [AuthController::class, 'resendOtp'])->name('register.verify.resend');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Requestors
Route::middleware(['auth', 'role:requestor'])->prefix('requestor')->group(function () {
    Route::get('/',        fn () => redirect()->route('requestor.index'));
    Route::get('/dashboard', [RequestorController::class, 'index'])->name('requestor.index');
    Route::post('/store',  [RequestorController::class, 'store'])->name('requestor.store');
    Route::get('/requests/{facilityRequest}/edit', [RequestorController::class, 'edit'])->name('requestor.edit');
    Route::put('/requests/{facilityRequest}', [RequestorController::class, 'update'])->name('requestor.update');
    Route::post('/delete', [RequestorController::class, 'destroy'])->name('requestor.destroy');
    Route::get('/settings', [RequestorController::class, 'settings'])->name('requestor.settings');
    Route::post('/settings/profile', [RequestorController::class, 'updateProfile'])->name('requestor.settings.profile');
    Route::post('/settings/password', [RequestorController::class, 'updatePassword'])->name('requestor.settings.password');
    Route::post('/settings/notifications', [RequestorController::class, 'updateNotificationPreferences'])->name('requestor.settings.notifications');
    Route::post('/settings/signature', [RequestorController::class, 'updateSignature'])->name('requestor.settings.signature');
    Route::get('/equipment/availability', [RequestorController::class, 'equipmentAvailability'])->name('equipment.availability');
});

// Supply Office is now the canonical administrative role and dashboard.
Route::middleware(['auth', 'role:admin'])->prefix('supply-office')->group(function () {
    Route::get('/',         fn () => redirect()->route('supply-office.index'));
    Route::get('/dashboard', [SupplyOfficeController::class, 'index'])->name('supply-office.index');
    Route::get('/requests/pending', [SupplyOfficeController::class, 'pendingRequests'])->name('supply-office.requests.pending');
    Route::get('/requests/final-approval', [SupplyOfficeController::class, 'finalApprovalRequests'])->name('supply-office.requests.final-approval');
    Route::get('/requests/approved', [SupplyOfficeController::class, 'approvedRequests'])->name('supply-office.requests.approved');
    Route::get('/requests/rejected', [SupplyOfficeController::class, 'rejectedRequests'])->name('supply-office.requests.rejected');
    Route::get('/requests/needs-reschedule', [SupplyOfficeController::class, 'needsRescheduleRequests'])->name('supply-office.requests.needs-reschedule');
    Route::get('/requests/returns', [SupplyOfficeController::class, 'equipmentReturns'])->name('supply-office.requests.returns');
    Route::get('/final-approval', [SupplyOfficeController::class, 'finalApprovalRequests'])->name('supply-office.final-approval');
    Route::middleware('role:admin')->group(function (): void {
        Route::get('/users', [AdminController::class, 'users'])->name('supply-office.users');
        Route::post('/users', [AdminController::class, 'storeUser'])->name('supply-office.users.store');
        Route::get('/reports', [AdminController::class, 'reports'])->name('supply-office.usage-reports');
    });
    Route::get('/settings', [AdminController::class, 'settings'])->name('supply-office.settings');
    Route::post('/settings/profile', [AdminController::class, 'updateProfile'])->name('supply-office.settings.profile');
    Route::post('/settings/password', [AdminController::class, 'updatePassword'])->name('supply-office.settings.password');
    Route::post('/settings/notifications', [AdminController::class, 'updateNotificationPreferences'])->name('supply-office.settings.notifications');
    Route::get('/calendar', [CalendarController::class, 'index'])->name('supply-office.calendar');
    Route::middleware('role:admin')->group(function (): void {
        Route::get('/organizations', [AdminController::class, 'organizations'])->name('supply-office.organizations');
        Route::post('/organizations', [AdminController::class, 'storeOrganization'])->name('supply-office.organizations.store');
        Route::put('/organizations/{organization}', [AdminController::class, 'updateOrganization'])->name('supply-office.organizations.update');
        Route::post('/organizations/{organization}/memberships', [AdminController::class, 'storeOrganizationMembership'])->name('supply-office.organizations.memberships.store');
        Route::put('/organization-memberships/{membership}', [AdminController::class, 'updateOrganizationMembership'])->name('supply-office.organizations.memberships.update');
        Route::get('/audit-logs', [AdminController::class, 'auditLogs'])->name('supply-office.audit-logs');
        Route::put('/users/{user}', [AdminController::class, 'updateUser'])->name('supply-office.users.update');
        Route::delete('/users/{user}', [AdminController::class, 'destroyUser'])->name('supply-office.users.destroy');
        Route::post('/users/{user}/reactivate', [AdminController::class, 'reactivateUser'])->name('supply-office.users.reactivate');
        Route::get('/export',  [AdminController::class, 'export'])->name('supply-office.export');
    });
    Route::get('/usage-reports', [SupplyOfficeController::class, 'usageReports'])->name('supply-office.usage-reports');
    Route::get('/calendar', [CalendarController::class, 'index'])->name('supply-office.calendar');
    Route::post('/update',  [SupplyOfficeController::class, 'update'])->name('supply-office.update');
    Route::post('/requests/needs-revision', [SupplyOfficeController::class, 'needsRevision'])->name('supply-office.requests.needs-revision');
    Route::post('/requests/revise', [SupplyOfficeController::class, 'reviseReservation'])->name('supply-office.requests.revise');
    Route::get('/priority-override/confirm', [SupplyOfficeController::class, 'confirmPriorityOverride'])->name('supply-office.priority-override.confirm');
    Route::post('/priority-override/confirm', [SupplyOfficeController::class, 'submitPriorityOverride'])->name('supply-office.priority-override.submit');
    Route::post('/delete',  [SupplyOfficeController::class, 'destroy'])->name('supply-office.destroy');
    Route::get('/export',   [SupplyOfficeController::class, 'export'])->name('supply-office.export');
});

// Legacy admin URL compatibility aliases. These keep old route names working while the
// underlying UI and permissions remain owned by the Supply Office module.
Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('admin.index');
    Route::get('/final-approval', [AdminController::class, 'finalApproval'])->name('admin.final-approval');
    Route::get('/users', [AdminController::class, 'users'])->name('admin.users');
    Route::get('/reports', [AdminController::class, 'reports'])->name('admin.reports');
    Route::get('/settings', [AdminController::class, 'settings'])->name('admin.settings');
    Route::post('/settings/profile', [AdminController::class, 'updateProfile'])->name('admin.settings.profile');
    Route::post('/settings/password', [AdminController::class, 'updatePassword'])->name('admin.settings.password');
    Route::post('/settings/notifications', [AdminController::class, 'updateNotificationPreferences'])->name('admin.settings.notifications');
    Route::get('/calendar', [AdminController::class, 'calendar'])->name('admin.calendar');
    Route::get('/audit-logs', [AdminController::class, 'auditLogs'])->name('admin.audit-logs');
    Route::post('/update', [AdminController::class, 'update'])->name('admin.update');
    Route::post('/delete', [AdminController::class, 'destroy'])->name('admin.destroy');
    Route::middleware('role:admin')->group(function (): void {
        Route::get('/users', [AdminController::class, 'users'])->name('admin.users');
        Route::post('/users', [AdminController::class, 'storeUser'])->name('admin.users.store');
        Route::put('/users/{user}', [AdminController::class, 'updateUser'])->name('admin.users.update');
        Route::delete('/users/{user}', [AdminController::class, 'destroyUser'])->name('admin.users.destroy');
        Route::post('/users/{user}/reactivate', [AdminController::class, 'reactivateUser'])->name('admin.users.reactivate');
    });
    Route::get('/export', [AdminController::class, 'export'])->name('admin.export');
});

// Custodian
Route::middleware(['auth', 'role:custodian'])->prefix('custodian')->group(function () {
    Route::get('/',        fn () => redirect()->route('custodian.index'));
    Route::get('/dashboard',        [CustodianController::class, 'index'])->name('custodian.index');
    Route::get('/assignments', [CustodianController::class, 'assignments'])->name('custodian.assignments');
    Route::get('/venue', [CustodianController::class, 'venueManagement'])->name('custodian.venue');
    Route::get('/equipment', [CustodianController::class, 'equipmentManagement'])->name('custodian.equipment');
    Route::get('/settings', [CustodianController::class, 'settings'])->name('custodian.settings');
    Route::post('/settings/profile', [CustodianController::class, 'updateProfile'])->name('custodian.settings.profile');
    Route::post('/settings/password', [CustodianController::class, 'updatePassword'])->name('custodian.settings.password');
    Route::post('/settings/notifications', [CustodianController::class, 'updateNotificationPreferences'])->name('custodian.settings.notifications');
    Route::post('/settings/signature', [CustodianController::class, 'updateSignature'])->name('custodian.settings.signature');
    Route::post('/update', [CustodianController::class, 'update'])->name('custodian.update');
    Route::get('/equipment/availability', [RequestorController::class, 'equipmentAvailability'])->name('custodian.equipment.availability');
    Route::post('/facility-request/{id}/return', [CustodianController::class, 'returnEquipment'])->name('custodian.return');
    Route::post('/venues', [CustodianController::class, 'storeVenue'])->name('custodian.venues.store');
    Route::put('/venues/{venue}', [CustodianController::class, 'updateVenue'])->name('custodian.venues.update');
    Route::patch('/venues/{venue}/toggle', [CustodianController::class, 'toggleVenue'])->name('custodian.venues.toggle');
    Route::post('/equipment', [CustodianController::class, 'storeEquipment'])->name('custodian.equipment.store');
    Route::put('/equipment/{equipment}', [CustodianController::class, 'updateEquipment'])->name('custodian.equipment.update');
    Route::patch('/equipment/{equipment}/toggle', [CustodianController::class, 'toggleEquipment'])->name('custodian.equipment.toggle');
    Route::post('/equipment/{equipment}/report-issue', [CustodianController::class, 'reportEquipmentIssue'])->name('custodian.equipment.report-issue');
    Route::post('/equipment/{equipment}/return', [CustodianController::class, 'submitEquipmentReturn'])->name('custodian.equipment.return');
});

// Notifications
Route::middleware('auth')->group(function () {
    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index'])
         ->name('notifications.index');
    Route::post('/notifications/{id}/read', [\App\Http\Controllers\NotificationController::class, 'markAsRead'])
         ->name('notifications.read');
    Route::get('/user/{user}/signature', [RequestorController::class, 'accountSignature'])->name('user.signature');
    Route::get('/request/{id}', [RequestorController::class, 'show'])->name('request.show');
    Route::get('/request/{id}/print', [RequestorController::class, 'print'])->name('request.print');
    Route::get('/request/{id}/proposal', [RequestorController::class, 'proposal'])->name('request.proposal');
    Route::get('/request/{id}/proposal/download', [RequestorController::class, 'proposalDownload'])->name('request.proposal.download');
    Route::get('/request/{id}/signature', [RequestorController::class, 'signature'])->name('request.signature');
    Route::get('/request/{facilityRequest}/approval-signature/{type}', [RequestorController::class, 'approvalSignature'])->name('request.approval.signature');
    Route::post('/request/{facilityRequest}/cancel', [RequestActionController::class, 'cancel'])->name('request.cancel');
    Route::post('/request/{facilityRequest}/custodian/verify', [RequestActionController::class, 'custodianVerify'])->name('request.custodian.verify');
    Route::post('/request/{facilityRequest}/custodian/reject', [RequestActionController::class, 'custodianReject'])->name('request.custodian.reject');
    Route::post('/request/{facilityRequest}/custodian/revision', [RequestActionController::class, 'custodianRequestRevision'])->name('request.custodian.revision');
    Route::post('/request/{facilityRequest}/supply/final-approval', [RequestActionController::class, 'supplyFinalApproval'])->name('request.supply.final-approval');
    Route::post('/request/{facilityRequest}/supply/decline', [RequestActionController::class, 'supplyDecline'])->name('request.supply.decline');
});

// Calendar
Route::get('/calendar/events', [CalendarController::class, 'getEvents'])->name('calendar.events');

Route::middleware('auth')->group(function() {
    Route::post('/calendar/return/{id}', [CustodianController::class, 'returnEquipment'])->name('calendar.return');
    Route::post('/calendar/approve/{id}', [CalendarController::class, 'approveRequest'])->name('calendar.approve');
    Route::post('/calendar/reject/{facility_request}', [FacilityRequestApiController::class, 'reject'])->name('calendar.reject');
    Route::post('/calendar/check-conflicts', [CalendarController::class, 'checkConflicts'])
        ->middleware('role:admin')
        ->name('calendar.check-conflicts');
});
