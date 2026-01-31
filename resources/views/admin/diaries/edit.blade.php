@extends('layouts.app')

@section('title', $title)

@section('sections')
    <section class="tm-section-2 my-5 py-4">
        <div class="row">
            <div class="col-12 col-lg-8">
                <h2>{{ __('Upravit deník') }}</h2>
                <p class="mb-3">
                    <a href="{{ route('adminDiaries') }}">{{ __('Zpět na seznam deníků') }}</a>
                </p>
                <form action="{{ route('adminDiaryUpdate', ['id' => $diary->id]) }}" method="post">
                    <input type="hidden" name="_csrf" value="{{ Utilities::getCsrfToken() }}">
                    <div class="form-group">
                        <label for="call_sign">{{ __('Call sign') }}</label>
                        <input id="call_sign" name="call_sign" type="text" class="form-control" value="{{ $diary->call_sign }}" required>
                    </div>
                    <div class="form-group">
                        <label for="diary_url">{{ __('URL deníku') }}</label>
                        <input id="diary_url" name="diary_url" type="url" class="form-control" value="{{ $diary->diary_url }}">
                    </div>
                    <div class="form-group">
                        <label for="qth_name">{{ __('QTH název') }}</label>
                        <input id="qth_name" name="qth_name" type="text" class="form-control" value="{{ $diary->qth_name }}">
                    </div>
                    <div class="form-group">
                        <label for="qth_locator">{{ __('Lokátor') }}</label>
                        <input id="qth_locator" name="qth_locator" type="text" maxlength="6" class="form-control" value="{{ $diary->qth_locator }}" required>
                    </div>
                    <div class="form-group">
                        <label for="qso_count">{{ __('QSO') }}</label>
                        <input id="qso_count" name="qso_count" type="number" min="0" class="form-control" value="{{ $diary->qso_count }}" required>
                    </div>
                    <div class="form-group">
                        <label for="score_points">{{ __('Body') }}</label>
                        <input id="score_points" name="score_points" type="number" min="0" class="form-control" value="{{ $diary->score_points }}">
                    </div>
                    <div class="form-group">
                        <label for="email">{{ __('E-mail') }}</label>
                        <input id="email" name="email" type="email" class="form-control" value="{{ $diary->email }}">
                    </div>
                    <div class="form-group">
                        <label for="contest_id">{{ __('Soutěž') }}</label>
                        <select id="contest_id" name="contest_id" class="form-control" required>
                            @foreach ($contests as $contest)
                                <option value="{{ $contest->id }}" {{ (string) $diary->contest_id === (string) $contest->id ? 'selected' : '' }}>
                                    {{ $contest->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="category_id">{{ __('Kategorie') }}</label>
                        <select id="category_id" name="category_id" class="form-control" required>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" {{ (string) $diary->category_id === (string) $category->id ? 'selected' : '' }}>
                                    {{ __($category->name) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <button class="btn btn-primary" type="submit">{{ __('Uložit') }}</button>
                </form>

                <form class="mt-3" action="{{ route('adminDiaryDelete', ['id' => $diary->id]) }}" method="post" onsubmit="return confirm('{{ __('Opravdu chcete deník smazat?') }}')">
                    <input type="hidden" name="_csrf" value="{{ Utilities::getCsrfToken() }}">
                    <button class="btn btn-outline-danger" type="submit">{{ __('Smazat') }}</button>
                </form>
            </div>
        </div>
    </section>
@endsection
