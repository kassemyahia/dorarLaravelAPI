<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\HadithIdRequest;
use App\Http\Requests\Api\HadithSearchRequest;
use App\Services\Dorar\HadithSearchService;

class HadithSearchController extends BaseApiController
{
    public function __construct(private readonly HadithSearchService $service)
    {
    }

    public function searchUsingAPIDorar(HadithSearchRequest $request)
    {
        $response = $this->service->searchUsingAPIDorar(
            $request->query(),
            (bool) $request->attributes->get('isRemoveHTML', true),
        );

        return $this->sendSuccess(200, $response['data'], [
            ...$response['metadata'],
            'isCached' => $response['isCached'],
        ]);
    }

    public function searchUsingSiteDorar(HadithSearchRequest $request)
    {
        $response = $this->service->searchUsingSiteDorar(
            $request->query(),
            (string) $request->attributes->get('tab', 'home'),
            (bool) $request->attributes->get('isRemoveHTML', true),
            (bool) $request->attributes->get('isForSpecialist', false),
        );

        return $this->sendSuccess(200, $response['data'], [
            ...$response['metadata'],
            'isCached' => $response['isCached'],
        ]);
    }

    public function getOneHadithUsingSiteDorarById(HadithIdRequest $request, string $id)
    {
        $response = $this->service->getOneHadithUsingSiteDorarById($id);

        return $this->sendSuccess(200, $response['data'], [
            ...$response['metadata'],
            'isCached' => $response['isCached'],
        ]);
    }

    public function getAllSimilarHadithUsingSiteDorar(HadithIdRequest $request, string $id)
    {
        $response = $this->service->getAllSimilarHadithUsingSiteDorar($id);

        return $this->sendSuccess(200, $response['data'], [
            ...$response['metadata'],
            'isCached' => $response['isCached'],
        ]);
    }

    public function getAlternateHadithUsingSiteDorar(HadithIdRequest $request, string $id)
    {
        $response = $this->service->getAlternateHadithUsingSiteDorar($id);

        return $this->sendSuccess(200, $response['data'], [
            ...$response['metadata'],
            'isCached' => $response['isCached'],
        ]);
    }

    public function getUsulHadithUsingSiteDorar(HadithIdRequest $request, string $id)
    {
        $response = $this->service->getUsulHadithUsingSiteDorar($id);

        return $this->sendSuccess(200, $response['data'], [
            ...$response['metadata'],
            'isCached' => $response['isCached'],
        ]);
    }
}
