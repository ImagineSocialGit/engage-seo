<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Support\Seo\SeoIndexingPolicy;
use App\Support\Seo\SitemapBuilder;
use Illuminate\Http\Response;

final class RobotsController extends Controller
{
    public function __invoke(
        SeoIndexingPolicy $indexing,
        SitemapBuilder $sitemap,
    ): Response {
        $lines = [
            'User-agent: *',
        ];

        if (! $indexing->siteIndexable()) {
            $lines[] = 'Disallow: /';
        } else {
            $lines[] = 'Allow: /';

            if ($indexing->sitemapEnabled()) {
                $lines[] = 'Sitemap: '.$sitemap->url();
            }
        }

        return response(
            implode(PHP_EOL, $lines).PHP_EOL,
            200,
            [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ],
        );
    }
}