<?php
namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\FacilityRequest;
use App\Models\User;
use App\Models\StudentOrganization;
use App\Models\StudentOrganizationMember;
use App\Models\College;
use App\Models\Department;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Http\Controllers\Concerns\ManagesAccountSettings;

class AdminController extends Controller
{
    use ManagesAccountSettings;

    public function index(Request $request)
    {
        return app(SupplyOfficeController::class)->index($request);
    }

    public function update(Request $request)
    {
        return app(SupplyOfficeController::class)->update($request);
    }

    public function destroy(Request $request)
    {
        return redirect()->route('supply-office.index')
            ->withErrors(['id' => 'Requests cannot be permanently deleted. Use Cancel Request where applicable.']);
    }

    public function finalApproval(Request $request)
    {
        return app(SupplyOfficeController::class)->finalApprovalRequests($request);
    }

    public function users(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $query = User::query()
            ->whereNotIn('role', ['admin', 'supply_office'])
            ->orderBy('surname')
            ->orderBy('first_name')
            ->orderBy('middle_name');
        if ($search !== '') {
            $term = '%' . mb_strtolower($search) . '%';
            $query->where(function ($innerQuery) use ($term) {
                $innerQuery->whereRaw('LOWER(COALESCE(name, "")) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(surname, "")) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(first_name, "")) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(middle_name, "")) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(suffix, "")) LIKE ?', [$term]);
            });
        }

        $users = $query->get();
        $editUserId = (int) $request->get('edit_user', 0);

        return view('supply-office.users', [
            'users' => $users,
            'editUserId' => $editUserId,
            'showAddUser' => $request->boolean('add_user'),
            'colleges' => College::with('departments')->orderBy('name')->get(),
            'searchQuery' => $search,
        ]);
    }

    public function organizations()
    {
        return view('supply-office.organizations', [
            'organizations' => StudentOrganization::with('memberships.user')->orderBy('name')->get(),
            'students' => User::where('requestor_type', 'student')->orderBy('name')->get(),
        ]);
    }

    public function storeOrganization(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191', 'unique:student_organizations,name'],
            'acronym' => ['nullable', 'string', 'max:50'],
            'college_id' => ['nullable', 'exists:colleges,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'organization_type' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'adviser' => ['nullable', 'string', 'max:191'],
        ]);

        StudentOrganization::create($validated + ['is_active' => true]);

