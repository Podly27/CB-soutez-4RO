<?php

use App\Http\Parsers\CbpmrShareParser;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class CbpmrShareParserTest extends TestCase
{
    public function testParsesPortableShareHtml()
    {
        $html = file_get_contents(__DIR__ . '/fixtures/cbpmr_share_portable.html');
        $mock = new MockHandler([
            new Response(200, [ 'Content-Type' => 'text/html; charset=utf-8' ], $html),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client([ 'handler' => $handlerStack ]);
        $parser = new CbpmrShareParser($client);

        $parsed = $parser->parse('https://www.cbpmr.info/share/portable/10860');

        $this->assertSame('JN99DJ', $parsed['header']['my_locator']);
        $this->assertSame(2, count($parsed['entries']));
        $this->assertSame('20:26', $parsed['entries'][0]['time']);
        $this->assertSame('JN69WR', $parsed['entries'][0]['locator']);
        $this->assertSame(321, $parsed['entries'][0]['km']);
    }

    public function testParsesHashShareHtml()
    {
        $html = file_get_contents(__DIR__ . '/fixtures/cbpmr_share_portable.html');
        $mock = new MockHandler([
            new Response(200, [ 'Content-Type' => 'text/html; charset=utf-8' ], $html),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client([ 'handler' => $handlerStack ]);
        $parser = new CbpmrShareParser($client);

        $parsed = $parser->parse('https://www.cbpmr.info/share/2eb11f');

        $this->assertSame(2, count($parsed['entries']));
    }
}
