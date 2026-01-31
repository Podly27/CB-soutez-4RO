@extends('layouts.app')

@section('title', $title)

@section('sections')
    <section class="tm-section-2 my-5 py-4">
        <div class="row">
            <div class="col-12">
                <h2>{{ __('Soutěže') }}</h2>
                <p class="mb-3"><a href="{{ route('adminDashboard') }}">{{ __('Zpět na dashboard') }}</a></p>
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>{{ __('Název') }}</th>
                                <th>{{ __('Začátek soutěže') }}</th>
                                <th>{{ __('Konec soutěže') }}</th>
                                <th>{{ __('Začátek odesílání') }}</th>
                                <th>{{ __('Konec odesílání') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($contests as $contest)
                                <tr>
                                    <td>{{ $contest->id }}</td>
                                    <td>{{ $contest->name }}</td>
                                    <td>{{ Utilities::normalDateTime($contest->contest_start) }}</td>
                                    <td>{{ Utilities::normalDateTime($contest->contest_end) }}</td>
                                    <td>{{ Utilities::normalDateTime($contest->submission_start) }}</td>
                                    <td>{{ Utilities::normalDateTime($contest->submission_end) }}</td>
                                    <td>
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('adminContestEdit', ['id' => $contest->id]) }}">{{ __('Upravit') }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
@endsection
