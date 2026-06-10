<x-mail::message>
# Aktualizácia stavu Vašej žiadosti

Dobrý deň {{ $application->user->name }},

stav Vašej žiadosti **{{ $application->title }}** bol zmenený.

@php
$statusLabels = [
    'approved'             => 'Schválená',
    'rejected'             => 'Zamietnutá',
    'supplement_requested' => 'Vyžaduje doplnenie',
    'under_review'         => 'V procese hodnotenia',
    'submitted'            => 'Odoslaná',
];
$label = $statusLabels[$application->status->value] ?? $application->status->value;
@endphp

**Aktuálny stav:** {{ $label }}

<x-mail::button :url="config('app.frontend_url', config('app.url')) . '/applications/' . $application->public_id">
Zobraziť žiadosť
</x-mail::button>

— Tím {{ config('app.name') }}

<x-slot:subcopy>
Tento email bol odoslaný automaticky.
</x-slot:subcopy>
</x-mail::message>
