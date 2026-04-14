<?php

namespace App\Services\Common;

use App\Exceptions\ApiException;
use DOMDocument;
use DOMXPath;
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
        $response = Http::timeout($timeoutSeconds)->accept('*/*')->get($url);

        if (!$response->successful()) {
            throw new ApiException('Failed to fetch data: '.$response->reason(), $response->status());
        }

        return $response;
    }
}
