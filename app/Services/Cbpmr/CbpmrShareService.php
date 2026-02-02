<?php

declare(strict_types=1);

namespace App\Services\Cbpmr;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMNodeList;
use DOMXPath;

class CbpmrShareService
{
    public function __construct()
    {
    }

    public function fetch(string $url): array
    {
        /** @var CbpmrShareFetcher $fetcher */
        $fetcher = app(CbpmrShareFetcher::class);
        $fetchResult = $fetcher->fetch($url, ['use_cookies' => true]);

        $body = $fetchResult['body'] ?? null;
        $titleSnippet = null;
        if (is_string($body) && $body !== '') {
            $titleSnippet = $this->extractTitleSnippet($body);
        }

        return [
            'ok' => $fetchResult['ok'] ?? false,
            'error' => $fetchResult['error'] ?? null,
            'code' => $fetchResult['code'] ?? null,
            'http_code' => $fetchResult['http_status'] ?? null,
            'final_url' => $fetchResult['final_url'] ?? $url,
            'redirect_chain' => $fetchResult['redirect_chain'] ?? [],
            'content_type' => $fetchResult['content_type'] ?? null,
            'body' => $body,
            'title_snippet' => $titleSnippet,
            'body_len' => is_string($body) ? strlen($body) : 0,
        ];
    }

    public function parsePortable(string $html, string $finalUrl): array
    {
        $payload = $this->buildPortablePayload($html, $finalUrl);
        $firstRows = [];
        foreach (array_slice($payload['entries'], 0, 3) as $entry) {
            $row = [
                'time' => $entry['time'] ?? null,
                'name' => $entry['name'] ?? null,
                'locator' => $entry['locator'] ?? null,
                'km' => $entry['km_int'] ?? null,
            ];
            if (! empty($entry['note'])) {
                $row['note'] = $entry['note'];
            }
            $firstRows[] = $row;
        }

        return [
            'portable_id' => $payload['portable_id'],
            'title' => $payload['title'],
            'my_locator' => $payload['my_locator'],
            'place' => $payload['place'],
            'qso_count_header' => $payload['qso_count_header'],
            'total_km' => $payload['total_km'],
            'rows_found' => $payload['rows_found'],
            'first_rows' => $firstRows,
            'entries' => $payload['entries'],
        ];
    }

    public function fetchHtml(string $url): array
    {
        $fetchResult = $this->fetch($url);

        if (! $fetchResult['ok']) {
            return [
                'ok' => false,
                'error' => $fetchResult['error'] ?? 'Fetch failed.',
                'http_code' => $fetchResult['http_code'] ?? null,
                'final_url' => $fetchResult['final_url'] ?? $url,
                'redirect_chain' => $fetchResult['redirect_chain'] ?? [],
                'content_type' => $fetchResult['content_type'] ?? null,
                'body' => null,
            ];
        }

        return [
            'ok' => true,
            'http_code' => $fetchResult['http_code'] ?? null,
            'final_url' => $fetchResult['final_url'] ?? $url,
            'redirect_chain' => $fetchResult['redirect_chain'] ?? [],
            'content_type' => $fetchResult['content_type'] ?? null,
            'body' => $fetchResult['body'] ?? '',
        ];
    }

    public function parsePortableHtml(string $html, string $finalUrl): array
    {
        return $this->buildPortablePayload($html, $finalUrl);
    }

    private function createXPath(string $html): DOMXPath
    {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<meta charset="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();

        return new DOMXPath($dom);
    }

    private function textFromXPath(DOMXPath $xpath, string $query, ?DOMElement $contextNode = null): ?string
    {
        $nodes = $contextNode ? $xpath->query($query, $contextNode) : $xpath->query($query);
        $node = $nodes instanceof DOMNodeList ? $nodes->item(0) : null;
        if (! $node instanceof DOMNode) {
            return null;
        }

        return $node->textContent;
    }

    private function normalizeText(?string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', $value ?? '');
        return trim($value);
    }

    private function extractInt(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (preg_match('/(\d+)/', $value, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function extractPortableId(string $finalUrl): ?string
    {
        $path = parse_url($finalUrl, PHP_URL_PATH) ?? '';
        if (preg_match('|/share/portable/(\\d+)|', $path, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function buildPortablePayload(string $html, string $finalUrl): array
    {
        $title = $this->extractTitleSnippet($html);
        $xpath = $this->createXPath($html);

        $myLocator = $this->normalizeText($this->textFromXPath($xpath, '//*[@id="locator"]'));
        $place = $this->normalizeText($this->textFromXPath($xpath, '//*[@id="place"]'));
        $qsoCountHeader = $this->normalizeText($this->textFromXPath($xpath, '//*[@id="distance"]'));
        $totalKm = $this->normalizeText($this->textFromXPath($xpath, '//*[@id="km"]'));

        $rows = $xpath->query('//table[@id="myTable"]//tbody//tr');
        $entries = [];
        $rowsFound = 0;

        if ($rows instanceof DOMNodeList) {
            foreach ($rows as $row) {
                if (! $row instanceof DOMElement) {
                    continue;
                }

                $rowsFound++;
                $time = $this->normalizeText($this->textFromXPath($xpath, './td[2]', $row));
                $name = $this->normalizeText($this->textFromXPath($xpath, './/span[contains(@class,"duplicity-name")]', $row));
                $locator = $this->normalizeText($this->textFromXPath($xpath, './/span[contains(@class,"font-caption-locator-small")]', $row));
                $kmText = $this->normalizeText($this->textFromXPath($xpath, './/p[contains(@class,"span-km")]', $row));
                $note = $this->normalizeText($this->textFromXPath($xpath, './/br/following-sibling::span[contains(@class,"font-ariel")][1]', $row));

                if ($note === '') {
                    $note = $this->normalizeText($this->textFromXPath($xpath, './/span[contains(@class,"font-ariel")][last()]', $row));
                }

                $entry = [
                    'time' => $time !== '' ? $time : null,
                    'name' => $name !== '' ? $name : null,
                    'locator' => $locator !== '' ? $locator : null,
                    'km_int' => $this->extractInt($kmText),
                ];

                if ($note !== '') {
                    $entry['note'] = $note;
                }

                $entries[] = $entry;
            }
        }

        $portableId = $this->extractPortableId($finalUrl);

        return [
            'portable_id' => $portableId,
            'title' => $title,
            'my_locator' => $myLocator !== '' ? $myLocator : null,
            'place' => $place !== '' ? $place : null,
            'qso_count_header' => $qsoCountHeader !== '' ? $qsoCountHeader : null,
            'total_km' => $totalKm !== '' ? $totalKm : null,
            'rows_found' => $rowsFound,
            'entries' => $entries,
        ];
    }

    private function extractTitleSnippet(string $html): ?string
    {
        if (! preg_match('/<title[^>]*>(.*?)<\\/title>/is', $html, $matches)) {
            return null;
        }

        $title = $this->normalizeText(strip_tags($matches[1]));
        if ($title === '') {
            return null;
        }

        return mb_substr($title, 0, 120, 'UTF-8');
    }
}
