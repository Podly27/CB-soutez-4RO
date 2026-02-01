@extends('layouts.app')

@section('title', $title)

@section('sections')

    <x-recaptcha formId="submission-form"/>

    <section class="tm-section-2 tm-section-mb" id="tm-section-2">
        <div class="row">

            <div class="col-xl-12 col-lg-12 col-md-12">
                <h2 class="mb-4">{{ $title }}</h2>
                @if ($submissionSuccess = Session::pull('submissionSuccess'))
                    <div class="alert alert-success">
                        {!! $submissionSuccess !!}
                    </div>
                    <script>location.hash = '#scroll'</script>
                @elseif ($submissionErrors = Session::pull('submissionErrors'))
                    <div class="alert alert-danger">
                        @if (count($submissionErrors) == 1)
                            {{ $submissionErrors[0] }}
                        @else
                            <ul>
                            @foreach ($submissionErrors as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                            </ul>
                        @endif
                    </div>
                    <script>location.hash = '#scroll'</script>
                @elseif ($data->contests->all() && ! Auth::check())
                    <p class="alert alert-info">{{ __('Doporučení: Pro spárování soutěžního hlášení s vlastním uživatelským účtem je zapotřebí se nejdříve přihlásit přes hlavní menu.') }}</p>
                @endif

                @if (empty($data->contests->all()))
                <h3>{{ __('V tuto chvíli se nesbírají hlášení do žádné soutěže.') }}</h3>

                @elseif ($step == 1)
                <h3>{{ __('Krok 1: Výběr deníku') }}</h3>
                <form action="submission" method="post" class="submission-form" id="submission-form">
                    <input type="hidden" name="_csrf" value="{{ Utilities::getCsrfToken() }}">
                    <input type="hidden" name="step" value="1">
                    <div class="form-group">
                        <label for="diaryUrl">{{ __('Odkaz na deník') }} ({{ $diarySources }})...</label>
                        <input name="diaryUrl" type="text" class="form-control" id="diaryUrl" placeholder="http://">
                        <small class="form-text text-muted">{{ __('Pro CBPMR používejte odkaz ve formátu /share/portable/<id>.') }}</small>
                    </div>
                    <button type="submit" class="btn btn-primary g-recaptcha" data-sitekey="{{ config('ctvero.recaptchaSiteKey') }}" data-callback="onSubmit" data-action="submit">{{ __('Načíst údaje z deníku') }}</button>
                </form>
                <div class="row col-12 align-items-center mt-2">
                    <p>{{ __('Nebo') }} <p><a href="{{ route('submissionForm', [ 'step' => 2 ]) . '#scroll' }}" class="btn btn-secondary ml-4" role="button">{{ __('Vyplnit hlášení ručně') }}</a>
                </div>

                @elseif ($step == 2)
                <h3>{{ __('Krok 2') }}: {{ Session::get('diary') ? __('Kontrola a doplnění hlášení') : __('Vyplnění hlášení') }}</h3>
                <form action="submission" method="post" class="submission-form" id="submission-form">
                    <input type="hidden" name="_csrf" value="{{ Utilities::getCsrfToken() }}">
                    <input type="hidden" name="step" value="2">
                    <div class="row">
                        <div class="col-12 col-md-6 form-group required">
                            <label for="contest">{{ __('Soutěž') }}</label>
                            <select name="contest" class="form-control form-control-lg p-0 px-3" id="contest">
                                @if (count($data->contests) > 1)
                                <option disabled selected value>{{ __('Vyber možnost...') }}</option>
                                @endif
                                @foreach ($data->contests as $contest)
                                    <option value="{{ $contest->name }}" {{ Session::get('diary.contest') == $contest->name ? ' selected' : '' }}>{{ Utilities::contestL10n($contest->name) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6 form-group required">
                            <label for="category">{{ trans_choice('Kategorie', 1) }}</label>
                            <select name="category" class="form-control form-control-lg p-0 px-3" id="category">
                                @if (count($data->categories) > 1)
                                <option disabled selected value>{{ __('Vyber možnost...') }}</option>
                                @endif
                                @foreach ($data->categories as $category)
                                    <option value="{{ $category->name }}" {{ Session::get('diary.category') == $category->name ? ' selected' : '' }}>{{ __($category->name) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="diaryUrl">{{ __('URL deníku') }}</label>
                        <input name="diaryUrl" class="form-control" id="diaryUrl" type="text" placeholder="{{ __('URL adresa deníku určená ke sdílení') }}" value="{{ Session::get('diary.url') }}">
                        <small class="form-text text-muted">{{ __('Pro CBPMR používejte odkaz ve formátu /share/portable/<id>.') }}</small>
                    </div>
                    <div class="form-group required">
                        <label for="callSign">{{ __('Volačka') }}</label>
                        <input name="callSign" class="form-control" id="callSign" type="text" placeholder="{{ __('Tvoje nebo expediční volačka') }}" value="{{ Session::get('diary.callSign', Auth::user()->nickname ?? '') }}">
                    </div>
                    <div class="row">
                        <div class="col-12 col-lg-7 form-group required">
                            <label for="qthName">{{ __('Místo vysílání') }}</label>
                            <input name="qthName" class="form-control" id="qthName" type="text" placeholder="{{ __('Název místa vysílání (QTH)') }}" value="{{ Session::get('diary.qthName') }}">
                        </div>
                        <div class="col-12 col-lg-5 form-group required">
                            <label for="qthLocator">{{ __('Lokátor') }}</label>
                            <div class="input-group">
                                <input name="qthLocator" class="form-control" id="qthLocator" type="text" placeholder="{{ __('Lokátor místa vysílání') }}" value="{{ Session::get('diary.qthLocator') }}" maxlength="6">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" id="qthLocatorMap" type="button" data-toggle="modal" data-target="#modal-map">{{ __('Najít v mapě') }}</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal fade" id="modal-map" tabindex="-1" role="dialog">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h4 class="modal-title">{{ __('Najít lokátor v mapě') }}</h4>
                                    <button type="button" class="close" data-dismiss="modal">
                                        <span>&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <div id="map" style="height: 480px;" class="w-100"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12 col-lg-5 form-group required">
                            <label for="qsoCount">{{ __('Počet spojení') }}</label>
                            <input name="qsoCount" class="form-control" id="qsoCount" type="text" placeholder="{{ __('Počet úspěšných spojení (QSO)') }}" value="{{ Session::get('diary.qsoCount') }}">
                        </div>
                        <div class="col-12 col-lg-7 form-group required">
                            <label for="email">{{ __('E-mail') }} <small>({{ __('pro případnou komunikaci nesrovnalostí v deníku') }})</small></label>
                            <input name="email" class="form-control" id="email" type="email" placeholder="name@example.com" value="{{ Session::get('diary.email', Auth::user()->email ?? '') }}">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary g-recaptcha" data-sitekey="{{ config('ctvero.recaptchaSiteKey') }}" data-callback="onSubmit" data-action="submit">{{ __('Odeslat') }}</button>
                </form>
                <div class="row col-12 align-items-center mt-2">
                    <p>{{ __('Nebo') }} <p><a href="{{ route('submissionForm', [ 'step' => 1 ]) . '#scroll' }}" class="btn btn-secondary ml-4" role="button">{{ __('Zpět na výběr deníku') }}</a>
                </div>
                @endif
            </div>
        </div>
    </section>

@endsection

@section('scripts')

    @if ($step == 2)
    <x-leaflet-map/>

    <link rel="stylesheet" href="https://unpkg.com/leaflet-geosearch@3.6.0/dist/geosearch.css"/>
    <script src="https://unpkg.com/leaflet-geosearch@3.6.0/dist/geosearch.umd.js"></script>

    <script>
        function qthLocatorPopup(gpsLatLon, qthLocator) {
            $('#modal-map').data('qth-locator', qthLocator);
            var popup = L.popup()
                .setLatLng(gpsLatLon)
                .setContent(`<div class="text-center">
                                 <h5>${qthLocator}</h5>
                                 <a href="#">
                                     <h6 style="line-height: 1.5;">
                                         {{ __('Potvrdit lokátor') }}<br>
                                         <i class="fa fa-thumb-tack fa-lg"></i>
                                     </h6>
                                 </a>
                             </div>`)
                .openOn(map);
            $(popup._contentNode).find('a').click(function(e) {
                e.preventDefault();
                $('input#qthLocator').val(qthLocator);
                $('#modal-map').modal('hide');
            });
            map.flyTo(gpsLatLon);
        }

        function qthLocatorMapClick(e) {
            $.post('/api/v0/util/gpsToLocator', {gpsLon: e.latlng.lng, gpsLat: e.latlng.lat, _token: '{{ Utilities::getSessionSafeToken() }}'}, function(data) {
                qthLocatorPopup([e.latlng.lat, e.latlng.lng], data.qthLocator);
            });
        }
        map.on('click', qthLocatorMapClick);

        class MapboxProvider extends GeoSearch.JsonProvider {
            endpoint({query, type}) {
                return this.getUrl('https://api.mapbox.com/geocoding/v5/mapbox.places/' + query + '.json', {
                    access_token: '{{ config("ctvero.mapboxAccessToken") }}',
                    language: '{{ app("translator")->getLocale() }}',
                    proximity: [defaultCenterPos[1], defaultCenterPos[0]].join(',')
                });
            }
            parse({data}) {
                return data.features.map((d) => ({
                    x: d.center[0],
                    y: d.center[1],
                    label: d.place_name,
                    bounds: d.bbox ? [
                        [d.bbox[1], d.bbox[0]],
                        [d.bbox[3], d.bbox[2]],
                    ] : []
                }));
            }
        }
        search = GeoSearch.GeoSearchControl({
            provider: new MapboxProvider(),
            style: 'bar',
            showMarker: false,
            showPopup: true,
            popupFormat: function(d) {
                $.post('/api/v0/util/gpsToLocator', {gpsLon: d.result.x, gpsLat: d.result.y, _token: '{{ Utilities::getSessionSafeToken() }}'}, function(data) {
                    qthLocatorPopup([d.result.y, d.result.x], data.qthLocator);
                });
                return null;
            },
            animateZoom: false,
            autoClose: true,
            searchLabel: '{{ __("Vyhledat místo") }}',
            keepResult: true,
            updateMap: true
        });
        map.addControl(search);

        $('#modal-map').on('shown.bs.modal', function () {
            // Fix map size within modal after loading
            map.invalidateSize(true);

            if ($('input#qthLocator').val() !== '' && $('input#qthLocator').val().toUpperCase() !== $('#modal-map').data('qth-locator')) {
                $.post('/api/v0/util/locatorToGps', {qthLocator: $('input#qthLocator').val().toUpperCase(), _token: '{{ Utilities::getSessionSafeToken() }}'}, function(data) {
                    if (data.qthLocator !== null && data.gpsLon !== null && data.gpsLat !== null) {
                        qthLocatorPopup([data.gpsLat, data.gpsLon], data.qthLocator);
                    }
                });
            }
        })
    </script>
    @endif

@endsection
