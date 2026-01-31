@extends('layouts.app')

@section('title', $title)

@section('sections')
    <section class="tm-section-2 my-5 py-4">
        <div class="row">
            <div class="col-12 col-lg-8">
                <h2>{{ __('Nové kolo') }}</h2>
                <p class="mb-3">
                    <a href="{{ route('adminContests') }}">{{ __('Zpět na seznam soutěží') }}</a>
                </p>
                <form action="{{ route('adminContestStore') }}" method="post">
                    <input type="hidden" name="_csrf" value="{{ Utilities::getCsrfToken() }}">
                    <div class="form-group">
                        <label for="name">{{ __('Název') }}</label>
                        <input id="name" name="name" type="text" class="form-control" value="" required>
                    </div>
                    <div class="form-group">
                        <label for="contest_start">{{ __('Začátek soutěže') }}</label>
                        <input id="contest_start" name="contest_start" type="datetime-local" class="form-control" value="{{ $defaults['contest_start'] }}" required>
                    </div>
                    <div class="form-group">
                        <label for="contest_end">{{ __('Konec soutěže') }}</label>
                        <input id="contest_end" name="contest_end" type="datetime-local" class="form-control" value="{{ $defaults['contest_end'] }}" required>
                    </div>
                    <div class="form-group">
                        <label for="submission_start">{{ __('Začátek odesílání') }}</label>
                        <input id="submission_start" name="submission_start" type="datetime-local" class="form-control" value="{{ $defaults['submission_start'] }}" required>
                    </div>
                    <div class="form-group">
                        <label for="submission_end">{{ __('Konec odesílání') }}</label>
                        <input id="submission_end" name="submission_end" type="datetime-local" class="form-control" value="{{ $defaults['submission_end'] }}" required>
                    </div>
                    <button class="btn btn-primary" type="submit">{{ __('Vytvořit') }}</button>
                </form>
            </div>
        </div>
    </section>
@endsection
