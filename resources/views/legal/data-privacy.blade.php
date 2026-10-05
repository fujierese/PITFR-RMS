@extends('layouts.app')
@section('title', 'Data Privacy Act of 2012')

@section('content')
<div class="space-y-6">
    <x-page-header eyebrow="Philippine privacy information" title="Data Privacy Act of 2012" description="A plain-language introduction to Republic Act No. 10173 and personal information in this reservation system." accent="emerald" />
    @include('legal.data-privacy-content')
</div>
@endsection
