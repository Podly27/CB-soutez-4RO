@if (! Auth::check())
    <script src="https://www.google.com/recaptcha/enterprise.js?render={{ config('ctvero.recaptchaSiteKey') }}"></script>

    <script>
        (function () {
            var recaptchaSiteKey = '{{ config('ctvero.recaptchaSiteKey') }}';
            if (! recaptchaSiteKey) {
                return;
            }

            var form = document.getElementById('{{ $formId }}');
            if (! form) {
                return;
            }

            form.addEventListener('submit', function (event) {
                if (form.dataset.recaptchaSubmitted === '1') {
                    form.dataset.recaptchaSubmitted = '0';
                    return;
                }

                event.preventDefault();

                grecaptcha.enterprise.ready(async function () {
                    try {
                        var token = await grecaptcha.enterprise.execute(recaptchaSiteKey, { action: 'submit' });
                        var tokenInput = form.querySelector('input[name=\"g-recaptcha-response\"]');
                        if (! tokenInput) {
                            tokenInput = document.createElement('input');
                            tokenInput.type = 'hidden';
                            tokenInput.name = 'g-recaptcha-response';
                            form.appendChild(tokenInput);
                        }
                        tokenInput.value = token;
                    } catch (error) {
                        console.error('reCAPTCHA execution failed', error);
                    }

                    form.dataset.recaptchaSubmitted = '1';
                    form.submit();
                });
            });
        })();
    </script>
@endif
