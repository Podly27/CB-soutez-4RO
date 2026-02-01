<?php

declare(strict_types=1);

namespace App\Services\Cbpmr;

class CbpmrShareFetcher
{
    public const DEFAULT_TIMEOUT_SECONDS = 18;

    public function fetch(string $url, array $options = []): array
    {
        $useCookies = $options['use_cookies'] ?? true;
        $ch = curl_init($url);
        if (! $ch) {
            return [
                'ok' => false,
                'error' => 'Failed to initialize cURL.',
                'code' => null,
                'url' => $url,
            ];
        }

        $cookieFile = null;
        if ($useCookies) {
            $cookieFile = sys_get_temp_dir() . '/cbpmr_cookie_' . bin2hex(random_bytes(6)) . '.txt';
        }

        $headers = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: cs,en;q=0.8',
        ];

        $redirectChain = [];
        $headerHandler = static function ($ch, string $header) use (&$redirectChain): int {
            if (preg_match('/^Location:\s*(.+)$/i', trim($header), $matches)) {
                $redirectChain[] = trim($matches[1]);
            }

            return strlen($header);
        };

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => self::DEFAULT_TIMEOUT_SECONDS,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADERFUNCTION => $headerHandler,
        ]);

        if ($cookieFile) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        }

        $body = curl_exec($ch);
        $curlError = null;
        $curlCode = null;
        if ($body === false) {
            $curlError = curl_error($ch);
            $curlCode = curl_errno($ch);
        }

        $info = curl_getinfo($ch);
        curl_close($ch);

        if ($cookieFile && file_exists($cookieFile)) {
            @unlink($cookieFile);
        }

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
                'final_url' => $finalUrl,
                'redirect_chain' => $redirectChain,
            ];
        }

        return [
            'ok' => true,
            'body' => $body,
            'final_url' => $finalUrl,
            'http_status' => $httpStatus,
            'content_type' => $contentType,
            'redirect_chain' => $redirectChain,
        ];
    }
}
