<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\BookIdRequest;
use App\Services\Dorar\BookSearchService;

class BookSearchController extends BaseApiController
{
    public function __construct(private readonly BookSearchService $service)
    {
    }

    public function getOneBookByIdUsingSiteDorar(BookIdRequest $request, string $id)
    {
        $response = $this->service->getOneBookByIdUsingSiteDorar($id);

        return $this->sendSuccess(200, $response['data'], [
            ...$response['metadata'],
            'isCached' => $response['isCached'],
        ]);
    }
}
