<?php

namespace App\Http\Middleware;

use App\Support\Staging\StagingAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ForceStagingAccess
{
    public function __construct(private readonly StagingAccess $access)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('staging')) {
            return $next($request);
        }

        // Public robots are always restrictive in staging; /up is registered
        // outside the web group by Laravel and remains independently available.
        if ($request->routeIs('seo.robots')
            && in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $this->privateResponse(response(
                "User-agent: *\nDisallow: /\n",
                200,
                ['Content-Type' => 'text/plain; charset=UTF-8'],
            ));
        }

        if ($request->routeIs('staging.login', 'staging.login.submit')) {
            return $this->privateResponse($next($request));
        }

        if (! $this->access->configured()) {
            return $this->privateResponse(response('Staging access unavailable.', 503));
        }

        if (! $this->access->authenticated($request)) {
            if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
                return $this->privateResponse(response('Staging access required.', 403));
            }

            $this->access->rememberIntended($request);

            return $this->privateResponse(redirect('/staging-login'));
        }

        // Never expose a staging sitemap, even if client config incorrectly
        // overrides the app.env config value used by the SEO indexing policy.
        if ($request->routeIs('seo.sitemap')) {
            return $this->privateResponse(response('', 404));
        }

        return $this->privateResponse($next($request));
    }

    private function privateResponse(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');

        return $response;
    }
}