<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" href="/favicon.ico">

    <title>{{ __('Čtvero ročních období') }} - @yield('title')</title>
    <!--

    Template 2103 Central

    http://www.tooplate.com/view/2103-central

    -->
    <!-- Google web font "Open Sans" -->
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Open+Sans:300,400">
    <!-- Font Awesome -->
    <link rel="stylesheet" type="text/css" href="/static/font-awesome-4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css"
        integrity="sha384-B0vP5xmATw1+K9KRQjQERJvTumQW0nPEzvF6L/Z6nronJ3oUOFUFpCjEUQouq2+l"
        crossorigin="anonymous">
    <!-- Slick Carousel -->
    <link rel="stylesheet" type="text/css" href="/static/slick/slick.css" />
    <link rel="stylesheet" type="text/css" href="/static/slick/slick-theme.css" />
    <!-- tooplate style -->
    <link rel="stylesheet" type="text/css" href="/static/css/tooplate-style.css">
    <!-- Ctvero app styles and overrides -->
    <link rel="stylesheet" type="text/css" href="/static/css/ctvero.css">

    <!-- Global site tag (gtag.js) - Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-HP42P30DVN"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());

        gtag('config', 'G-HP42P30DVN');
    </script>

    <script>
        var renderPage = true;

        if (navigator.userAgent.indexOf('MSIE') !== -1
            || navigator.appVersion.indexOf('Trident/') > 0) {
            /* Microsoft Internet Explorer detected in. */
            alert("Please view this in a modern browser such as Chrome or Microsoft Edge.");
            renderPage = false;
        }

    </script>

</head>

