@extends('layouts.app')
@section('title', 'Privacy & Data Protection')

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <x-page-header eyebrow="Help & Information" title="Privacy & Data Protection" description="How PITFR collects, uses, stores and protects your personal information." accent="emerald" />
    @include('legal.privacy-content')
</div>
@endsection
