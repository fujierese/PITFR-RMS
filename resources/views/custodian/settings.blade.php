@extends('layouts.app')
@section('title', 'Custodian Settings')
@section('content')
    @include('settings.account', [
        'settingsRoute' => 'custodian.settings',
        'showSignature' => false, // Signature upload hidden (APR-01); set to true to bring it back.
        'showOrganization' => false,
        'isAdmin' => false,
    ])
@endsection
