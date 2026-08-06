@extends('layouts.app')
@section('title', 'Custodian Settings')

@section('content')
<div class="mx-auto w-full max-w-none px-3 py-4 sm:px-4 sm:py-6 lg:max-w-5xl lg:px-6 lg:py-8">
    <div class="overflow-hidden rounded-3xl bg-white shadow-xl ring-1 ring-slate-200/60">
        <div class="border-b border-slate-200 bg-slate-50 px-4 py-4 sm:px-6 sm:py-5">
            <h1 class="text-2xl font-semibold text-slate-900">Custodian Settings</h1>
            <p class="mt-2 text-sm text-slate-600">Update your profile details and password for your custodial account.</p>
        </div>

        <div class="grid gap-4 p-4 sm:gap-6 sm:p-6 lg:grid-cols-2">
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Profile Information</h2>
                <form method="POST" action="{{ route('custodian.settings.profile') }}" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <label class="text-sm font-medium text-slate-700">Full Name</label>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2" required>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Department</label>
                        <input type="text" name="department" value="{{ old('department', $user->department) }}" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Contact Number</label>
                        <input type="text" name="contact_number" value="{{ old('contact_number', $user->contact_number) }}" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2">
                    </div>
                    <button type="submit" class="w-full rounded-2xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white sm:w-auto">Save Profile</button>
                </form>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Change Password</h2>
                <form method="POST" action="{{ route('custodian.settings.password') }}" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <label class="text-sm font-medium text-slate-700">Current Password</label>
                        <div class="relative password-toggle-wrapper">
                            <input type="password" name="current_password" id="custodian_current_password" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2 pr-12" required>
                            <button type="button" data-password-toggle-target="#custodian_current_password" aria-label="Show current password" class="password-toggle absolute inset-y-0 right-4 inline-flex h-10 w-10 items-center justify-center rounded-full text-slate-500 transition hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-slate-50">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">New Password</label>
                        <div class="relative password-toggle-wrapper">
                            <input type="password" name="password" id="custodian_new_password" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2 pr-12" required>
                            <button type="button" data-password-toggle-target="#custodian_new_password" aria-label="Show new password" class="password-toggle absolute inset-y-0 right-4 inline-flex h-10 w-10 items-center justify-center rounded-full text-slate-500 transition hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-slate-50">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">Confirm Password</label>
                        <div class="relative password-toggle-wrapper">
                            <input type="password" name="password_confirmation" id="custodian_password_confirmation" class="mt-1 w-full rounded-2xl border border-slate-200 px-3 py-2 pr-12" required>
                            <button type="button" data-password-toggle-target="#custodian_password_confirmation" aria-label="Show confirm password" class="password-toggle absolute inset-y-0 right-4 inline-flex h-10 w-10 items-center justify-center rounded-full text-slate-500 transition hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-slate-50">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="w-full rounded-2xl bg-slate-800 px-4 py-2 text-sm font-semibold text-white sm:w-auto">Update Password</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
