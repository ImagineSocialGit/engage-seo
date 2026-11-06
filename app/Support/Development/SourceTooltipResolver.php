<?php

namespace App\Support\Development;

use Illuminate\Foundation\Application;

final class SourceTooltipResolver
{
    public function __construct(
        private readonly Application $app,
    ) {
    }

    public function enabled(): bool
    {
        return $this->app->environment('local')
            && config('app.debug') === true
            && config('devtools.source_tooltips') === true;
    }

    /**
     * Read only explicitly annotated PHP literals belonging to the selected
     * site's configuration and the current public page. This never evaluates
     * source files or exposes unannotated configuration values.
     *
     * @return list<array{text: string, source: string, scope: string}>
     */
    public function forCurrentRequest(): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $clientKey = config('client.key');

        if (! is_string($clientKey)
            || preg_match('/^[a-z0-9][a-z0-9_-]*$/', $clientKey) !== 1
        ) {
            return [];
        }

        $prefix = "client/{$clientKey}/config";
        $entries = self::annotatedFile(
            $this->app->basePath("{$prefix}/site.php"),
            "{$prefix}/site.php",
            'site',
        );

        $requestedPath = $this->normalizePath('/'.ltrim(request()->path(), '/'));
        $pages = config('pages', []);

        if (! is_array($pages)) {
            return $entries;
        }

        foreach ($pages as $key => $page) {
            if (! is_string($key)
                || preg_match('/^[a-z0-9][a-z0-9_-]*$/', $key) !== 1
                || ! is_array($page)
                || ! is_string($page['path'] ?? null)
                || $this->normalizePath($page['path']) !== $requestedPath
            ) {
                continue;
            }

            $relativePath = "{$prefix}/pages/{$key}.php";

            return [
                ...$entries,
                ...self::annotatedFile(
                    $this->app->basePath($relativePath),
                    $relativePath,
                    'page',
                ),
            ];
        }

        return $entries;
    }

    /**
     * @return list<array{text: string, source: string, scope: string}>
     */
    public static function annotatedFile(
        string $absolutePath,
        string $relativePath,
        string $scope,
    ): array {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return [];
        }

        $source = file_get_contents($absolutePath);

        if ($source === false) {
            return [];
        }

        $tokens = token_get_all($source);
        $stringsByLine = [];
        $annotatedLines = [];

        foreach ($tokens as $index => $token) {
            if (! is_array($token)) {
                continue;
            }

            [$kind, $value, $line] = $token;

            if ($kind === T_COMMENT
                && preg_match('/\bcopy:[a-z0-9]+(?:-[a-z0-9]+)*\b/i', $value) === 1
            ) {
                $annotatedLines[$line] = true;
            }

            if ($kind !== T_CONSTANT_ENCAPSED_STRING
                || self::isArrayKey($tokens, $index)
            ) {
                continue;
            }

            $literal = self::decodeLiteral($value);

            if ($literal === null
                || trim($literal) === ''
                || strlen($literal) > 2048
                || str_contains($literal, "\n")
                || preg_match('~^(?:/|https?://|mailto:|tel:|#)~i', $literal) === 1
            ) {
                continue;
            }

            $stringsByLine[$line][] = $literal;
        }

        $entries = [];

        foreach (array_keys($annotatedLines) as $line) {
            foreach ($stringsByLine[$line] ?? [] as $literal) {
                $entries[] = [
                    'text' => $literal,
                    'source' => "{$relativePath}:{$line}",
                    'scope' => $scope,
                ];
            }
        }

        return $entries;
    }

    private function normalizePath(string $path): string
    {
        $path = '/'.trim($path, '/');

        return $path === '/' ? $path : rtrim($path, '/');
    }

    /**
     * @param array<int, array{int, string, int}|string> $tokens
     */
    private static function isArrayKey(array $tokens, int $index): bool
    {
        for ($next = $index + 1; $next < count($tokens); $next++) {
            $token = $tokens[$next];

            if (is_array($token)
                && ($token[0] === T_WHITESPACE || $token[0] === T_COMMENT)
            ) {
                continue;
            }

            return is_array($token) && $token[0] === T_DOUBLE_ARROW;
        }

        return false;
    }

    private static function decodeLiteral(string $raw): ?string
    {
        $quote = $raw[0] ?? '';

        if (strlen($raw) < 2 || ($quote !== "'" && $quote !== '"')) {
            return null;
        }

        $value = substr($raw, 1, -1);

        if ($quote === "'") {
            return str_replace(["\\\\", "\\'"], ["\\", "'"], $value);
        }

        // Interpolated strings do not occur as one constant string token.
        return stripcslashes($value);
    }
}