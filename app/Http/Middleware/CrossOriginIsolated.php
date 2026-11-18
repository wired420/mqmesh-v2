<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CrossOriginIsolated
{
    /**
     * The mesh generator uses threads (SharedArrayBuffer), which browsers only
     * allow on cross-origin isolated pages. "credentialless" keeps the site's
     * third-party fonts working without them sending CORP headers.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Embedder-Policy', 'credentialless');

        return $response;
    }
}
