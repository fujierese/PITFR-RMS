@extends('layouts.app')
@section('title', 'Requestor Settings')
@section('content')
    @include('settings.account', [
        'settingsRoute' => 'requestor.settings',
        'showSignature' => true,
        'showOrganization' => true,
        'isAdmin' => false,
    ])
@endsection
