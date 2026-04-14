<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\SharhIdRequest;
use App\Http\Requests\Api\SharhSearchRequest;
use App\Http\Requests\Api\SharhTextRequest;
use App\Services\Dorar\SharhSearchService;

class SharhSearchController extends BaseApiController
{
    public function __construct(private readonly SharhSearchService $service)
    {
    }

    public function getOneSharhByIdUsingSiteDorar(SharhIdRequest $request, string $id)
    {
        $response = $this->service->getOneSharhByIdUsingSiteDorar($id);

        return $this->sendSuccess(200, $response['data'], [
            ...$response['metadata'],
            'isCached' => $response['isCached'],
        ]);
    }

    public function getOneSharhByTextUsingSiteDorar(SharhTextRequest $request, string $text)
    {
        $response = $this->service->getOneSharhByTextUsingSiteDorar(
            $text,
            (string) $request->attributes->get('tab', 'home'),
            (bool) $request->attributes->get('isForSpecialist', false),
        );

        return $this->sendSuccess(200, $response['data'], [
            ...$response['metadata'],
            'isCached' => $response['isCached'],
        ]);
    }

    public function getAllSharhUsingSiteDorar(SharhSearchRequest $request)
    {
        $response = $this->service->getAllSharhUsingSiteDorar(
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
}
