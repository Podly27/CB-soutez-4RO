<?php

declare(strict_types=1);

namespace App\Services\Cbpmr;

class CbpmrShareFetcher
{
    public const DEFAULT_TIMEOUT_SECONDS = 18;

    public function fetch(string $url): array
    {
        $ch = curl_init($url);
        if (! $ch) {
            return [
                'ok' => false,
                'error' => 'Failed to initialize cURL.',
                'code' => null,
                'url' => $url,
            ];
        }

        $headers = [
            'Accept: text/html,application/xhtml+xml,application/json',
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => self::DEFAULT_TIMEOUT_SECONDS,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $body = curl_exec($ch);
        $curlError = null;
        $curlCode = null;
        if ($body === false) {
            $curlError = curl_error($ch);
            $curlCode = curl_errno($ch);
        }

        $info = curl_getinfo($ch);
        curl_close($ch);

        $finalUrl = $info['url'] ?? $url;
        $httpStatus = $info['http_code'] ?? null;
        $contentType = $info['content_type'] ?? null;

        if ($body === false) {
            return [
                'ok' => false,
                'error' => $curlError ?: 'Failed to fetch remote content.',
                'code' => $curlCode,
                'url' => $finalUrl,
                'http_status' => $httpStatus,
                'content_type' => $contentType,
            ];
        }

        return [
            'ok' => true,
            'body' => $body,
            'final_url' => $finalUrl,
            'http_status' => $httpStatus,
            'content_type' => $contentType,
        ];
    }
}
