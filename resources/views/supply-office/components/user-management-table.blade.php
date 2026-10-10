<div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
    <div class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="text-lg font-semibold text-slate-900">User Management</h3>
            <p class="mt-1 text-sm text-slate-500">Review and deactivate system users.</p>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm text-slate-600">
            <thead class="border-b border-slate-200 text-slate-500">
                <tr>
                    <th class="px-4 py-3 font-medium">Name</th>
                    <th class="px-4 py-3 font-medium">Username</th>
                    <th class="px-4 py-3 font-medium">Role</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium">Department</th>
                    <th class="px-4 py-3 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($users as $user)
                    <tr>
                        <td class="px-4 py-4 font-medium text-slate-900">{{ \App\Models\User::formatFullName($user->surname, $user->first_name, $user->middle_name, in_array(strtolower((string) $user->suffix), ['n/a', 'na', 'none'], true) ? null : $user->suffix) ?: $user->name }}</td>
                        <td class="px-4 py-4">{{ $user->username }}</td>
                        <td class="px-4 py-4">{{ $user->account_type_label }}</td>
                        <td class="px-4 py-4">{{ $user->is_active === false ? 'Deactivated' : 'Active' }}</td>
                        <td class="px-4 py-4">{{ $user->department ?? 'N/A' }}</td>
                        <td class="px-4 py-4 text-right">
                            <a href="{{ route('supply-office.users', ['edit_user' => $user->id]) }}" class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">Edit</a>
                            @if($user->is_active)
                                <form method="POST" action="{{ route('supply-office.users.destroy', $user) }}" class="inline-block" data-swal-confirm data-swal-title="Deactivate this user?" data-swal-text="The account will be retained for historical records and can be reactivated by an administrator." data-swal-confirm-text="Deactivate" data-swal-confirm-color="#dc2626">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-full border border-red-200 bg-red-50 px-3 py-1 text-xs font-semibold text-red-700 hover:bg-red-100">Deactivate</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('supply-office.users.reactivate', $user) }}" class="inline-block">
                                    @csrf
                                    <button type="submit" class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-100">Reactivate</button>
                                </form>
                            @endif
                        </td>
                    </tr>

                    @if($editUserId === $user->id)
                        <tr>
                            <td colspan="6" class="p-0">
                                <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-950/65 p-3 backdrop-blur-sm sm:p-6" role="dialog" aria-modal="true" aria-labelledby="edit-user-title-{{ $user->id }}">
                                    <div class="my-auto max-h-[calc(100vh-1.5rem)] w-full max-w-4xl overflow-y-auto rounded-2xl bg-slate-50 shadow-2xl ring-1 ring-slate-900/10 sm:max-h-[calc(100vh-3rem)]">
                                        <div class="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-slate-200 bg-white/95 px-5 py-4 backdrop-blur sm:px-7">
                                            <div>
                                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-sky-700">User management</p>
                                                <h2 id="edit-user-title-{{ $user->id }}" class="mt-1 text-xl font-bold text-slate-950">Edit user account</h2>
                                                <p class="mt-1 text-sm text-slate-600">Update the account details that apply to this user's role.</p>
                                            </div>
                                            <a href="{{ route('supply-office.users') }}" class="rounded-xl border border-slate-200 bg-white p-2 text-xl leading-none text-slate-500 transition hover:bg-slate-100 hover:text-slate-800" aria-label="Close edit user dialog">&times;</a>
                                        </div>
                                <form method="POST" action="{{ route('supply-office.users.update', $user) }}" class="space-y-4 p-4 sm:p-6">
                                    @csrf
                                    @method('PUT')
                                    <section class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
                                        <div class="mb-4">
                                            <h3 class="text-sm font-semibold text-slate-900">Name</h3>
                                            <p class="mt-1 text-xs text-slate-500">Enter the name this person uses for their account.</p>
                                        </div>
                                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                            <label class="block text-sm font-medium text-slate-700">First name
                                                <input type="text" name="first_name" value="{{ old('first_name', $user->first_name) }}" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" autocomplete="given-name">
                                            </label>
                                            <label class="block text-sm font-medium text-slate-700">Middle name <span class="font-normal text-slate-400">(optional)</span>
                                                <input type="text" name="middle_name" value="{{ old('middle_name', $user->middle_name) }}" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" autocomplete="additional-name">
                                            </label>
                                            <label class="block text-sm font-medium text-slate-700">Surname
                                                <input type="text" name="surname" value="{{ old('surname', $user->surname) }}" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" autocomplete="family-name">
                                            </label>
                                            <label class="block text-sm font-medium text-slate-700">Suffix
                                                <select name="suffix" class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                                                    @foreach(['' => 'No suffix', 'Jr.' => 'Jr.', 'Sr.' => 'Sr.', 'II' => 'II', 'III' => 'III', 'IV' => 'IV', 'V' => 'V'] as $value => $label)
                                                        <option value="{{ $value }}" @selected(old('suffix', $user->suffix) === $value || ($value === '' && in_array(strtolower((string) old('suffix', $user->suffix)), ['n/a', 'na', 'none'], true)))>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                        </div>
                                    </section>

                                    <section class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
                                        <div class="mb-4">
                                            <h3 class="text-sm font-semibold text-slate-900">Sign-in and role</h3>
                                            <p class="mt-1 text-xs text-slate-500" data-edit-role-help>Choose an account role to show only the details that apply.</p>
                                        </div>
                                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                            <label class="block text-sm font-medium text-slate-700">Email address
                                                <input type="email" name="username" value="{{ old('username', $user->username) }}" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" autocomplete="email" required>
                                            </label>
                                            <label class="block text-sm font-medium text-slate-700">System role
                                                @if(in_array($user->role, ['facility_admin', 'admin', 'supply_office'], true))
                                                    <input type="hidden" name="role" value="{{ $user->role }}">
                                                    <div class="mt-1.5 rounded-xl border border-slate-300 bg-slate-100 px-3 py-2.5 text-sm text-slate-700">
                                                        Protected administrator account
                                                    </div>
                                                    <span class="mt-1 block text-xs font-normal text-slate-500">The system role and access for this account cannot be changed here.</span>
                                                @else
                                                    <select name="role" class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm" required>
                                                        @foreach([
                                                            'requestor' => 'Requestor',
                                                            'custodian-venue' => 'Venue Custodian',
                                                            'custodian-equipment' => 'Equipment Custodian',
                                                        ] as $value => $label)
                                                            <option value="{{ $value }}" @selected(old('role', $user->role) === $value)>{{ $label }}</option>
                                                        @endforeach
                                                        @if(in_array($user->role, ['student', 'faculty', 'outsider', 'custodian'], true))
                                                            <option value="{{ $user->role }}" @selected(old('role', $user->role) === $user->role)>{{ [
                                                                'student' => 'Student (existing legacy role)',
                                                                'faculty' => 'Faculty (existing legacy role)',
                                                                'outsider' => 'Outsider (existing legacy role)',
                                                                'custodian' => 'Custodian (existing legacy role)',
                                                            ][$user->role] }}</option>
                                                        @endif
                                                    </select>
                                                    <span class="mt-1 block text-xs font-normal text-slate-500">Choose Requestor for someone who submits requests, then select their category below. Choose a custodian role only for venue or equipment custodians.</span>
                                                @endif
                                            </label>
                                            <label data-edit-field="requestor-type" class="block text-sm font-medium text-slate-700">
                                                <span>Requestor category</span>
                                                <select name="requestor_type" class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                                                    <option value="">Choose a category</option>
                                                    @foreach(['student' => 'Student', 'faculty' => 'Faculty', 'staff' => 'Staff', 'outsider' => 'Outsider'] as $value => $label)
                                                        <option value="{{ $value }}" @selected(old('requestor_type', $user->requestor_type) === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                            <label data-edit-field="student" class="hidden block text-sm font-medium text-slate-700">Student ID
                                                <input type="text" name="school_id_number" value="{{ old('school_id_number', $user->school_id_number) }}" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                                            </label>
                                            <label data-edit-field="academic" class="hidden block text-sm font-medium text-slate-700">College
                                                <select name="college_id" class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                                                    <option value="">Not assigned</option>
                                                    @foreach($colleges ?? collect() as $college)
                                                        <option value="{{ $college->id }}" @selected(old('college_id', $user->college_id) == $college->id)>{{ $college->name }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                            <label data-edit-field="academic" class="hidden block text-sm font-medium text-slate-700">Department
                                                <select name="department_id" class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                                                    <option value="">Not assigned</option>
                                                    @foreach(($colleges ?? collect())->flatMap->departments as $department)
                                                        <option value="{{ $department->id }}" data-college="{{ $department->college_id }}" @selected(old('department_id', $user->department_id) == $department->id)>{{ $department->name }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                            <label data-edit-field="work" class="hidden block text-sm font-medium text-slate-700">Position or title
                                                <input type="text" name="position" value="{{ old('position', $user->position) }}" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                                            </label>
                                            <label data-edit-field="student-organization" class="hidden block text-sm font-medium text-slate-700">
                                                <span data-edit-organization-label>Student organization</span>
                                                <select name="student_organization_id" class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                                                    <option value="">Not assigned</option>
                                                    @foreach(App\Models\StudentOrganization::query()->where('is_active', true)->orderBy('name')->get() as $organization)
                                                        <option value="{{ $organization->id }}" @selected(old('student_organization_id', $user->organizationMemberships()->where('is_active', true)->value('student_organization_id')) == $organization->id)>{{ $organization->name }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                            <label data-edit-field="workplace" class="hidden block text-sm font-medium text-slate-700">Office or organization
                                                <input type="text" name="office_or_organization" value="{{ old('office_or_organization', $user->office_or_organization) }}" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                                            </label>
                                            <label class="block text-sm font-medium text-slate-700">Contact number
                                                <input type="text" name="contact_number" value="{{ old('contact_number', $user->contact_number) }}" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" autocomplete="tel">
                                            </label>
                                            <label data-edit-field="faculty" class="hidden block text-sm font-medium text-slate-700">Faculty ID
                                                <input type="text" name="faculty_id" value="{{ old('faculty_id', $user->faculty_id) }}" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                                            </label>
                                            <label data-edit-field="faculty-adviser" class="hidden block text-sm font-medium text-slate-700">Advises a student organization
                                                <select name="faculty_adviser" class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                                                    <option value="no" @selected(old('faculty_adviser', $user->organizationMemberships()->where('membership_role', 'Adviser')->where('is_active', true)->exists() ? 'yes' : 'no') === 'no')>No</option>
                                                    <option value="yes" @selected(old('faculty_adviser', $user->organizationMemberships()->where('membership_role', 'Adviser')->where('is_active', true)->exists() ? 'yes' : 'no') === 'yes')>Yes</option>
                                                </select>
                                            </label>
                                        </div>
                                    </section>

                                    <section class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
                                        <div class="mb-4">
                                            <h3 class="text-sm font-semibold text-slate-900">Password and sign-in access</h3>
                                            <p class="mt-1 text-xs text-slate-500">Leave the password fields blank if you do not want to change the password.</p>
                                        </div>
                                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                            <label for="edit-user-password-{{ $user->id }}" class="block text-sm font-medium text-slate-700">New password <span class="font-normal text-slate-400">(optional)</span>
                                                <input id="edit-user-password-{{ $user->id }}" type="password" name="password" autocomplete="new-password" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="At least 12 characters">
                                            </label>
                                            <label for="edit-user-password-confirmation-{{ $user->id }}" class="block text-sm font-medium text-slate-700">Confirm new password
                                                <input id="edit-user-password-confirmation-{{ $user->id }}" type="password" name="password_confirmation" autocomplete="new-password" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="Re-enter new password">
                                            </label>
                                            <label data-edit-field="account-status" class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-medium text-slate-700 sm:col-span-2">
                                                <input type="hidden" name="is_active" value="0">
                                                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active ?? true)) class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                                <span><span class="block">Allow this account to sign in</span><span class="mt-0.5 block text-xs font-normal text-slate-500">Turn this off to deactivate the account.</span></span>
                                            </label>
                                        </div>
                                    </section>

                                    <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-4 sm:flex-row sm:justify-end">
                                        <a href="{{ route('supply-office.users') }}" class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-center text-sm font-semibold text-slate-700 transition hover:bg-slate-100">Cancel</a>
                                        <button type="submit" style="background-color: #0369a1; color: #ffffff;" class="rounded-xl px-6 py-2.5 text-sm font-semibold shadow-sm transition hover:bg-sky-800 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2">Save changes</button>
                                    </div>
                                </form>
                                <script>
                                    (() => {
                                        const form = document.currentScript.previousElementSibling;
                                        const typeField = form?.querySelector('[name="requestor_type"]');
                                        const roleField = form?.querySelector('[name="role"]');
                                        const roleHelp = form?.querySelector('[data-edit-role-help]');
                                        const organizationLabel = form?.querySelector('[data-edit-organization-label]');
                                        const roleLabels = {
                                            student: 'Student account',
                                            faculty: 'Faculty account',
                                            outsider: 'Outsider account',
                                            staff: 'Staff account',
                                            custodian: 'Custodian account',
                                            'custodian-venue': 'Venue Custodian account',
                                            'custodian-equipment': 'Equipment Custodian account',
                                            facility_admin: 'Facility administrator account',
                                            admin: 'Administrator account',
                                            supply_office: 'Supply Office account',
                                        };
                                        const isRequestorRole = role => ['requestor', 'student', 'faculty', 'outsider'].includes(role);
                                        const updateVisibility = () => {
                                            const role = roleField?.value || '';
                                            const type = role === 'requestor'
                                                ? (typeField?.value || '')
                                                : ({ student: 'student', faculty: 'faculty', outsider: 'outsider' }[role] || '');
                                            const isRequestor = isRequestorRole(role);
                                            const isAcademic = isRequestor && ['student', 'faculty'].includes(type);
                                            const isStudent = isRequestor && type === 'student';
                                            const isFaculty = isRequestor && type === 'faculty';
                                            const isStaff = isRequestor && type === 'staff';
                                            const isOutsider = isRequestor && type === 'outsider';
                                            const isPrivileged = ['admin', 'facility_admin', 'supply_office'].includes(role);
                                            const isAdviser = isFaculty && form?.querySelector('[name="faculty_adviser"]')?.value === 'yes';

                                            form?.querySelectorAll('[data-edit-field]').forEach(field => {
                                                const kind = field.dataset.editField;
                                                const visible = {
                                                    'requestor-type': role === 'requestor',
                                                    student: isStudent,
                                                    academic: isAcademic,
                                                    work: isStudent || isFaculty || isStaff,
                                                    'student-organization': isStudent || isAdviser,
                                                    workplace: isStaff || isOutsider,
                                                    faculty: isFaculty,
                                                    'faculty-adviser': isFaculty,
                                                    'account-status': !isPrivileged,
                                                }[kind] ?? true;
                                                field.classList.toggle('hidden', !visible);
                                                field.querySelectorAll('input, select, textarea').forEach(control => {
                                                    control.disabled = !visible;
                                                });
                                            });
                                            if (typeField) {
                                                typeField.required = role === 'requestor';
                                                typeField.disabled = role !== 'requestor';
                                            }
                                            if (roleHelp) {
                                                roleHelp.textContent = role === 'requestor'
                                                    ? (type ? `${type[0].toUpperCase()}${type.slice(1)} requestor account` : 'Choose a requestor category to show the relevant details.')
                                                    : (roleLabels[role] || 'Choose an account role to show only the details that apply.');
                                            }
                                            if (organizationLabel) {
                                                organizationLabel.textContent = isAdviser ? 'Advising organization' : 'Student organization';
                                            }
                                        };
                                        typeField?.addEventListener('change', updateVisibility);
                                        roleField?.addEventListener('change', updateVisibility);
                                        form?.querySelector('[name="faculty_adviser"]')?.addEventListener('change', updateVisibility);
                                        updateVisibility();
                                    })();
                                </script>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center text-sm text-slate-500">No users found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
