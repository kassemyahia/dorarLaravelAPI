<?php

namespace App\Http\Controllers\Api;

use App\Services\DataService;

class DataController extends BaseApiController
{
    public function __construct(private readonly DataService $dataService)
    {
    }

    public function getBook()
    {
        $response = $this->dataService->getData('book');

        return $this->sendSuccess(200, $response['data'], [
            ...$response['metadata'],
            'isCached' => $response['isCached'],
        ]);
    }

    public function getDegree()
    {
        $response = $this->dataService->getData('degree');

        return $this->sendSuccess(200, $response['data'], [
            ...$response['metadata'],
            'isCached' => $response['isCached'],
        ]);
    }

    public function getMethodSearch()
    {
        $response = $this->dataService->getData('method-search');

        return $this->sendSuccess(200, $response['data'], [
            ...$response['metadata'],
            'isCached' => $response['isCached'],
        ]);
    }

    public function getMohdith()
    {
        $response = $this->dataService->getData('mohdith');

        return $this->sendSuccess(200, $response['data'], [
            ...$response['metadata'],
            'isCached' => $response['isCached'],
        ]);
    }

    public function getRawi()
    {
        $response = $this->dataService->getData('rawi');

        return $this->sendSuccess(200, $response['data'], [
            ...$response['metadata'],
            'isCached' => $response['isCached'],
        ]);
    }

    public function getZoneSearch()
    {
        $response = $this->dataService->getData('zone-search');

        return $this->sendSuccess(200, $response['data'], [
            ...$response['metadata'],
            'isCached' => $response['isCached'],
        ]);
    }
}
