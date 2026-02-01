<?php

declare(strict_types=1);

namespace App\Services\Cbpmr;

use DiDom\Document;

class CbpmrShareParser
{
    private const MAX_PREVIEW_ROWS = 3;

    public function parsePortable(string $html): array
    {
        $document = new Document($html, true);

        $title = null;
        $titleElement = $document->first('title');
        if ($titleElement) {
            $title = trim($titleElement->text());
            if ($title === '') {
                $title = null;
            }
        }

        $headerElement = $document->first('header');
        $headerText = $headerElement ? $headerElement->text() : $document->text();
        $date = null;
        if (preg_match('/\b\d{1,2}\.\d{1,2}\.\d{4}\b/u', $headerText, $matches)) {
            $date = $matches[0];
        }

        $locator = $this->textFromSelector($document, '#locator');
        $place = $this->textFromSelector($document, '#place');
        $distance = $this->textFromSelector($document, '#distance');
        $totalKm = $this->textFromSelector($document, '#km');

        $qsoCount = $this->extractInt($distance);
        $totalKmValue = $this->extractInt($totalKm);

        $rows = $document->find('table#myTable tbody tr');
        $parsedRows = [];
        $foundRowsCount = 0;

        foreach ($rows as $row) {
            $cells = $row->find('td');
            if (count($cells) < 3) {
                continue;
            }

            $foundRowsCount++;

            $time = isset($cells[2]) ? $this->normalizeText($cells[2]->text()) : null;
            $nameElement = $row->first('span.duplicity-name');
            $locatorElement = $row->first('span.font-caption-locator-small');
            $noteSpans = $row->find('span.font-ariel');
            $note = null;
            if (count($noteSpans) >= 2) {
                $note = $this->normalizeText($noteSpans[1]->text());
            }

            $kmElement = $row->first('p.span-km');
            $km = $kmElement ? $this->extractInt($kmElement->text()) : null;

            $parsedRows[] = [
                'time' => $time,
                'name' => $nameElement ? $this->normalizeText($nameElement->text()) : null,
                'note' => $note,
                'locator' => $locatorElement ? $this->normalizeText($locatorElement->text()) : null,
                'km' => $km,
            ];
        }

        $hasTable = count($document->find('table#myTable')) > 0;

        return [
            'title' => $title,
            'date' => $date,
            'qth_locator' => $locator,
            'qth_name' => $place,
            'qso_count' => $qsoCount,
            'total_km' => $totalKmValue,
            'rows' => $parsedRows,
            'found_rows_count' => $foundRowsCount,
            'has_table' => $hasTable,
        ];
    }

    public function parse(string $html): array
    {
        $document = new Document($html, true);

        $title = null;
        $titleElement = $document->first('title');
        if ($titleElement) {
            $title = trim($titleElement->text());
            if ($title === '') {
                $title = null;
            }
        }

        $locator = null;
        $locatorElement = $document->first('#locator');
        if ($locatorElement) {
            $locator = $this->normalizeText($locatorElement->text());
        }

        $rows = $document->find('table#myTable tr');
        $firstRows = [];
        $qsoCount = 0;

        foreach ($rows as $row) {
            $cells = $row->find('th, td');
            if (count($cells) < 4) {
                continue;
            }

            $hasHeader = count($row->find('th')) > 0;
            if ($hasHeader) {
                continue;
            }

            $qsoCount++;

            if (count($firstRows) < self::MAX_PREVIEW_ROWS) {
                $firstRows[] = [
                    'time' => $this->normalizeText($cells[0]->text()),
                    'name' => $this->normalizeText($cells[1]->text()),
                    'locator' => $this->normalizeText($cells[2]->text()),
                    'km' => $this->normalizeText($cells[3]->text()),
                ];
            }
        }

        $detectedFormat = 'unknown';
        if (count($rows) > 0) {
            $detectedFormat = 'html-table';
        } elseif (preg_match('/id="app"|data-v-app/i', $html)) {
            $detectedFormat = 'spa';
        }

        return [
            'title' => $title,
            'detected_format' => $detectedFormat,
            'parsed_preview' => [
                'qso_count' => $qsoCount,
                'my_locator' => $locator,
                'first_rows' => $firstRows,
            ],
        ];
    }

    public function extractShareId(string $html): ?string
    {
        if (preg_match('|/share/[^/]+/(\d+)|', $html, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function normalizeText(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', $value);
        return trim($value ?? '');
    }

    private function textFromSelector(Document $document, string $selector): ?string
    {
        $element = $document->first($selector);
        if (! $element) {
            return null;
        }

        $text = $this->normalizeText($element->text());
        return $text !== '' ? $text : null;
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
}
