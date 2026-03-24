<?php

class BasicTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->sessionCookieName = 'ctvero_rocnich_obdobi_session';
    }

    public function testIndex()
    {
        $this->get('/lang/cs');

        $this->get('/');
        $this->response->assertSeeTextInOrder([
            'Čtvero ročních období',
            'CB soutěž',
        ]);
        $this->seeStatusCode(200);
    }

    public function testIndexCsLocale()
    {
        $this->get('/', $headers = [ 'Accept-Language' => 'cs' ]);
        $this->response->assertSeeTextInOrder([
            'Čtvero ročních období',
            'CB soutěž',
        ]);
        $this->seeStatusCode(200);
    }

    public function testIndexDeLocale()
    {
        $this->get('/', $headers = [ 'Accept-Language' => 'de-AT' ]);
        $this->response->assertSeeTextInOrder([
            'Die vier Jahreszeiten',
            'CB-Wettbewerb',
        ]);
        $this->seeStatusCode(200);
    }

    public function testIndexEnLocale()
    {
        $this->get('/', $headers = [ 'Accept-Language' => 'en-US' ]);
        $this->response->assertSeeTextInOrder([
            'The Four Seasons',
            'CB-Contest',
        ]);
        $this->seeStatusCode(200);
    }

    public function testIndexMultipleLocalesDePreferred()
    {
        $this->get('/', $headers = [ 'Accept-Language' => 'de-AT,cs' ]);
        $this->response->assertSeeTextInOrder([
            'Die vier Jahreszeiten',
            'CB-Wettbewerb',
        ]);
        $this->seeStatusCode(200);
    }

    public function testIndexMultipleLocalesEnPreferred()
    {
        $this->get('/', $headers = [ 'Accept-Language' => 'en-US,cs' ]);
        $this->response->assertSeeTextInOrder([
            'The Four Seasons',
            'CB-Contest',
        ]);
        $this->seeStatusCode(200);
    }

    public function testIndexFallbackLocale()
    {
        $this->get('/', $headers = [ 'Accept-Language' => 'unsupported' ]);
        $this->response->assertSeeTextInOrder([
            'Čtvero ročních období',
            'CB soutěž',
        ]);
        $this->seeStatusCode(200);
    }

    public function testSwitchLocaleToCs()
    {
        $this->get('/lang/cs');
        $sessionCookie = $this->response->getCookie($this->sessionCookieName);
        $this->get('/', $headers = [ 'Set-Cookie' => $this->sessionCookieName . '=' . $sessionCookie ]);
        $this->response->assertSeeTextInOrder([
            'Čtvero ročních období',
            'CB soutěž',
        ]);
        $this->seeStatusCode(200);
    }

    public function testSwitchLocaleToDe()
    {
        $this->get('/lang/de');
        $sessionCookie = $this->response->getCookie($this->sessionCookieName);
        $this->get('/', $headers = [ 'Set-Cookie' => $this->sessionCookieName . '=' . $sessionCookie ]);
        $this->response->assertSeeTextInOrder([
            'Die vier Jahreszeiten',
            'CB-Wettbewerb',
        ]);
        $this->seeStatusCode(200);
    }

    public function testSwitchLocaleToUnsupported()
    {
        $this->get('/lang/unsupported');
        $this->seeStatusCode(422);
    }

    public function test404()
    {
        $this->get('/non-existing-page');
        $this->seeStatusCode(404);
    }

    public function testResults()
    {
        $this->get('/results');
        $this->seeStatusCode(200);
    }

    public function testResultsOldUrl()
    {
        $this->get('/vysledky');
        $this->seeStatusCode(302);
    }

    public function testSubmissionForm()
    {
        $this->get('/submission');
        $this->seeStatusCode(200);
    }

    public function testSubmissionFormOldUrl()
    {
        $this->get('/hlaseni');
        $this->seeStatusCode(302);
    }

    public function testSubmissionFormInvalidStep()
    {
        $this->get('/lang/cs');

        $this->get('/submission?step=999999999');
        $this->response->assertSeeText('Neplatný formulářový krok');
        $this->seeStatusCode(422);
    }

    public function testCalendar()
    {
        $this->get('/calendar?contest=Předkolo Zima 2020');
        $this->response->assertDownload();
        $this->seeStatusCode(200);
    }

    public function testCalendarOldUrl()
    {
        $this->get('/kalendar?soutez=Předkolo Zima 2020');
        $this->seeStatusCode(302);
    }

    public function testCalendarLinkInIndexPage()
    {
        $this->get('/');
        $this->response->assertSee('/calendar?contest=');
        $this->seeStatusCode(200);
    }

    public function testCalendarBadRequest()
    {
        $this->get('/calendar');
        $this->seeStatusCode(400);
    }

    public function testCalendarNonExistingContest()
    {
        $this->get('/calendar?contest=Neexistující soutěž');
        $this->seeStatusCode(404);
    }

    public function testRecaptchaOnMessageSend()
    {
        if (! config('ctvero.recaptchaSiteKey')) {
            $this->markTestSkipped('ReCAPTCHA is not configured.');
        }
        $originalConfig = config('app.env');
        config([ 'app.env' => 'production' ]);
        $this->post('/message', []);
        $this->seeStatusCode(403);
        config([ 'app.env' => $originalConfig ]);
    }

    public function testMessageSendDoesNotRequireLoginWhenRecaptchaSiteKeyMissing()
    {
        $originalConfig = config('app.env');
        config([
            'app.env' => 'production',
            'ctvero.recaptchaSiteKey' => null,
            'ctvero.recaptchaSecret' => null,
            'ctvero.recaptchaEnterpriseProjectId' => 'test-project',
            'ctvero.recaptchaEnterpriseApiKey' => 'test-api-key',
        ]);

        $this->get('/');
        $doc = new \DiDom\Document($this->response->getContent(), false);
        $csrfToken = $doc->first('form#contact-form input[name=_csrf]')->value;

        $this->post('/message', [
            '_csrf' => $csrfToken,
            'name' => 'Tester',
            'email' => 'tester@example.com',
            'subject' => 'Hello',
            'message' => 'Test message',
        ]);

        $this->assertTrue(in_array($this->response->getStatusCode(), [200, 302], true));
        config([ 'app.env' => $originalConfig ]);
    }

    public function testWrongMethodOnMessageSend()
    {
        $this->get('/message');
        $this->seeStatusCode(405);
    }

    public function testRecaptchaOnSubmissionSend()
    {
        if (! config('ctvero.recaptchaSiteKey')) {
            $this->markTestSkipped('ReCAPTCHA is not configured.');
        }
        $originalConfig = config('app.env');
        config([ 'app.env' => 'production' ]);
        $this->post('/submission', [ 'step' => 1 ]);
        $this->seeStatusCode(403);
        config([ 'app.env' => $originalConfig ]);
    }

    public function testSubmissionSendDoesNotRequireLoginWhenRecaptchaSiteKeyMissing()
    {
        $originalConfig = config('app.env');
        config([
            'app.env' => 'production',
            'ctvero.recaptchaSiteKey' => null,
            'ctvero.recaptchaSecret' => null,
            'ctvero.recaptchaEnterpriseProjectId' => 'test-project',
            'ctvero.recaptchaEnterpriseApiKey' => 'test-api-key',
        ]);

        $this->get('/submission');
        $doc = new \DiDom\Document($this->response->getContent(), false);
        $csrfToken = $doc->first('form#submission-form input[name=_csrf]')->value;

        $this->post('/submission', [
            '_csrf' => $csrfToken,
            'step' => 2,
        ]);

        $this->assertNotSame(403, $this->response->getStatusCode());
        config([ 'app.env' => $originalConfig ]);
    }

    public function testRecaptchaOnSubmissionSendStepTwo()
    {
        if (! config('ctvero.recaptchaSiteKey')) {
            $this->markTestSkipped('ReCAPTCHA is not configured.');
        }
        $originalConfig = config('app.env');
        config([ 'app.env' => 'production' ]);
        $this->post('/submission', [ 'step' => 2 ]);
        $this->seeStatusCode(403);
        config([ 'app.env' => $originalConfig ]);
    }

    public function testContests()
    {
        $this->get('/contests');
        $this->seeStatusCode(200);
    }

    public function testContest()
    {
        $this->get('/contest/Předkolo Zima 2020');
        $this->seeStatusCode(200);
    }

    public function testContestNonExisting()
    {
        $this->get('/contest/Neexistující soutěž');
        $this->seeStatusCode(404);
    }

    public function testContact()
    {
        $this->get('/contact');
        $this->seeStatusCode(302);
        $this->response->assertSeeTextInOrder([
            'Redirecting to ',
            '#contact-message',
        ]);
    }
}
