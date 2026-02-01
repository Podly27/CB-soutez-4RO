<?php

declare(strict_types=1);

namespace App\Services\Cbpmr;

use DiDom\Document;

class CbpmrShareParser
{
    private const MAX_PREVIEW_ROWS = 3;

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
}
