<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PerformanceHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $queries = 0;

        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $response = $next($request);

        $response->headers->set('X-Response-Time-Ms', number_format((hrtime(true) - $startedAt) / 1e6, 2, '.', ''));
        $response->headers->set('X-DB-Queries', (string) $queries);
        $response->headers->set('X-Cache', (string) $request->attributes->get('perf.cache', 'NONE'));
        $response->headers->set('X-Data-Source', (string) $request->attributes->get('perf.source', 'primary'));

        return $response;
    }
}
