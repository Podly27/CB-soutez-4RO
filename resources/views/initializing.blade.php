@extends('layouts.app')

@section('title', __('Inicializace aplikace'))

@section('sections')
    <section class="tm-section-2 my-5 py-4">
        <div class="row">
            <div class="col-xl-20 col-lg-20 col-md-12">
                <h2>{{ __('Aplikace se inicializuje') }}</h2>
                <p class="mt-4">{{ $message ?? __('Databáze zatím není připravena. Dokončete nastavení a zkuste to znovu.') }}</p>
            </div>
        </div>
    </section>
@endsection
