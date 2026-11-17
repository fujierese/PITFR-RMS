@extends('layouts.app')
@section('title', 'Requestor Settings')
@section('content')
    @include('settings.account', [
        'settingsRoute' => 'requestor.settings',
        'showSignature' => false, // Signature upload hidden (APR-01); set to true to bring it back.
        'showOrganization' => true,
        'colleges' => $colleges ?? collect(),
        'isAdmin' => false,
    ])
@endsection
