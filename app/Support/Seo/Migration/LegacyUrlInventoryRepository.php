<?php

namespace App\Support\Seo\Migration;

use App\Support\Seo\RedirectRepository;
use Illuminate\Foundation\Application;
use InvalidArgumentException;

final class LegacyUrlInventoryRepository
{
    public function __construct(
        private readonly Application $app,
        private readonly RedirectRepository $redirects,
    ) {
    }

    /**
     * @return array{
     *     enabled: bool,
     *     entries: list<array{path: string, outcome: string, target: ?string, notes: ?string, line: int}>,
     *     errors: list<string>
     * }
     */
    public function inspect(?string $basePath = null): array
    {
        $errors = [];
        $enabled = config('seo_migration.enabled', false);

        if (! is_bool($enabled)) {
            return [
                'enabled' => false,
                'entries' => [],
                'errors' => [
                    'SEO migration [enabled] must be a boolean.',
                ],
            ];
        }

        $configuredPath = config(
            'seo_migration.inventory_path',
            'resources/migration/legacy-urls.tsv',
        );

        try {
            $relativePath = $this->normalizeInventoryPath($configuredPath);
        } catch (InvalidArgumentException $exception) {
            return [
                'enabled' => $enabled,
                'entries' => [],
                'errors' => [
                    $exception->getMessage(),
                ],
            ];
        }

        if (! $enabled) {
            return [
                'enabled' => false,
                'entries' => [],
                'errors' => [],
            ];
        }

        $clientKey = config('client.key');

        if (! is_string($clientKey) || trim($clientKey) === '') {
            return [
                'enabled' => true,
                'entries' => [],
                'errors' => [
                    'SEO migration requires a selected client.',
                ],
            ];
        }

        $basePath ??= $this->app->basePath();

        $inventoryPath = rtrim($basePath, DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.'client'
            .DIRECTORY_SEPARATOR.trim($clientKey)
            .DIRECTORY_SEPARATOR.str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $relativePath,
            );

        if (! is_file($inventoryPath)) {
            return [
                'enabled' => true,
                'entries' => [],
                'errors' => [
                    "SEO migration inventory is missing: {$relativePath}.",
                ],
            ];
        }

        $handle = fopen($inventoryPath, 'rb');

        if ($handle === false) {
            return [
                'enabled' => true,
                'entries' => [],
                'errors' => [
                    "Unable to read SEO migration inventory: {$relativePath}.",
                ],
            ];
        }

        try {
            $header = fgetcsv($handle, 0, "\t", '"', '');

            if (is_array($header) && isset($header[0])) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
            }

            if ($header !== ['path', 'outcome', 'target', 'notes']) {
                return [
                    'enabled' => true,
                    'entries' => [],
                    'errors' => [
                        'SEO migration inventory header must be exactly: path, outcome, target, notes.',
                    ],
                ];
            }

            $entries = [];
            $seen = [];
            $line = 1;

            while (($row = fgetcsv($handle, 0, "\t", '"', '')) !== false) {
                $line++;

                if ($this->blankRow($row)) {
                    continue;
                }

                if (count($row) !== 4) {
                    $errors[] = "SEO migration inventory line {$line} must contain exactly four tab-separated columns.";

                    continue;
                }

                [$path, $outcome, $target, $notes] = array_map(
                    static fn (mixed $value): string => trim((string) $value),
                    $row,
                );

                try {
                    $path = $this->redirects->normalizePath($path);
                } catch (InvalidArgumentException $exception) {
                    $errors[] = "SEO migration inventory line {$line}: {$exception->getMessage()}";

                    continue;
                }

                if (array_key_exists($path, $seen)) {
                    $errors[] = sprintf(
                        'SEO migration inventory contains duplicate legacy path [%s] on lines %d and %d.',
                        $path,
                        $seen[$path],
                        $line,
                    );

                    continue;
                }

                $seen[$path] = $line;
                $outcome = strtolower($outcome);

                if ($outcome === '') {
                    $outcome = 'unaccounted';
                }

                if (! in_array(
                    $outcome,
                    ['preserved', 'redirected', 'retired', 'unaccounted'],
                    true,
                )) {
                    $errors[] = "SEO migration inventory line {$line} has unsupported outcome [{$outcome}].";

                    continue;
                }

                $target = $target !== ''
                    ? $target
                    : null;
                $notes = $notes !== ''
                    ? $notes
                    : null;

                if ($outcome === 'redirected') {
                    if ($target === null) {
                        $errors[] = "SEO migration inventory line {$line} requires a redirect target.";

                        continue;
                    }

                    if (! $this->validDestination($target)) {
                        $errors[] = "SEO migration inventory line {$line} redirect target must be an absolute site path or absolute http/https URL.";

                        continue;
                    }
                } elseif ($target !== null) {
                    $errors[] = "SEO migration inventory line {$line} may define [target] only when outcome is redirected.";

                    continue;
                }

                $entries[] = [
                    'path' => $path,
                    'outcome' => $outcome,
                    'target' => $target,
                    'notes' => $notes,
                    'line' => $line,
                ];
            }
        } finally {
            fclose($handle);
        }

        return [
            'enabled' => true,
            'entries' => $entries,
            'errors' => array_values(array_unique($errors)),
        ];
    }

    private function normalizeInventoryPath(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(
                'SEO migration [inventory_path] must be a non-blank relative TSV path.'
            );
        }

        $path = str_replace('\\', '/', trim($value));

        if (str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:\//', $path) === 1
            || in_array('..', explode('/', $path), true)
            || ! str_starts_with($path, 'resources/migration/')
            || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'tsv'
        ) {
            throw new InvalidArgumentException(
                'SEO migration [inventory_path] must be a client-relative TSV path under resources/migration/.'
            );
        }

        return $path;
    }

    /**
     * @param array<int, string|null> $row
     */
    private function blankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function validDestination(string $destination): bool
    {
        if (str_starts_with($destination, '/')) {
            return true;
        }

        $scheme = parse_url($destination, PHP_URL_SCHEME);
        $host = parse_url($destination, PHP_URL_HOST);

        return in_array($scheme, ['http', 'https'], true)
            && is_string($host)
            && trim($host) !== '';
    }
}