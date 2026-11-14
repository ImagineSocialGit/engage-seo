<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serve immutable-ownership client icons without copying client assets to the
 * shared platform public/ directory. URLs resolve within the selected client.
 */
final class ClientIconController extends Controller
{
    private const MIME_TYPES = [
        'favicon.svg' => 'image/svg+xml',
        'favicon.ico' => 'image/x-icon',
        'favicon-16x16.png' => 'image/png',
        'favicon-32x32.png' => 'image/png',
        'apple-touch-icon.png' => 'image/png',
        'android-chrome-192x192.png' => 'image/png',
        'android-chrome-512x512.png' => 'image/png',
        'site.webmanifest' => 'application/manifest+json',
    ];

    public function __construct(
        private readonly ?string $rootDirectory = null,
    ) {
    }

    public function __invoke(string $filename): BinaryFileResponse
    {
        $mimeType = self::MIME_TYPES[$filename] ?? null;

        if ($mimeType === null) {
            abort(404);
        }

        $clientKey = config('client.key');

        if (! is_string($clientKey)
            || preg_match('/^[a-z0-9][a-z0-9_-]*$/', $clientKey) !== 1
        ) {
            abort(404);
        }

        $root = $this->rootDirectory ?? base_path();
        $directory = $root.'/client/'.$clientKey.'/resources/favicons';
        $basePath = realpath($directory);
        $sourcePath = $directory.'/'.$filename;
        $resolvedPath = realpath($sourcePath);

        if ($basePath === false
            || $resolvedPath === false
            || ! str_starts_with($resolvedPath, $basePath.DIRECTORY_SEPARATOR)
            || ! is_file($resolvedPath)
            || is_link($sourcePath)
        ) {
            abort(404);
        }

        return response()->file($resolvedPath, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}