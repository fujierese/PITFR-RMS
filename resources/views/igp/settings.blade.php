@extends('layouts.app')
@section('title', 'IGP Account Settings')
@section('content')
    @include('settings.account', [
        'settingsRoute' => 'igp.settings',
        'showSignature' => true,
        'showOrganization' => false,
        'isAdmin' => false,
    ])
@endsection
