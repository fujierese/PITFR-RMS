@extends('layouts.app')
@section('title', 'Privacy Policy Draft')

@section('content')
<div class="space-y-6">
    <x-page-header eyebrow="Privacy and transparency" title="Privacy Policy" description="Draft notice for the PIT Facility and Equipment Request System." accent="emerald" />
    @include('legal.privacy-content')
</div>
@endsection
