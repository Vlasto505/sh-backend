<x-mail::message>
# Vitajte v {{ config('app.name') }}

Dobrý deň {{ $user->name }},

váš účet bol úspešne vytvorený. Môžete sa prihlásiť a začať používať platformu.

<x-mail::button :url="config('app.frontend_url', config('app.url'))">
Prejsť do aplikácie
</x-mail::button>

Alebo skopírujte tento odkaz do prehliadača:
{{ config('app.frontend_url', config('app.url')) }}

Registrovaný email: {{ $user->email }}

Ak ste sa neregistrovali Vy, tento email môžete ignorovať.

— Tím {{ config('app.name') }}

<x-slot:subcopy>
Tento email bol odoslaný automaticky.
Potrebujete pomoc? Kontaktujte nás na support@nti.sk
</x-slot:subcopy>
</x-mail::message>
