<?php

use DiDom\Document;

class MessageTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('mail.default', 'array');
        $this->app['config']->set('ctvero.ownerMail', 'owner@example.com');

        $this->get('/lang/cs');
        $this->get('/');

        // CSRF
        $doc = new Document($this->response->getContent(), false);
        $this->csrfToken = $doc->first('form#contact-form input[name=_csrf]')->value;
    }

    public function testMessageFormMissingCsrf()
    {
        $this->post('/message', []);
        $this->seeStatusCode(302);
        $this->get('/');
        $this->response->assertSeeText('Zprávu se nepodařilo odeslat, zkuste to prosím později.');
    }

    public function testMessageFormMissingData()
    {
        $this->post('/message', [ '_csrf' => $this->csrfToken ]);
        $this->get('/');
        $this->response->assertSeeText('Pole name je vyžadováno.');
        $this->response->assertSeeText('Pole email je vyžadováno.');
        $this->response->assertSeeText('Pole subject je vyžadováno.');
        $this->response->assertSeeText('Pole message je vyžadováno.');
    }

    public function testMessageFormWrongMail()
    {
        $this->post('/message', [
            '_csrf' => $this->csrfToken,
            'name' => 'Tester',
            'email' => 'Wrong mail address',
        ]);
        $this->get('/');
        $this->response->assertSeeText('Pole email obsahuje neplatnou e-mailovou adresu.');
    }

    public function testMessageFormValidDataDoesNot500()
    {
        $this->post('/message', [
            '_csrf' => $this->csrfToken,
            'name' => 'Tester',
            'email' => 'tester@example.com',
            'subject' => 'Hello',
            'message' => 'Test message',
        ]);

        $this->assertTrue(in_array($this->response->getStatusCode(), [200, 302], true));
        $this->get('/');
        $this->response->assertSeeText('Zpráva byla');
    }
}
