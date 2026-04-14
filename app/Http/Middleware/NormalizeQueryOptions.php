<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class NormalizeQueryOptions
{
    public function handle(Request $request, Closure $next)
    {
        $removeHtmlRaw = $request->query('removehtml');
        $isRemoveHtml = $removeHtmlRaw === null ? true : strtolower((string) $removeHtmlRaw) !== 'false';

        $specialistRaw = $request->query('specialist');
        $isForSpecialist = $specialistRaw === null ? false : strtolower((string) $specialistRaw) === 'true';

        $request->attributes->set('isRemoveHTML', $isRemoveHtml);
        $request->attributes->set('isForSpecialist', $isForSpecialist);
        $request->attributes->set('tab', $isForSpecialist ? 'specialist' : 'home');

        $request->query->remove('removehtml');
        $request->query->remove('specialist');

        return $next($request);
    }
}
