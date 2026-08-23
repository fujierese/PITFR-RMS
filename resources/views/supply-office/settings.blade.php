@extends('layouts.app')
@section('title', 'Supply Office Settings')
@section('content')
    @include('settings.account', [
        'settingsRoute' => request()->routeIs('admin.*') ? 'admin.settings' : 'supply-office.settings',
        'showSignature' => false,
        'showOrganization' => false,
        'isAdmin' => true,
    ])
@endsection
