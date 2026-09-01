@extends('layouts.app')
@section('title', 'Student Organizations')
@section('content')
<div class="space-y-6">
    <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200/60">
        <h1 class="text-2xl font-semibold text-slate-900">Student Organization Directory</h1>
        <p class="mt-1 text-sm text-slate-500">Manage recognized organizations and the students authorized to submit requests.</p>
        <form method="POST" action="{{ route('supply-office.organizations.store') }}" class="mt-5 grid gap-3 md:grid-cols-4">
            @csrf
            <input name="name" required placeholder="Organization name" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
            <input name="acronym" placeholder="Acronym" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
            <input name="organization_type" placeholder="Organization type" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
            <input name="adviser" placeholder="Adviser" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
            <select name="college_id" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                <option value="">Select College</option>
                @foreach(App\Models\College::orderBy('name')->get() as $college)
                    <option value="{{ $college->id }}">{{ $college->name }}</option>
                @endforeach
            </select>
            <select name="department_id" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                <option value="">Select Department</option>
                @foreach(App\Models\Department::orderBy('name')->get() as $department)
                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                @endforeach
            </select>
            <input name="category" placeholder="Category" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
            <button class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Add organization</button>
        </form>
    </section>
    @foreach($organizations as $organization)
        <section class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200/60">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">{{ $organization->name }} @if($organization->acronym)<span class="text-slate-500">({{ $organization->acronym }})</span>@endif</h2>
                    <p class="text-sm text-slate-500">{{ $organization->organization_type ?: 'Type not specified' }} · {{ $organization->is_active ? 'Active' : 'Inactive' }} · Adviser: {{ $organization->adviser ?: 'Not assigned' }}</p>
                </div>
                <form method="POST" action="{{ route('supply-office.organizations.update', $organization) }}" class="flex items-center gap-2">
                    @csrf @method('PUT')
                    <input type="hidden" name="name" value="{{ $organization->name }}">
                    <input type="hidden" name="is_active" value="{{ $organization->is_active ? 0 : 1 }}">
                    <input type="hidden" name="acronym" value="{{ $organization->acronym }}">
                    <input type="hidden" name="organization_type" value="{{ $organization->organization_type }}">
                    <input type="hidden" name="college_id" value="{{ $organization->college_id }}">
                    <input type="hidden" name="department_id" value="{{ $organization->department_id }}">
                    <input type="hidden" name="category" value="{{ $organization->category }}">
                    <input type="hidden" name="adviser" value="{{ $organization->adviser }}">
                    <button class="rounded-xl border border-slate-300 px-3 py-2 text-sm">{{ $organization->is_active ? 'Deactivate' : 'Activate' }}</button>
                </form>
            </div>
            <form method="POST" action="{{ route('supply-office.organizations.memberships.store', $organization) }}" class="mt-4 flex flex-wrap gap-2">
                @csrf
                <select name="user_id" required class="rounded-xl border border-slate-300 px-3 py-2 text-sm"><option value="">Select Student</option>@foreach($students as $student)<option value="{{ $student->id }}">{{ $student->name }}</option>@endforeach</select>
                <input name="membership_role" required placeholder="Membership role" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                <label class="flex items-center gap-2 px-2 text-sm"><input type="checkbox" name="can_submit_requests" value="1"> Can submit requests</label>
                <button class="rounded-xl bg-sky-600 px-3 py-2 text-sm font-semibold text-white">Save member</button>
            </form>
            <div class="mt-4 divide-y divide-slate-100">@foreach($organization->memberships as $membership)<div class="flex flex-wrap items-center justify-between gap-3 py-2 text-sm"><span>{{ $membership->user?->name }} · {{ $membership->membership_role ?: 'Role not set' }}</span><span class="text-slate-500">{{ $membership->is_active ? 'Active' : 'Inactive' }} · {{ $membership->can_submit_requests ? 'Authorized' : 'Member only' }}</span></div>@endforeach</div>
        </section>
    @endforeach
</div>
@endsection