        return back()->with('success', 'Student organization created.');
    }

    public function updateOrganization(Request $request, StudentOrganization $organization)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191', 'unique:student_organizations,name,' . $organization->id],
            'acronym' => ['nullable', 'string', 'max:50'],
            'college_id' => ['nullable', 'exists:colleges,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'organization_type' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'adviser' => ['nullable', 'string', 'max:191'],
            'is_active' => ['required', 'boolean'],
        ]);

        $organization->update($validated);

        return back()->with('success', 'Student organization updated.');
    }

    public function storeOrganizationMembership(Request $request, StudentOrganization $organization)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'membership_role' => ['required', 'string', 'max:100'],
            'can_submit_requests' => ['sometimes', 'boolean'],
        ]);
        $student = User::findOrFail($validated['user_id']);
        abort_unless($student->isStudent(), 422, 'Only Student accounts may be organization members.');

        StudentOrganizationMember::updateOrCreate(
            ['user_id' => $student->id, 'student_organization_id' => $organization->id],
            ['membership_role' => $validated['membership_role'], 'can_submit_requests' => $request->boolean('can_submit_requests'), 'is_active' => true],
        );

        return back()->with('success', 'Organization membership saved.');
    }

    public function updateOrganizationMembership(Request $request, StudentOrganizationMember $membership)
    {
        $validated = $request->validate([
            'membership_role' => ['required', 'string', 'max:100'],
            'can_submit_requests' => ['sometimes', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ]);
        $membership->update([
            'membership_role' => $validated['membership_role'],
            'can_submit_requests' => $request->boolean('can_submit_requests'),
            'is_active' => $validated['is_active'],
        ]);

        return back()->with('success', 'Organization membership updated.');
    }

    private function resolvePreferredStudentOrganizationId(?int $collegeId, ?int $departmentId, ?int $selectedId = null): ?int
    {
        if ($selectedId) {
            return $selectedId;
        }

        $query = StudentOrganization::query()->where('is_active', true);

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        } elseif ($collegeId) {
            $query->where('college_id', $collegeId);
        }

        return $query->orderBy('name')->value('id');
    }

    public function storeUser(Request $request)
    {
        $validated = $request->validate([
            'account_type' => ['required', 'in:student,outsider,faculty'],
            'surname' => ['nullable', 'string', 'max:100'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:50'],
            'username' => ['required', 'email', 'max:255', 'unique:users,username'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'college_id' => ['required_if:account_type,student,faculty', 'nullable', 'exists:colleges,id'],
            'department_id' => ['required_if:account_type,student,faculty', 'nullable', 'exists:departments,id'],
            'school_id_number' => ['required_if:account_type,student', 'nullable', 'string', 'regex:/^\d{2}-\d{4}-\d{3}$/'],
            'faculty_id' => ['required_if:account_type,faculty', 'nullable', 'string', 'max:50', 'unique:users,faculty_id'],
            'faculty_adviser' => ['nullable', 'in:yes,no'],
            'position' => ['nullable', 'string', 'max:100'],
            'student_organization_id' => ['nullable', 'integer', 'exists:student_organizations,id'],
            'office_or_organization' => ['required_if:account_type,outsider', 'nullable', 'string', 'max:191'],
            'contact_number' => ['nullable', 'string', 'max:50'],
        ], [
            'school_id_number.regex' => 'Student ID must be in format: 23-0098-635 (2 digits - 4 digits - 3 digits).',
            'office_or_organization.required_if' => 'Organization name is required for this account type.',
        ]);

        $facultyAdviser = $request->input('faculty_adviser') === 'yes';

        if ($validated['account_type'] === 'student' && empty($validated['student_organization_id'])) {
            $validated['student_organization_id'] = $this->resolvePreferredStudentOrganizationId(
                $validated['college_id'] ?? null,
                $validated['department_id'] ?? null,
            );
        }

        if ($validated['account_type'] === 'faculty' && $facultyAdviser && empty($validated['student_organization_id'])) {
            $validated['student_organization_id'] = $this->resolvePreferredStudentOrganizationId(
                $validated['college_id'] ?? null,
                $validated['department_id'] ?? null,
            );
        }

        if ($validated['account_type'] === 'faculty' && $facultyAdviser && empty($validated['student_organization_id'])) {
            return back()->withErrors(['student_organization_id' => 'Please select the student organization this faculty adviser belongs to.'])->withInput();
        }

        $fullName = User::formatFullName(
            $validated['surname'] ?? null,
            $validated['first_name'] ?? null,
            $validated['middle_name'] ?? null,
            $validated['suffix'] ?? null,
        );
        if (trim((string) $fullName) === '') {
            $fullName = preg_replace('/[^A-Za-z0-9. _-]+/', '', (string) ($validated['username'] ?? ''));
            $fullName = trim((string) $fullName);
            if ($fullName === '') {
                $fullName = 'New User';
            }
        }

        $department = null;
        if (!empty($validated['department_id'])) {
            $department = Department::find($validated['department_id']);
            if ($department && (int) $department->college_id !== (int) $validated['college_id']) {
                return back()->withErrors(['department_id' => 'Please select a department under the selected college.'])
                    ->withInput();
            }
        }

        $isStudent = $validated['account_type'] === 'student';
        $isAcademic = in_array($validated['account_type'], ['student', 'faculty'], true);
        $position = trim((string) ($validated['position'] ?? ''));

        $createdUser = User::create([
            'username' => strtolower(trim($validated['username'])),
            'password' => Hash::make($validated['password']),
            'name' => $fullName,
            'surname' => trim((string) ($validated['surname'] ?? '')) ?: null,
            'first_name' => trim((string) ($validated['first_name'] ?? '')) ?: null,
            'middle_name' => trim((string) ($validated['middle_name'] ?? '')) ?: null,
            'suffix' => trim((string) ($validated['suffix'] ?? '')) ?: null,
            'role' => 'requestor',
            'requestor_type' => match ($validated['account_type']) {
                'student' => 'student',
                'faculty' => 'faculty',
                default => 'outsider',
            },
            'school_id_number' => $isStudent ? $validated['school_id_number'] : null,
            'faculty_id' => $validated['account_type'] === 'faculty' ? $validated['faculty_id'] : null,
            'position' => $position !== '' ? $position : match ($validated['account_type']) {
                'student' => 'Student',
                'faculty' => 'Faculty',
                'outsider' => 'External Partner',
                default => null,
            },
            'office_or_organization' => !$isStudent ? ($validated['office_or_organization'] ?? null) : null,
            'contact_number' => $validated['contact_number'] ?? null,
            'department' => $isAcademic ? $department?->name : null,
            'college_id' => $isAcademic ? $validated['college_id'] : null,
            'department_id' => $isAcademic ? $validated['department_id'] : null,
            'email_verified_at' => now(),
        ]);

        if ($isStudent && !empty($validated['student_organization_id'])) {
            StudentOrganizationMember::updateOrCreate(
                ['user_id' => $createdUser->id, 'student_organization_id' => $validated['student_organization_id']],
                ['membership_role' => 'Member', 'can_submit_requests' => true, 'is_active' => true],
            );
        }

        if ($validated['account_type'] === 'faculty' && $facultyAdviser && !empty($validated['student_organization_id'])) {
            StudentOrganizationMember::updateOrCreate(
                ['user_id' => $createdUser->id, 'student_organization_id' => $validated['student_organization_id']],
                ['membership_role' => 'Adviser', 'can_submit_requests' => false, 'is_active' => true],
            );
        }

        $this->recordUserAudit(Auth::user(), $createdUser, 'user_created', 'Created a new user account.', [], [
            'name' => $createdUser->name,
            'username' => $createdUser->username,
            'role' => $createdUser->role,
            'requestor_type' => $createdUser->requestor_type,
        ]);

        return redirect()->route('admin.users')->with('success', 'User account created successfully.');
    }

    public function updateUser(Request $request, User $user)
    {
        $currentUser = Auth::user();
        if ($currentUser && $currentUser->id === $user->id) {
            return redirect()->route('admin.users')->with('error', 'You cannot change your own admin account.');
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'surname' => ['nullable', 'string', 'max:100'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:50'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username,' . $user->id],
            'role' => ['required', 'in:requestor,student,faculty,outsider,custodian,custodian-venue,custodian-equipment,admin,supply_office'],
            'is_active' => ['sometimes', 'boolean'],
            'department' => ['nullable', 'string', 'max:255'],
            'requestor_type' => ['nullable', 'in:student,faculty,outsider'],
            'school_id_number' => ['nullable', 'string', 'max:255'],
            'faculty_id' => ['nullable', 'string', 'max:50', 'unique:users,faculty_id,' . $user->id],
            'faculty_adviser' => ['nullable', 'in:yes,no'],
            'position' => ['nullable', 'string', 'max:100'],
            'student_organization_id' => ['nullable', 'integer', 'exists:student_organizations,id'],
            'office_or_organization' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:255'],
        ]);

        $facultyAdviser = $request->input('faculty_adviser') === 'yes';
        $selectedOrganizationId = (int) ($validated['student_organization_id'] ?? 0);
        if ($user->isFaculty() && $facultyAdviser && empty($selectedOrganizationId)) {
            $selectedOrganizationId = (int) $this->resolvePreferredStudentOrganizationId($user->college_id, $user->department_id, null) ?: 0;
        }

        if ($user->isFaculty() && $facultyAdviser && $selectedOrganizationId <= 0) {
            return redirect()->route('admin.users')->withErrors(['student_organization_id' => 'Faculty advisers must be linked to a student organization.'])->withInput();
        }

        $explicitName = trim((string) ($validated['name'] ?? ''));
        $nameToStore = $explicitName !== ''
            ? $explicitName
            : User::formatFullName(
                $validated['surname'] ?? null,
                $validated['first_name'] ?? null,
                $validated['middle_name'] ?? null,
                $validated['suffix'] ?? null,
            );

        $previousRole = $user->role;
        $newRole = $validated['role'];
        $adminRoles = ['admin', 'supply_office'];

        if ($currentUser && in_array($currentUser->role, $adminRoles, true) && in_array($newRole, $adminRoles, true)) {
            return redirect()->route('admin.users')->with('error', 'Admin role assignment is restricted. Please keep the existing privilege model unchanged.');
        }

        $previouslyCustodian = in_array($previousRole, ['custodian', 'custodian-venue', 'custodian-equipment'], true);
        $newlyCustodian = in_array($newRole, ['custodian', 'custodian-venue', 'custodian-equipment'], true);

        if ($previouslyCustodian && ! $newlyCustodian) {
            $venueAssignments = $user->venues()->where('is_active', true)->pluck('name')->filter()->all();
            $equipmentAssignments = $user->equipmentItems()->where('is_active', true)->pluck('name')->filter()->all();
            $affected = array_values(array_filter(array_merge($venueAssignments, $equipmentAssignments), fn ($value) => is_string($value) && trim($value) !== ''));

            if ($affected !== []) {
                $names = implode(', ', $affected);
                return redirect()->route('admin.users')->with('error', 'This user is still assigned as custodian for active resources (' . $names . '). Reassign those items before changing the role.');
            }
        }

        $oldValues = [
            'name' => $user->name,
            'username' => $user->username,
            'role' => $user->role,
            'requestor_type' => $user->requestor_type,
            'is_active' => $user->is_active,
        ];

        $user->fill([
            'name' => $nameToStore,
            'surname' => trim((string) ($validated['surname'] ?? '')) ?: null,
            'first_name' => trim((string) ($validated['first_name'] ?? '')) ?: null,
            'middle_name' => trim((string) ($validated['middle_name'] ?? '')) ?: null,
            'suffix' => trim((string) ($validated['suffix'] ?? '')) ?: null,
            'username' => strtolower(trim($validated['username'])),
            'role' => $newRole,
            'is_active' => $request->boolean('is_active', $user->is_active ?? true),
            'department' => $validated['department'] ?? null,
            'requestor_type' => $validated['requestor_type'] ?? $user->requestor_type,
            'school_id_number' => $validated['school_id_number'] ?? null,
            'faculty_id' => $validated['faculty_id'] ?? null,
            'position' => $validated['position'] ?? null,
            'office_or_organization' => $validated['office_or_organization'] ?? null,
            'contact_number' => $validated['contact_number'] ?? null,
        ]);
        $user->save();

        if ($user->isStudent() && !empty($validated['student_organization_id'])) {
            StudentOrganizationMember::updateOrCreate(
                ['user_id' => $user->id, 'student_organization_id' => $validated['student_organization_id']],
                ['membership_role' => 'Member', 'can_submit_requests' => true, 'is_active' => true],
            );
        }

        if ($user->isFaculty() && $facultyAdviser && $selectedOrganizationId > 0) {
            StudentOrganizationMember::updateOrCreate(
                ['user_id' => $user->id, 'student_organization_id' => $selectedOrganizationId],
                ['membership_role' => 'Adviser', 'can_submit_requests' => false, 'is_active' => true],
            );
        }

        $this->recordUserAudit($currentUser, $user, 'user_updated', 'Updated user account details.', $oldValues, [
            'name' => $user->name,
            'username' => $user->username,
            'role' => $user->role,
            'requestor_type' => $user->requestor_type,
            'is_active' => $user->is_active,
        ]);

        return redirect()->route('admin.users')->with('success', 'User updated successfully.');
    }

    public function destroyUser(User $user)
    {
        $currentUser = Auth::user();
        if ($currentUser && $currentUser->id === $user->id) {
            return redirect()->route('admin.users')->with('error', 'You cannot deactivate your own account.');
        }

        $venueAssignments = $user->venues()->where('is_active', true)->pluck('name')->filter()->all();
        $equipmentAssignments = $user->equipmentItems()->where('is_active', true)->pluck('name')->filter()->all();
        $affected = array_values(array_filter(array_merge($venueAssignments, $equipmentAssignments), fn ($value) => is_string($value) && trim($value) !== ''));

        if ($affected !== []) {
            $names = implode(', ', $affected);
            return redirect()->route('admin.users')->with('error', 'This user has active custodian assignments (' . $names . '). Reassign them before deactivating the account.');
        }

        $oldValues = [
            'is_active' => $user->is_active,
        ];

        $user->update(['is_active' => false]);

        $this->recordUserAudit($currentUser, $user, 'user_deactivated', 'Deactivated the user account.', $oldValues, [
            'is_active' => $user->fresh()->is_active,
        ]);

        return redirect()->route('admin.users')->with('success', 'User deactivated successfully.');
    }

    public function reactivateUser(User $user)
    {
        $oldValues = [
            'is_active' => $user->is_active,
        ];

        $user->update(['is_active' => true]);

        $this->recordUserAudit(Auth::user(), $user, 'user_reactivated', 'Reactivated a deactivated user account.', $oldValues, [
            'is_active' => $user->fresh()->is_active,
        ]);

        return redirect()->route('admin.users')->with('success', 'User reactivated successfully.');
    }

    public function reports(Request $request)
    {
        return app(SupplyOfficeController::class)->usageReports($request);
    }

    public function settings(Request $request)
    {
        $user = Auth::user();

        return view('supply-office.settings', [
            'user' => $user,
            'appName' => config('app.name'),
            'appEnv' => config('app.env'),
            'appUrl' => config('app.url'),
            'maintenanceMode' => app()->isDownForMaintenance(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:255'],
        ]);

        $user->fill($validated);
        $user->save();

        return redirect()->route('supply-office.settings')->with('success', 'Profile updated successfully.');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user = Auth::user();
        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Your current password is incorrect.']);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return redirect()->route('supply-office.settings')->with('success', 'Password updated successfully.');
    }

    public function updateNotificationPreferences(Request $request)
    {
        $route = $request->routeIs('admin.*') ? 'admin.settings' : 'supply-office.settings';

        return $this->saveNotificationPreferences($request, $route);
    }

    public function calendar(Request $request)
    {
        $requests = FacilityRequest::where('venue_status', 'approved')
            ->where('equipment_status', 'approved')
            ->orderBy('start_date')
            ->get();

        $calendarItems = [];
        foreach ($requests as $req) {
            $start = $req->start_date;
            $end = $req->end_date ?: $req->start_date;

            $current = $start->copy();
            while ($current->lte($end)) {
                $key = $current->toDateString();
                $calendarItems[$key][] = $req;
                $current->addDay();
            }
        }

        return view('supply-office.calendar', [
            'calendarItems' => $calendarItems,
        ]);
    }

    public function auditLogs(Request $request)
    {
        $requestHistoryQuery = \App\Models\RequestHistory::with(['facilityRequest', 'user'])
            ->orderByDesc('occurred_at');

        if ($search = $request->get('search')) {
            $requestHistoryQuery->where(function ($q) use ($search) {
                $q->where('action', 'like', '%' . $search . '%')
                  ->orWhere('detail', 'like', '%' . $search . '%')
                  ->orWhereHas('facilityRequest', function ($fr) use ($search) {
                      $fr->where('control_number', 'like', '%' . $search . '%')
                         ->orWhere('name_of_activity', 'like', '%' . $search . '%');
                  })
                  ->orWhereHas('user', function ($u) use ($search) {
                      $u->where('name', 'like', '%' . $search . '%');
                  });
            });
        }

        if ($action = $request->get('action')) {
            $requestHistoryQuery->where('action', $action);
        }

        if ($dateFrom = $request->get('date_from')) {
            $requestHistoryQuery->whereDate('occurred_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->get('date_to')) {
            $requestHistoryQuery->whereDate('occurred_at', '<=', $dateTo);
        }

        $requestLogs = $requestHistoryQuery->get();

        $userAuditLogs = AuditLog::with(['actor', 'targetUser'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (AuditLog $log) {
                $log->kind = 'user_management';
                $log->occurred_at = $log->created_at;
                $log->user = $log->actor;
                $log->facilityRequest = null;
                $log->detail = $log->details;
                return $log;
            });

        $allLogs = $requestLogs->concat($userAuditLogs)
            ->sortByDesc(fn ($log) => $log->occurred_at ? $log->occurred_at->toDateTimeString() : $log->created_at?->toDateTimeString())
            ->values();

        $page = (int) $request->query('page', 1);
        $perPage = 50;
        $items = $allLogs->slice(($page - 1) * $perPage, $perPage)->values();
        $auditLogs = new LengthAwarePaginator(
            $items,
            $allLogs->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('supply-office.audit-logs', [
            'auditLogs' => $auditLogs,
            'filters' => $request->only(['search', 'action', 'date_from', 'date_to']),
        ]);
    }

    protected function recordUserAudit(?User $actor, ?User $targetUser, string $action, string $details = '', array $oldValues = [], array $newValues = []): void
    {
        AuditLog::create([
            'actor_id' => $actor?->id,
            'target_user_id' => $targetUser?->id,
            'action' => $action,
            'details' => $details,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }
}