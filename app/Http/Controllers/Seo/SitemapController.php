<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Support\Seo\SeoIndexingPolicy;
use App\Support\Seo\SitemapBuilder;
use Illuminate\Http\Response;

final class SitemapController extends Controller
{
    public function __invoke(
        SeoIndexingPolicy $indexing,
        SitemapBuilder $sitemap,
    ): Response {
        abort_unless($indexing->sitemapEnabled(), 404);

        return response()->view(
            'seo.sitemap',
            [
                'entries' => $sitemap->entries(),
            ],
            200,
            [
                'Content-Type' => 'application/xml; charset=UTF-8',
            ],
        );
    }
}