<?php

namespace App\Http\Middleware;

use App\Support\Performance\Flags;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConditionalGet
{
    public function __construct(private readonly Flags $flags) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!$this->applies($request, $response)) {
            return $response;
        }

        $etag = '"' . sha1((string) $response->getContent()) . '"';
        $request->attributes->set('cache.revalidate', true);

        $response->headers->set('ETag', $etag);
        $response->headers->set('Cache-Control', 'private, no-cache');
        $response->headers->set('Vary', 'Authorization');

        if ($this->matches($request, $etag)) {
            $response->setNotModified();
        }

        return $response;
    }

    private function applies(Request $request, Response $response): bool
    {
        return $this->flags->enabled('etag')
            && $request->isMethod('GET')
            && $response->getStatusCode() === 200;
    }

    private function matches(Request $request, string $etag): bool
    {
        $header = (string) $request->headers->get('If-None-Match', '');

        if ($header === '') {
            return false;
        }

        foreach (explode(',', $header) as $candidate) {
            $candidate = trim(preg_replace('/^W\//', '', trim($candidate)));

            if ($candidate === '*' || $candidate === $etag) {
                return true;
            }
        }

        return false;
    }
}
