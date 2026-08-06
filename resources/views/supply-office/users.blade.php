@extends('layouts.app')
@section('title', 'Users')

@section('content')
<div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200/50">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">User Management</h1>
            <p class="mt-1 text-sm text-slate-500">Maintain accounts, roles, and departmental access for the facility request system.</p>
        </div>
        <button type="button" class="inline-flex items-center gap-2 rounded-full bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700 transition">
            + Add User
        </button>
    </div>

    @include('supply-office.components.user-management-table', ['users' => $users])
</div>
@endsection
