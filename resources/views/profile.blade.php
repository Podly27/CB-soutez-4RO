@extends(request()->ajax() ? 'layouts.ajax' : 'layouts.app')

@section('title', $title)

@section('sections')

    <section class="tm-section-2 tm-section-mb" id="tm-section-2">
        <div class="row">
            <div class="col-xl-12 col-lg-12 col-md-12">
                @if (! request()->ajax())
                    <h2 class="mb-4"><x-avatar :small="false"/>{{ $title }}</h2>
                @endif
                @if (! Auth::user()->email)
                    <div class="alert alert-warning">
                        {{ __('Účet X neposkytl e-mail. Pokud chcete e-mail pro notifikace, doplňte jej v profilu.') }}
                    </div>
                @endif
                <form>
                    <div class="row">
                        <div class="form-group col-12 col-lg-9">
                            <label for="nickname">{{ __('Přezdívka') }} <small>({{ __('volačka') }})</small></label>
                            <div class="input-group">
                                <input name="nickname" type="text" class="form-control" id="nickname" value="{{ Auth::user()->nickname }}">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" id="saveNickname" type="button"><span class="fa fa-floppy-o"></span></button>
                                </div>
                                <span class="input-group-text ajax-status-indicator"><i id="saveNicknameStatus"></i></span>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-12 col-lg-6">
                            <label for="name">{{ __('Jméno') }}</label>
                            <input name="name" type="text" class="form-control" id="name" value="{{ Auth::user()->name }}" readonly>
                        </div>
                        <div class="form-group col-12 col-lg-6">
                            <label for="email">{{ __('E-mail') }}</label>
                            <input name="email" type="text" class="form-control" id="email" value="{{ Auth::user()->email }}" readonly>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>

@endsection

@section('scripts')

    <script>
        $('button#saveNickname').data('nickname', '{{ Auth::user()->nickname }}');
        $('button#saveNickname').click(function () {
            if ($(this).data('nickname') == $('input#nickname').val()) {
                return;
            }
            $('i#saveNicknameStatus').removeClass();
            $('input#nickname').tooltip('dispose');
            $.post('/api/v0/user/nickname', {nickname: $('input#nickname').val()}, function(data) {
                if (data == 'success') {
                    $('button#saveNickname').data('nickname', $('input#nickname').val());
                    $('i#saveNicknameStatus').addClass('fa fa-check text-success');
                }
            }).fail(function(data) {
                $('button#saveNickname').removeData('nickname');
                $('i#saveNicknameStatus').addClass('fa fa-times text-danger');
                errorTooltip('input#nickname', data);
            });
        });
    </script>

@endsection
