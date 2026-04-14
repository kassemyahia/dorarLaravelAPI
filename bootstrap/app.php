<?php

use App\Exceptions\ApiException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $e, Request $request) {
            if (!$request->is('v1/*')) {
                return null;
            }

            $messages = $e->errors() ? array_merge(...array_values($e->errors())) : ['Validation error'];

            return response()->json([
                'status' => 'fail',
                'message' => implode('. ', $messages),
            ], 400);
        });

        $exceptions->render(function (ApiException $e, Request $request) {
            if (!$request->is('v1/*')) {
                return null;
            }

            $statusCode = $e->statusCode();

            return response()->json([
                'status' => $statusCode >= 500 ? 'error' : 'fail',
                'message' => $e->getMessage(),
            ], $statusCode);
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (!$request->is('v1/*')) {
                return null;
            }

            $path = strtolower($request->path());
            $message = 'Resource not found';
            if (str_contains($path, 'sharh/text/')) {
                $message = 'No sharh found for the given text';
            } elseif (str_contains($path, 'sharh')) {
                $message = 'Sharh not found';
            } elseif (str_contains($path, 'hadith')) {
                $message = 'Hadith not found';
            }

            return response()->json([
                'status' => 'fail',
                'message' => $message,
            ], 404);
        });

        $exceptions->render(function (\Throwable $e, Request $request) {
            if (!$request->is('v1/*')) {
                return null;
            }

            return response()->json([
                'status' => 'error',
                'message' => app()->environment('production') ? 'Something went wrong!' : $e->getMessage(),
            ], 500);
        });
    })->create();
