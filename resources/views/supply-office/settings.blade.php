@extends('layouts.app')
@section('title', 'Supply Office Settings')
@section('content')
    @include('settings.account', [
        'settingsRoute' => request()->routeIs('admin.*') ? 'admin.settings' : 'supply-office.settings',
        'showSignature' => false, // Signature upload hidden (APR-01); set to true to bring it back.
        'showOrganization' => false,
        'isAdmin' => true,
    ])
@endsection
