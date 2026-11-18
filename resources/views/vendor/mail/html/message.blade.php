<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
You received this email because of activity on your PITFR account. It was sent automatically, so please do not reply.<br>
You can choose which emails you receive in your account settings.

© {{ date('Y') }} Palompon Institute of Technology · [Privacy & Data Protection]({{ rtrim(config('app.url'), '/') }}/privacy-and-data-protection)
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
