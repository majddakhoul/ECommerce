<?php

namespace App\Http\Middleware;

use App\Support\Processing\ProcessingMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetProcessingMode
{
    public function __construct(private readonly ProcessingMode $processing) {}

    public function handle(Request $request, Closure $next, string $mode = ProcessingMode::SYNC): Response
    {
        $this->processing->set($mode);

        $response = $next($request);
        $response->headers->set('X-Processing-Mode', $this->processing->current());

        return $response;
    }
}
