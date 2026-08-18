<?php

namespace App\Http\Controllers;

use App\Support\Pages\PageRepository;
use App\Support\Seo\RedirectRepository;
use App\Support\Seo\StructuredDataResolver;
use App\Support\Site\SitePresentationResolver;
use App\Support\Views\PageViewResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PublicPageController extends Controller
{
    public function __invoke(
        Request $request,
        PageRepository $pages,
        PageViewResolver $views,
        SitePresentationResolver $sitePresentation,
        RedirectRepository $redirects,
        StructuredDataResolver $structuredData,
    ): View|RedirectResponse {
        $path = '/'.ltrim($request->path(), '/');

        if ($path === '//') {
            $path = '/';
        }

        $redirect = $redirects->resolve($path);

        if ($redirect !== null) {
            return redirect()->to(
                $redirect['to'],
                $redirect['status'],
            );
        }

        $page = $pages->resolvePath($path);

        if ($page !== null) {
            $site = $sitePresentation->resolve($path);

            $page['meta']['structured_data'] = $structuredData->resolve(
                $page,
                $site,
            );

            return view($views->resolve(), [
                'page' => $page,
                'site' => $site,
            ]);
        }

        if (config('client.key') === null && $path === '/') {
            return view('welcome');
        }

        throw new NotFoundHttpException();
    }
}