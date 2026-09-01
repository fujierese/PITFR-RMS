@extends('layouts.app')
@section('title', 'Users')

@section('content')
<div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200/50">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">User Management</h1>
            <p class="mt-1 text-sm text-slate-500">Maintain accounts, roles, and departmental access for the facility request system.</p>
        </div>
        <a href="{{ route('admin.users', ['add_user' => 1]) }}" class="inline-flex items-center gap-2 rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">
            + Add User
        </a>
    </div>

    <form method="GET" action="{{ route('admin.users') }}" class="mb-6 flex flex-col gap-3 sm:flex-row">
        <input type="text" name="search" value="{{ old('search', $searchQuery ?? '') }}" placeholder="Search by name" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
        <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Search</button>
        @if(!empty($searchQuery))
            <a href="{{ route('admin.users') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Clear</a>
        @endif
    </form>

    @if($showAddUser)
        <div class="mb-6 overflow-hidden rounded-2xl border border-emerald-200 bg-emerald-50 shadow-sm">
            <div class="border-b border-emerald-100 bg-emerald-600 px-5 py-4 text-white">
                <h2 class="text-lg font-semibold">Create user account</h2>
                <p class="mt-1 text-sm text-emerald-50">Set the account type first to show only the fields that apply.</p>
            </div>
            <div class="p-5">
            <p class="mb-4 rounded-xl border border-emerald-200 bg-white/80 px-3 py-2 text-sm text-emerald-800">Admin-created accounts are verified immediately and cannot receive admin privileges.</p>
            @if($errors->any())
                <div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700" role="alert">{{ $errors->first() }}</div>
            @endif
            <form method="POST" action="{{ route('admin.users.store') }}" class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2" id="add-user-form">
                @csrf
                <div>
                    <label for="account_type" class="mb-1 block text-sm font-medium text-slate-700">Account type</label>
                    <select id="account_type" name="account_type" required class="w-full rounded-xl border border-emerald-300 bg-white px-3 py-2 text-sm focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                        @foreach(['student' => 'Student', 'outsider' => 'Outsider', 'faculty' => 'Faculty'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('account_type', 'student') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="add-user-surname" class="mb-1 block text-sm font-medium text-slate-700">Surname</label>
                    <input id="add-user-surname" type="text" name="surname" value="{{ old('surname') }}" maxlength="100" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                </div>
                <div>
                    <label for="add-user-first-name" class="mb-1 block text-sm font-medium text-slate-700">First name</label>
                    <input id="add-user-first-name" type="text" name="first_name" value="{{ old('first_name') }}" maxlength="100" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                </div>
                <div>
                    <label for="add-user-middle-name" class="mb-1 block text-sm font-medium text-slate-700">Middle name</label>
                    <input id="add-user-middle-name" type="text" name="middle_name" value="{{ old('middle_name') }}" maxlength="100" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                </div>
                <div>
                    <label for="add-user-suffix" class="mb-1 block text-sm font-medium text-slate-700">Suffix</label>
                    <input id="add-user-suffix" type="text" name="suffix" value="{{ old('suffix') }}" maxlength="50" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                </div>
                <div>
                    <label for="add-user-email" class="mb-1 block text-sm font-medium text-slate-700">Email address</label>
                    <input id="add-user-email" type="email" name="username" value="{{ old('username') }}" required class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                </div>
                <div>
                    <label for="add-user-contact" class="mb-1 block text-sm font-medium text-slate-700">Contact number</label>
                    <input id="add-user-contact" type="text" name="contact_number" value="{{ old('contact_number') }}" maxlength="50" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                </div>
                <div data-academic-field>
                    <label for="add-user-college" class="mb-1 block text-sm font-medium text-slate-700">College</label>
                    <select id="add-user-college" name="college_id" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Select college</option>
                        @foreach($colleges as $college)
                            <option value="{{ $college->id }}" @selected(old('college_id') == $college->id)>{{ $college->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div data-academic-field>
                    <label for="add-user-department" class="mb-1 block text-sm font-medium text-slate-700">Department</label>
                    <select id="add-user-department" name="department_id" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Select department</option>
                        @foreach($colleges->flatMap->departments as $department)
                            <option value="{{ $department->id }}" data-college="{{ $department->college_id }}" @selected(old('department_id') == $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div data-student-field>
                    <label for="add-user-school-id" class="mb-1 block text-sm font-medium text-slate-700">Student ID</label>
                    <input id="add-user-school-id" type="text" name="school_id_number" value="{{ old('school_id_number') }}" placeholder="23-0098-635" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                </div>
                <div data-faculty-field class="hidden">
                    <label for="add-user-faculty-id" class="mb-1 block text-sm font-medium text-slate-700">Faculty ID</label>
                    <input id="add-user-faculty-id" type="text" name="faculty_id" value="{{ old('faculty_id') }}" maxlength="50" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                </div>
                <div data-faculty-field class="hidden">
                    <label for="add-user-faculty-adviser" class="mb-1 block text-sm font-medium text-slate-700">Faculty adviser</label>
                    <select id="add-user-faculty-adviser" name="faculty_adviser" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        <option value="no" @selected(old('faculty_adviser', 'no') === 'no')>No</option>
                        <option value="yes" @selected(old('faculty_adviser') === 'yes')>Yes</option>
                    </select>
                </div>
                <div data-position-field>
                    <label for="add-user-position" class="mb-1 block text-sm font-medium text-slate-700">Position</label>
                    <input id="add-user-position" type="text" name="position" value="{{ old('position') }}" maxlength="100" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div data-student-organization-field class="hidden">
                    <label for="add-user-student-organization" class="mb-1 block text-sm font-medium text-slate-700">Student organization</label>
                    <select id="add-user-student-organization" name="student_organization_id" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Select organization</option>
                        @foreach(App\Models\StudentOrganization::query()->where('is_active', true)->orderBy('name')->get() as $organization)
                            <option value="{{ $organization->id }}" @selected(old('student_organization_id') == $organization->id)>{{ $organization->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div data-organization-field class="hidden">
                    <label for="add-user-organization" class="mb-1 block text-sm font-medium text-slate-700">Office / organization</label>
                    <input id="add-user-organization" type="text" name="office_or_organization" value="{{ old('office_or_organization') }}" maxlength="191" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                </div>
                <div>
                    <label for="add-user-password" class="mb-1 block text-sm font-medium text-slate-700">Password</label>
                    <div class="pitfr-password-wrapper">
                        <input id="add-user-password" type="password" name="password" required minlength="6" class="pitfr-password-input w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                        <button type="button" data-password-toggle-target="#add-user-password" aria-label="Show password" class="password-toggle pitfr-password-toggle"></button>
                    </div>
                </div>
                <div>
                    <label for="add-user-password-confirmation" class="mb-1 block text-sm font-medium text-slate-700">Confirm password</label>
                    <div class="pitfr-password-wrapper">
                        <input id="add-user-password-confirmation" type="password" name="password_confirmation" required minlength="6" class="pitfr-password-input w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100">
                        <button type="button" data-password-toggle-target="#add-user-password-confirmation" aria-label="Show password" class="password-toggle pitfr-password-toggle"></button>
                    </div>
                </div>
                <div class="flex gap-3 md:col-span-2">
                    <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Create account</button>
                    <a href="{{ route('admin.users') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-white">Cancel</a>
                </div>
            </form>
        </div>
        <script>
            const accountType = document.getElementById('account_type');
            const facultyAdviser = document.getElementById('add-user-faculty-adviser');
            const academicFields = document.querySelectorAll('[data-academic-field]');
            const studentFields = document.querySelectorAll('[data-student-field]');
            const facultyFields = document.querySelectorAll('[data-faculty-field]');
            const positionFields = document.querySelectorAll('[data-position-field]');
            const studentOrganizationFields = document.querySelectorAll('[data-student-organization-field]');
            const organizationFields = document.querySelectorAll('[data-organization-field]');
            const updateAccountFields = () => {
                const isStudent = accountType.value === 'student';
                const isFaculty = accountType.value === 'faculty';
                const isOutsider = accountType.value === 'outsider';
                const isAcademic = isStudent || isFaculty;
                const isFacultyAdviser = isFaculty && facultyAdviser && facultyAdviser.value === 'yes';
                academicFields.forEach(field => field.classList.toggle('hidden', !isAcademic));
                studentFields.forEach(field => field.classList.toggle('hidden', !isStudent));
                facultyFields.forEach(field => field.classList.toggle('hidden', !isFaculty));
                positionFields.forEach(field => field.classList.toggle('hidden', !(isStudent || isFaculty || isOutsider)));
                studentOrganizationFields.forEach(field => field.classList.toggle('hidden', !(isStudent || isFacultyAdviser)));
                organizationFields.forEach(field => field.classList.toggle('hidden', !isOutsider));
            };
            accountType.addEventListener('change', updateAccountFields);
            if (facultyAdviser) {
                facultyAdviser.addEventListener('change', updateAccountFields);
            }
            updateAccountFields();
        </script>
        </div>
    @endif

    @include('supply-office.components.user-management-table', ['users' => $users])
</div>
@endsection
