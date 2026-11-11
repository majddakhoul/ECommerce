<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class SetEtag
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if (!config('performance.etag_enabled') || $response->getStatusCode() !== 200 || !$response->getContent()) return $response;
        $etag = '"'.sha1($response->getContent()).'"';
        $response->setEtag($etag);
        $response->headers->set('Cache-Control', 'private, max-age=0, must-revalidate');
        if ($request->headers->get('If-None-Match') === $etag) {
            $response->setNotModified();
        }
        return $response;
    }
}
