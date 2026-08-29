<?php

namespace App\Services\Common;

use App\Exceptions\ApiException;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class DorarHttpService
{
    public function fetchDocument(string $url): DOMXPath
    {
        $response = $this->request($url);
        $html = html_entity_decode($response->body(), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $this->toXPath($html);
    }

    public function fetchJson(string $url): mixed
    {
        $response = $this->request($url);
        $json = $response->json();

        if ($json === null && trim($response->body()) !== 'null') {
            throw new ApiException('Error parsing response', 502);
        }

        return $json;
    }

    public function toXPath(string $html): DOMXPath
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();

        return new DOMXPath($document);
    }

    private function request(string $url)
    {
        $timeoutSeconds = max(1, (int) ceil(((int) config('dorar.fetch_timeout_ms', 15000)) / 1000));
        $retries = max(1, (int) config('dorar.fetch_retries', 4));
        $baseRetryMs = max(100, (int) config('dorar.fetch_retry_base_ms', 1000));
        $transientStatuses = [429, 500, 502, 503, 504, 520, 521, 522, 523, 524, 525, 526, 530];
        $candidateUrls = [$url];
        if (str_contains($url, 'https://www.dorar.net/')) {
            $candidateUrls[] = str_replace('https://www.dorar.net/', 'https://dorar.net/', $url);
        } elseif (str_contains($url, 'https://dorar.net/')) {
            $candidateUrls[] = str_replace('https://dorar.net/', 'https://www.dorar.net/', $url);
        }

        $lastStatus = 502;
        $lastReason = 'Unknown upstream error';

        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            foreach ($candidateUrls as $candidateUrl) {
                try {
                    $response = Http::timeout($timeoutSeconds)
                        ->accept('*/*')
                        ->withHeaders([
                            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                            'Accept-Language' => 'ar,en;q=0.9',
                        ])
                        ->get($candidateUrl);

                    if ($response->successful()) {
                        return $response;
                    }

                    $status = (int) $response->status();
                    $reason = trim((string) $response->reason()) ?: '<none>';
                    $lastStatus = $status > 0 ? $status : 502;
                    $lastReason = $reason;

                    $isTransient = in_array($status, $transientStatuses, true);
                    if (! $isTransient || $attempt >= $retries) {
                        break 2;
                    }
                } catch (ConnectionException $e) {
                    $lastStatus = 502;
                    $lastReason = $e->getMessage();
                    if ($attempt >= $retries) {
                        break 2;
                    }
                }
            }

            // Linear backoff to reduce pressure on transient upstream outages.
            usleep((int) (($baseRetryMs * $attempt) * 1000));
        }

        throw new ApiException("Dorar HTTP {$lastStatus}: {$lastReason}", $lastStatus);
    }
}
