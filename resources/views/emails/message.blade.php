@component('mail::message')
Máte novou zprávu!
==================

**Od:** {{ $name }} <{{ $from }}>

**Obsah zprávy:** {{ $message }}

--  
*Zpráva byla přijata přes kontaktní formulář aplikace {{ config('app.name') }}*
@endcomponent
