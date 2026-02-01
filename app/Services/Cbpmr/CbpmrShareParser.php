<?php

declare(strict_types=1);

namespace App\Services\Cbpmr;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMNodeList;
use DOMXPath;

class CbpmrShareParser
{
    private const MAX_PREVIEW_ROWS = 3;

    public function parsePortable(string $html): array
    {
        $xpath = $this->createXPath($html);

        $title = $this->normalizeText($this->textFromXPath($xpath, '//title'));
        $title = $title !== '' ? $title : null;

        $headerText = $this->normalizeText($this->textFromXPath($xpath, '//header'));
        $date = null;
        if (preg_match('/\b\d{1,2}\.\d{1,2}\.\d{4}\b/u', $headerText, $matches)) {
            $date = $matches[0];
        }

        $expName = $title ?: ($headerText !== '' ? $headerText : null);
        $locator = $this->normalizeText($this->textFromXPath($xpath, '//*[@id="locator"]'));
        $place = $this->normalizeText($this->textFromXPath($xpath, '//*[@id="place"]'));
        $distance = $this->normalizeText($this->textFromXPath($xpath, '//*[@id="distance"]'));
        $totalKm = $this->normalizeText($this->textFromXPath($xpath, '//*[@id="km"]'));

        $locator = $locator !== '' ? $locator : null;
        $place = $place !== '' ? $place : null;

        $qsoCount = $this->extractInt($distance);
        $totalKmValue = $this->extractInt($totalKm);

        $rows = $xpath->query('//table[@id="myTable"]//tbody//tr');
        $parsedRows = [];
        $foundRowsCount = 0;

        if ($rows instanceof DOMNodeList) {
            foreach ($rows as $row) {
                if (! $row instanceof DOMElement) {
                    continue;
                }
                $cells = $this->filterElements($xpath->query('./td', $row));
                if (count($cells) < 2) {
                    continue;
                }

                $foundRowsCount++;

                $time = isset($cells[1]) ? $this->normalizeText($cells[1]->textContent) : null;
                $name = $this->firstTextMatch($xpath->query('.//span[contains(@class, "duplicity-name")]', $row));
                $remoteLocator = $this->firstTextMatch($xpath->query('.//span[contains(@class, "font-caption-locator-small")]', $row));
                $noteNode = $this->firstNodeMatch($xpath->query('.//br/following-sibling::span[contains(@class, "font-ariel")][1]', $row));
                if (! $noteNode) {
                    $noteNode = $this->nthNodeMatch($xpath->query('.//span[contains(@class, "font-ariel")]', $row), 1);
                }
                $note = $noteNode ? $this->normalizeText($noteNode->textContent) : null;
                $kmText = $this->firstTextMatch($xpath->query('.//p[contains(@class, "span-km")]', $row));
                $km = $this->extractInt($kmText);

                $parsedRows[] = [
                    'time' => $time !== '' ? $time : null,
                    'name' => $name !== '' ? $name : null,
                    'note' => $note !== '' ? $note : null,
                    'remote_locator' => $remoteLocator !== '' ? $remoteLocator : null,
                    'km' => $km,
                ];
            }
        }

        $hasTable = $xpath->query('//table[@id="myTable"]')->length > 0;
        $qsoCount = $qsoCount ?? ($foundRowsCount > 0 ? $foundRowsCount : null);

        return [
            'title' => $title,
            'exp_name' => $expName,
            'date' => $date,
            'my_locator' => $locator,
            'place' => $place,
            'qso_count' => $qsoCount,
            'total_km' => $totalKmValue,
            'entries' => $parsedRows,
            'found_rows_count' => $foundRowsCount,
            'has_table' => $hasTable,
        ];
    }

    public function parse(string $html): array
    {
        $portable = $this->parsePortable($html);
        $entries = $portable['entries'] ?? [];
        $firstRows = array_slice($entries, 0, self::MAX_PREVIEW_ROWS);

        return [
            'title' => $portable['title'] ?? null,
            'detected_format' => ($portable['has_table'] ?? false) ? 'html-table' : 'unknown',
            'parsed_preview' => [
                'qso_count' => $portable['qso_count'] ?? null,
                'my_locator' => $portable['my_locator'] ?? null,
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

    private function normalizeText(?string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', $value ?? '');
        return trim($value);
    }

    private function createXPath(string $html): DOMXPath
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        return new DOMXPath($dom);
    }

    private function textFromXPath(DOMXPath $xpath, string $query): ?string
    {
        $nodes = $xpath->query($query);
        $node = $nodes instanceof DOMNodeList ? $nodes->item(0) : null;
        if (! $node instanceof DOMNode) {
            return null;
        }

        return $node->textContent;
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

    /**
     * @param DOMNodeList|null $nodes
     * @return array<int, DOMElement>
     */
    private function filterElements(?DOMNodeList $nodes): array
    {
        if (! $nodes instanceof DOMNodeList) {
            return [];
        }
        $elements = [];
        foreach ($nodes as $node) {
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }
        return $elements;
    }

    private function firstNodeMatch(?DOMNodeList $nodes): ?DOMNode
    {
        if (! $nodes instanceof DOMNodeList) {
            return null;
        }
        foreach ($nodes as $node) {
            if ($node instanceof DOMNode) {
                return $node;
            }
        }
        return null;
    }

    private function nthNodeMatch(?DOMNodeList $nodes, int $index): ?DOMNode
    {
        if (! $nodes instanceof DOMNodeList) {
            return null;
        }
        $position = 0;
        foreach ($nodes as $node) {
            if (! $node instanceof DOMNode) {
                continue;
            }
            if ($position === $index) {
                return $node;
            }
            $position++;
        }
        return null;
    }

    private function firstTextMatch(?DOMNodeList $nodes): ?string
    {
        $node = $this->firstNodeMatch($nodes);
        if (! $node) {
            return null;
        }
        $text = $this->normalizeText($node->textContent);
        return $text !== '' ? $text : null;
    }
}
