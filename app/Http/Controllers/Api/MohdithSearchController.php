<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\MohdithIdRequest;
use App\Services\Dorar\MohdithSearchService;

class MohdithSearchController extends BaseApiController
{
    public function __construct(private readonly MohdithSearchService $service)
    {
    }

    public function getOneMohdithByIdUsingSiteDorar(MohdithIdRequest $request, string $id)
    {
        $response = $this->service->getOneMohdithByIdUsingSiteDorar($id);

        return $this->sendSuccess(200, $response['data'], [
            ...$response['metadata'],
            'isCached' => $response['isCached'],
        ]);
    }
}
