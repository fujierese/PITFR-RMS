@extends('layouts.app')
@section('title', 'Frequently Asked Questions')

@section('content')
<div class="space-y-6">
    <x-page-header eyebrow="Help center" title="Frequently Asked Questions" description="Quick answers about facility and equipment reservations." accent="emerald" />
    @include('legal.faq-content')
</div>
@endsection
