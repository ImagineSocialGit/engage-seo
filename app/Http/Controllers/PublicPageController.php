<?php

namespace App\Http\Controllers;

use App\Support\Pages\PageRepository;
use App\Support\Site\SitePresentationResolver;
use App\Support\Views\PageViewResolver;
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
    ): View {
        $path = '/'.ltrim($request->path(), '/');

        if ($path === '//') {
            $path = '/';
        }

        $page = $pages->resolvePath($path);

        if ($page !== null) {
            return view($views->resolve(), [
                'page' => $page,
                'site' => $sitePresentation->resolve($path),
            ]);
        }

        if (config('client.key') === null && $path === '/') {
            return view('welcome');
        }

        throw new NotFoundHttpException();
    }
}