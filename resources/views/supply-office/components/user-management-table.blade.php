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
                        <td class="px-4 py-4 font-medium text-slate-900">{{ $user->name }}</td>
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
                            <td colspan="6" class="px-4 py-4 bg-slate-50">
                                <form method="POST" action="{{ route('supply-office.users.update', $user) }}" class="grid grid-cols-1 gap-3 md:grid-cols-5">
                                    @csrf
                                    @method('PUT')
                                    <input type="text" name="name" value="{{ old('name', $user->name) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Name" required>
                                    <input type="text" name="username" value="{{ old('username', $user->username) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Username" required>
                                    <select name="role" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" required>
                                        <option value="requestor" {{ old('role', $user->role) === 'requestor' ? 'selected' : '' }}>Requestor</option>
                                        <option value="student" {{ old('role', $user->role) === 'student' ? 'selected' : '' }}>Student</option>
                                        <option value="faculty" {{ old('role', $user->role) === 'faculty' ? 'selected' : '' }}>Faculty</option>
                                        <option value="outsider" {{ old('role', $user->role) === 'outsider' ? 'selected' : '' }}>Outsider</option>
                                        <option value="custodian" {{ old('role', $user->role) === 'custodian' ? 'selected' : '' }}>Custodian</option>
                                        <option value="admin" {{ old('role', $user->role) === 'admin' ? 'selected' : '' }}>Supply Office</option>
                                    </select>
                                    <input type="text" name="department" value="{{ old('department', $user->department) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Department">
                                    <input type="text" name="requestor_type" value="{{ old('requestor_type', $user->requestor_type) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Requestor Type">
                                    <input type="text" name="school_id_number" value="{{ old('school_id_number', $user->school_id_number) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="School ID">
                                    <input type="text" name="position" value="{{ old('position', $user->position) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Position">
                                    <select name="student_organization_id" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                                        <option value="">Student Org</option>
                                        @foreach(App\Models\StudentOrganization::query()->where('is_active', true)->orderBy('name')->get() as $organization)
                                            <option value="{{ $organization->id }}" @selected(old('student_organization_id', optional($user->studentOrganizations()->first())->id) == $organization->id)>{{ $organization->name }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" name="office_or_organization" value="{{ old('office_or_organization', $user->office_or_organization) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Office / Organization">
                                    <input type="text" name="contact_number" value="{{ old('contact_number', $user->contact_number) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Contact Number">
                                    <label class="flex items-center gap-2 text-sm text-slate-700"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active ?? true))> Active</label>
                                    <button type="submit" class="rounded-xl bg-sky-600 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-700">Save</button>
                                    <a href="{{ route('supply-office.users') }}" class="rounded-xl border border-slate-300 px-3 py-2 text-center text-sm font-semibold text-slate-700 hover:bg-slate-100">Cancel</a>
                                </form>
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
