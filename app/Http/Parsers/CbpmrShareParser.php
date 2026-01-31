<?php

namespace App\Http\Parsers;

use App\Exceptions\SubmissionException;
use DOMDocument;
use DOMXPath;
use GuzzleHttp\Client;

class CbpmrShareParser
{
    private Client $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client([
            'http_errors' => false,
        ]);
    }

    public function parse(string $url): array
    {
        $response = $this->client->request('GET', $url, [
            'allow_redirects' => true,
            'timeout' => 20,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml',
            ],
        ]);

        $status = $response->getStatusCode();
        $contentType = $response->getHeaderLine('Content-Type');
        $body = (string) $response->getBody();

        if ($status !== 200) {
            $this->logFailure($url, $status, $contentType, 0, $this->extractTitle($body));
            throw new SubmissionException(422, [__('Odkaz se nepodařilo načíst (HTTP :status)', [ 'status' => $status ])]);
        }

        $parsed = $this->parseHtml($body);
        $entriesCount = count($parsed['entries']);

        if ($entriesCount === 0) {
            $this->logFailure($url, $status, $contentType, $entriesCount, $this->extractTitle($body));
            throw new SubmissionException(422, [__('Nepodařilo se najít tabulku spojení na stránce (cbpmr share). Ověřte, že odkaz je veřejný.')]);
        }

        $parsed['header']['qso_count'] = $parsed['header']['qso_count'] ?? $entriesCount;

        return $parsed;
    }

    public function parseHtml(string $html): array
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        $header = [
            'my_locator' => $this->textById($xpath, 'locator'),
            'my_place' => $this->textById($xpath, 'place'),
            'total_km' => $this->parseInteger($this->textById($xpath, 'km')),
            'qso_count' => $this->parseInteger($this->textById($xpath, 'distance')),
            'exp_name' => $this->findExpName($xpath),
            'date' => $this->findDate($xpath),
        ];

        $entries = [];
        $rows = $xpath->query("//table[@id='myTable']//tbody/tr");
        if ($rows) {
            foreach ($rows as $row) {
                $cells = $xpath->query('./td', $row);
                $time = null;
                if ($cells && $cells->length > 1) {
                    $time = $this->extractTime($cells->item(1)->textContent ?? '');
                }

                $callsign = $this->textByQuery($xpath, ".//span[contains(concat(' ', normalize-space(@class), ' '), ' duplicity-name ')]", $row);
                $note = $this->textByQuery($xpath, ".//span[contains(concat(' ', normalize-space(@class), ' '), ' font-ariel ')]", $row);
                $locator = $this->textByQuery($xpath, ".//span[contains(concat(' ', normalize-space(@class), ' '), ' font-caption-locator-small ')]", $row);
                $kmText = $this->textByQuery($xpath, ".//*[contains(concat(' ', normalize-space(@class), ' '), ' span-km ')]", $row);

                $entry = [
                    'time' => $time,
                    'callsign' => $callsign,
                    'locator' => $locator,
                    'km' => $this->parseInteger($kmText),
                ];

                if ($note !== null && $note !== '') {
                    $entry['note'] = $note;
                }

                $entries[] = $entry;
            }
        }

        return [
            'header' => $header,
            'entries' => $entries,
        ];
    }

    private function textById(DOMXPath $xpath, string $id): ?string
    {
        $node = $xpath->query("//*[@id='{$id}']")->item(0);

        return $this->normalizeText($node ? $node->textContent : null);
    }

    private function textByQuery(DOMXPath $xpath, string $query, ?\DOMNode $context = null): ?string
    {
        $node = $xpath->query($query, $context)->item(0);

        return $this->normalizeText($node ? $node->textContent : null);
    }

    private function normalizeText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $clean = trim(preg_replace('/\s+/u', ' ', $text));

        return $clean === '' ? null : $clean;
    }

    private function parseInteger(?string $text): ?int
    {
        if ($text === null) {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', $text);

        return $digits === '' ? null : intval($digits);
    }

    private function extractTime(string $text): ?string
    {
        if (preg_match('/\b\d{1,2}:\d{2}\b/', $text, $matches)) {
            return $matches[0];
        }

        return $this->normalizeText($text);
    }

    private function findExpName(DOMXPath $xpath): ?string
    {
        foreach ($xpath->query('//div[normalize-space()]') as $node) {
            $text = $this->normalizeText($node->textContent);
            if ($text && preg_match('/^Exp\s+/u', $text)) {
                return $text;
            }
        }

        return null;
    }

    private function findDate(DOMXPath $xpath): ?string
    {
        foreach ($xpath->query('//text()[normalize-space()]') as $node) {
            $text = $this->normalizeText($node->textContent);
            if ($text && preg_match('/\b\d{2}\.\d{2}\.\d{4}\b/', $text, $matches)) {
                return $matches[0];
            }
        }

        return null;
    }

    private function extractTitle(string $html): ?string
    {
        if (preg_match('/<title>(.*?)<\/title>/is', $html, $matches)) {
            return $this->normalizeText($matches[1]);
        }

        return null;
    }

    private function logFailure(string $url, ?int $status, ?string $contentType, int $foundRows, ?string $title): void
    {
        try {
            $payload = [
                'url: ' . $url,
                'status: ' . ($status !== null ? $status : 'unknown'),
                'content-type: ' . ($contentType ?: 'unknown'),
                'found_rows_count: ' . $foundRows,
                'title: ' . ($title ?: 'unknown'),
            ];
            file_put_contents(storage_path('logs/last_exception.txt'), implode(PHP_EOL, $payload));
        } catch (\Throwable $e) {
            // Best effort logging only.
        }
    }
}
