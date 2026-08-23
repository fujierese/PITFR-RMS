@extends('layouts.app')
@section('title', 'Custodian Settings')
@section('content')
    @include('settings.account', [
        'settingsRoute' => 'custodian.settings',
        'showSignature' => true,
        'showOrganization' => false,
        'isAdmin' => false,
    ])
@endsection
