@extends('layouts.app')
@section('title', 'IGP Account Settings')
@section('content')
    @include('settings.account', [
        'settingsRoute' => 'igp.settings',
        'showSignature' => false, // Signature upload hidden (APR-01); set to true to bring it back.
        'showOrganization' => false,
        'isAdmin' => false,
    ])
@endsection
