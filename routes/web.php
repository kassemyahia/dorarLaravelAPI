<?php

use App\Http\Controllers\Api\BookSearchController;
use App\Http\Controllers\Api\DataController;
use App\Http\Controllers\Api\HadithSearchController;
use App\Http\Controllers\Api\MohdithSearchController;
use App\Http\Controllers\Api\SharhSearchController;
use App\Http\Middleware\NormalizeQueryOptions;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/docs', 302));

Route::get('/docs', function () {
    return response()->json([
        'status' => 'success',
        'swagger' => '/api-docs',
        'openapi' => '/api-docs/openapi.yaml',
        'github' => 'https://github.com/AhmedElTabarani/dorar-hadith-api',
        'basePath' => '/v1',
    ]);
});

Route::view('/api-docs', 'api-docs');

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::middleware([NormalizeQueryOptions::class])->group(function () {
        Route::get('/api/hadith/search', [HadithSearchController::class, 'searchUsingAPIDorar']);
        Route::get('/site/hadith/search', [HadithSearchController::class, 'searchUsingSiteDorar']);
        Route::get('/site/sharh/search', [SharhSearchController::class, 'getAllSharhUsingSiteDorar']);
        Route::get('/site/sharh/text/{text}', [SharhSearchController::class, 'getOneSharhByTextUsingSiteDorar']);
    });

    Route::get('/site/hadith/similar/{id}', [HadithSearchController::class, 'getAllSimilarHadithUsingSiteDorar']);
    Route::get('/site/hadith/alternate/{id}', [HadithSearchController::class, 'getAlternateHadithUsingSiteDorar']);
    Route::get('/site/hadith/usul/{id}', [HadithSearchController::class, 'getUsulHadithUsingSiteDorar']);
    Route::get('/site/hadith/{id}', [HadithSearchController::class, 'getOneHadithUsingSiteDorarById']);

    Route::get('/site/sharh/{id}', [SharhSearchController::class, 'getOneSharhByIdUsingSiteDorar']);
    Route::get('/site/book/{id}', [BookSearchController::class, 'getOneBookByIdUsingSiteDorar']);
    Route::get('/site/mohdith/{id}', [MohdithSearchController::class, 'getOneMohdithByIdUsingSiteDorar']);

    Route::get('/data/book', [DataController::class, 'getBook']);
    Route::get('/data/degree', [DataController::class, 'getDegree']);
    Route::get('/data/methodSearch', [DataController::class, 'getMethodSearch']);
    Route::get('/data/mohdith', [DataController::class, 'getMohdith']);
    Route::get('/data/rawi', [DataController::class, 'getRawi']);
    Route::get('/data/zoneSearch', [DataController::class, 'getZoneSearch']);
});
