@extends('layouts.app')

@section('title', $title)

@section('sections')
    <section class="tm-section-2 my-5 py-4">
        <div class="row">
            <div class="col-12">
                <h2>{{ __('Admin dashboard') }}</h2>
                <p class="text-muted">{{ __('Správa soutěží a deníků je dostupná pouze adminům.') }}</p>
                <ul class="list-group">
                    <li class="list-group-item">
                        <a href="{{ route('adminContests') }}">{{ __('Soutěže') }}</a>
                    </li>
                    <li class="list-group-item">
                        <a href="{{ route('adminDiaries') }}">{{ __('Deníky') }}</a>
                    </li>
                </ul>
            </div>
        </div>
    </section>
@endsection
