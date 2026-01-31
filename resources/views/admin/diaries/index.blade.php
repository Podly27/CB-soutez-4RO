@extends('layouts.app')

@section('title', $title)

@section('sections')
    <section class="tm-section-2 my-5 py-4">
        <div class="row">
            <div class="col-12">
                <h2>{{ __('Deníky') }}</h2>
                <p class="mb-3"><a href="{{ route('adminDashboard') }}">{{ __('Zpět na dashboard') }}</a></p>

                <form class="mb-4" method="get" action="{{ route('adminDiaries') }}">
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="contest_id">{{ __('Soutěž') }}</label>
                            <select id="contest_id" name="contest_id" class="form-control">
                                <option value="">{{ __('Vše') }}</option>
                                @foreach ($contests as $contest)
                                    <option value="{{ $contest->id }}" {{ (string) $filters['contest_id'] === (string) $contest->id ? 'selected' : '' }}>
                                        {{ $contest->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="category_id">{{ __('Kategorie') }}</label>
                            <select id="category_id" name="category_id" class="form-control">
                                <option value="">{{ __('Vše') }}</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" {{ (string) $filters['category_id'] === (string) $category->id ? 'selected' : '' }}>
                                        {{ __($category->name) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="search">{{ __('Hledat') }}</label>
                            <input id="search" name="search" type="text" class="form-control" value="{{ $filters['search'] }}">
                        </div>
                    </div>
                    <button class="btn btn-outline-primary" type="submit">{{ __('Filtrovat') }}</button>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>{{ __('Call sign') }}</th>
                                <th>{{ __('Soutěž') }}</th>
                                <th>{{ __('Kategorie') }}</th>
                                <th>{{ __('QTH název') }}</th>
                                <th>{{ __('Lokátor') }}</th>
                                <th>{{ __('QSO') }}</th>
                                <th>{{ __('Body') }}</th>
                                <th>{{ __('E-mail') }}</th>
                                <th>{{ __('URL deníku') }}</th>
                                <th>{{ __('Uživatel') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($diaries as $diary)
                                <tr>
                                    <td>{{ $diary->id }}</td>
                                    <td>{{ $diary->call_sign }}</td>
                                    <td>
                                        {{ $diary->contest ? $diary->contest->name : '-' }}
                                    </td>
                                    <td>
                                        {{ $diary->category ? __($diary->category->name) : '-' }}
                                    </td>
                                    <td>{{ $diary->qth_name }}</td>
                                    <td>{{ $diary->qth_locator }}</td>
                                    <td>{{ $diary->qso_count }}</td>
                                    <td>{{ $diary->score_points }}</td>
                                    <td>{{ $diary->email }}</td>
                                    <td>
                                        @if ($diary->diary_url)
                                            <a href="{{ $diary->diary_url }}" target="_blank" rel="noopener">{{ __('Odkaz') }}</a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ $diary->user_id }}</td>
                                    <td>
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('adminDiaryEdit', ['id' => $diary->id]) }}">{{ __('Upravit') }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @php
                    $baseParams = array_filter([
                        'contest_id' => $filters['contest_id'],
                        'category_id' => $filters['category_id'],
                        'search' => $filters['search'],
                    ], static function ($value) {
                        return $value !== null && $value !== '';
                    });
                @endphp

                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div class="text-muted">
                        {{ __('Strana') }} {{ $pagination['page'] }} / {{ $pagination['lastPage'] }}
                        ({{ $pagination['total'] }} {{ __('záznamů') }})
                    </div>
                    <div>
                        @if ($pagination['page'] > 1)
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('adminDiaries', array_merge($baseParams, ['page' => $pagination['page'] - 1])) }}">{{ __('Předchozí') }}</a>
                        @endif
                        @if ($pagination['page'] < $pagination['lastPage'])
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('adminDiaries', array_merge($baseParams, ['page' => $pagination['page'] + 1])) }}">{{ __('Další') }}</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