<body>

    <!-- Loader -->
    <div id="loader-wrapper">
        <div id="loader"></div>
        <div class="loader-section section-left"></div>
        <div class="loader-section section-right"></div>
    </div>
    <div class="container">
        @foreach ([ 'errors' => 'danger', 'successes' => 'success', 'infos' => 'info' ] as $category => $alertType)
            @if ($messages = Session::pull($category))
                <div class="alert alert-{{ $alertType }}">
                    @if (count($messages) == 1)
                        {{ $messages[0] }}
                    @else
                        <ul>
                        @foreach ($messages as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                        </ul>
                    @endif
                </div>
            @endif
        @endforeach

        <section class="tm-section-head" id="top">
            <header id="header" class="text-center tm-text-gray">
                <h1><a href="{{ route('index') }}">{{ __('Čtvero ročních období') }}</a></h1>
                <p>{{ __('CB soutěž') }}</p>
            </header>

            <nav class="navbar navbar-light">
                <a class="navbar-brand tm-text-gray" href="#">
                    Menu
                </a>
                <button type="button" id="nav-toggle" class="navbar-toggler collapsed" data-toggle="collapse" data-target="#mainNav" aria-expanded="false"
                    aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon">
                        <i class="fa fa-navicon tm-fa-toggler-icon"></i>
                    </span>
                </button>
                <div id="mainNav" class="collapse navbar-collapse tm-bg-white">
                    <ul class="navbar-nav ml-auto">
                        <li class="nav-item">
                            <a class="nav-link tm-text-gray" href="{{ route('index') }}">Home
                                <span class="sr-only">(current)</span>
                            </a>
                        </li>
                        @php ($submissionDeadline = Utilities::submissionDeadline())
                        @if ($submissionDeadline)
                        <li class="nav-item">
                            <a class="nav-link tm-text-gray" href="{{ route('submissionForm') }}">{{ __('Odeslat hlášení') }}<br>
                            <small class="pt-0 mt-0">{{ $submissionDeadline }}</small>
                            </a>
                        </li>
                        @endif
                        <li class="nav-item">
                            <a class="nav-link tm-text-gray" href="{{ route('contests') }}">{{ __('Soutěžní kola') }}</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link tm-text-gray" href="{{ route('results') }}">{{ __('Výsledkové listiny') }}</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link tm-text-gray" href="{{ route('index') . '#contact-message' }}">{{ __('Kontakt') }}</a>
                        </li>
                        <li class="nav-item">
                        @foreach (config('ctvero.locales') as $lang)
                            {!! $loop->index > 0 ? '<span class="text-muted">|</span>' : '' !!} <a class="nav-link tm-text-gray d-inline-block" href="{{ route('lang', [ 'lang' => $lang ]) }}">{{ strtoupper($lang) }}</a>
                        @endforeach
                        </li>

                        <li class="dropdown-divider"></li>

                        @if (! Auth::check())
                        <li class="nav-item text-muted">
                            {{ __('Přihlásit se přes…') }}
                        </li>
                        <li class="nav-item">
                            <a class="nav-link tm-text-gray d-inline-block" href="{{ route('login', [ 'provider' => 'facebook' ]) }}"><i class="fa fa-facebook fa-2x"></i></a>
                            <a class="nav-link tm-text-gray d-inline-block ml-2" href="{{ route('login', [ 'provider' => 'google' ]) }}"><i class="fa fa-google fa-2x"></i></a>
                            <a class="nav-link tm-text-gray d-inline-block ml-2" href="{{ route('login', [ 'provider' => 'twitter' ]) }}" title="{{ __('Přihlásit přes X') }}" aria-label="{{ __('Přihlásit přes X') }}"><img class="social-icon" src="/static/img/x.png" alt="X"></a>
                        </li>
                        @elseif (Auth::user())
                        <li class="nav-item">
                            <a class="nav-link to-modal tm-text-gray" href="#" data-modal-id="profile" data-url="{{ route('profile') }}"><x-avatar/>{{ __('Můj profil') }}</a>
                        </li>
                        @if (Utilities::isAdmin(Auth::user()->email ?? null))
                        <li class="nav-item">
                            <a class="nav-link tm-text-gray" href="{{ route('adminDashboard') }}">{{ __('Admin') }}</a>
                        </li>
                        @endif
                        <li class="nav-item">
                            <a class="nav-link tm-text-gray" href="{{ route('logout') }}">{{ __('Odhlásit se') }}</a>
                        </li>
                        @endif
                        <li class="nav-item">
                            <a class="nav-link to-modal tm-text-gray small" href="#" data-modal-id="terms-and-privacy" data-url="{{ route('termsAndPrivacy') }}">{!! __('Podmínky služby<br>a ochrana soukromí') !!}</a>
                        </li>
                    </ul>
                </div>
            </nav>
        </section>

        <section class="row" id="tm-section-1">
            <div class="col-lg-12 tm-slider-col">
                <div class="tm-img-slider">
                    <div class="tm-img-slider-item">
                        <p class="tm-slider-caption">{{ __('Vysílání nás baví !') }}</p>
                        <img data-lazy="/static/img/gallery-img-1.jpg" alt="Slider" class="tm-slider-img">
                    </div>
                    <div class="tm-img-slider-item">
                        <p class="tm-slider-caption">{{ __('Výzva na kanále ...') }}</p>
                        <img data-lazy="/static/img/gallery-img-2.jpg" alt="Slider" class="tm-slider-img">
                    </div>
                    <div class="tm-img-slider-item">
                        <p class="tm-slider-caption">{{ __('Stanice ... na příjmu') }}</p>
                        <img data-lazy="/static/img/gallery-img-3.jpg" alt="Slider" class="tm-slider-img">
                    </div>
                    <div class="tm-img-slider-item">
                        <p class="tm-slider-caption">{{ __('27 MHz - pásmo 11m') }}</p>
                        <img data-lazy="/static/img/gallery-img-4.jpg" alt="Slider" class="tm-slider-img">
                    </div>
                    <div class="tm-img-slider-item">
                        <p class="tm-slider-caption">{{ __('73 a naslyš !') }}</p>
                        <img data-lazy="/static/img/gallery-img-5.jpg" alt="Slider" class="tm-slider-img">
                    </div>
                    <div class="tm-img-slider-item">
                        <p class="tm-slider-caption">{{ __('Hodně pěkných spojení !') }}</p>
                        <img data-lazy="/static/img/gallery-img-6.jpg" alt="Slider" class="tm-slider-img">
                    </div>
                    <div class="tm-img-slider-item">
                        <p class="tm-slider-caption">{{ __('Kategorie Pěšák') }}</p>
                        <img data-lazy="/static/img/gallery-img-7.jpg" alt="Slider" class="tm-slider-img">
                    </div>
                    <div class="tm-img-slider-item">
                        <p class="tm-slider-caption">{{ __('Kategorie Mobil') }}</p>
                        <img data-lazy="/static/img/gallery-img-8.jpg" alt="Slider" class="tm-slider-img">
                    </div>
                    <div class="tm-img-slider-item">
                        <p class="tm-slider-caption">{{ __('Kategorie Obrněnec') }}</p>
                        <img data-lazy="/static/img/gallery-img-9.jpg" alt="Slider" class="tm-slider-img">
                    </div>
                    <div class="tm-img-slider-item">
                        <p class="tm-slider-caption">{{ __('Kategorie Sdílenky') }}</p>
                        <img data-lazy="/static/img/gallery-img-11.jpg" alt="Slider" class="tm-slider-img">
                    </div>
                </div>
            </div>
        </section>

        <section id="scroll"></section>

        @yield('sections')

        <footer class="mt-5">
            <p class="text-center small">Copyright © 2020-2022 Radek Lichnov. Design: <a href="https://www.tooplate.com/">Tooplate</a>
            | {!! __('Tento <a href=":repositoryLink">projekt</a> je open-source', [ 'repositoryLink' => config('ctvero.repositoryUrl') ]) !!}. Engine: <a href="https://lumen.laravel.com/">Lumen Framework</a>
            | {{ __('Překlad do němčiny') }}: KML - Kamillo</p>
        </footer>
    </div>

    <x-modal.profile/>

    <x-modal.terms-and-privacy/>

    <!-- load JS files -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"
        integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0="
        crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-Piv4xVNRyMGpqkS2by6br4gNJ7DXjqk09RmUpJ8jgGtD7zP9yug3goQfGII0yAns"
        crossorigin="anonymous"></script>

    <!-- Slick Carousel -->
    <script src="/static/slick/slick.min.js"></script>

    <!-- Ctvero app scripts and final document loading actions -->
    <script src="/static/js/ctvero.js"></script>

    @yield('scripts')

</body>

</html>
