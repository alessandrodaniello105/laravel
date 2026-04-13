<x-mail::message>
# Workshop reminder

Hello {{ $user->name }},

You are registered for the following workshop(s) **tomorrow**@if(! empty($tomorrowLabel)) ({{ $tomorrowLabel }})@endif:

@foreach ($workshops as $workshop)
@php
    $when = $workshop->starts_at->timezone(config('app.timezone'))->format('g:i A T');
@endphp
- **{{ $workshop->name }}** — starts at {{ $when }}

<x-mail::button :url="route('workshops.show', $workshop, absolute: true)">
View workshop
</x-mail::button>

@endforeach

See you there,<br>
{{ config('app.name') }}
</x-mail::message>
