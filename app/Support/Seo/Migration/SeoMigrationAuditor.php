<?php

namespace App\Support\Seo\Migration;

use App\Support\Pages\PageRepository;
use App\Support\Seo\RedirectRepository;
use InvalidArgumentException;

final class SeoMigrationAuditor
{
    public function __construct(
        private readonly LegacyUrlInventoryRepository $inventory,
        private readonly RedirectRepository $redirects,
        private readonly PageRepository $pages,
    ) {
    }

    public function audit(?string $basePath = null): SeoMigrationAuditResult
    {
        $inspection = $this->inventory->inspect($basePath);
        $enabled = $inspection['enabled'];
        $entries = $inspection['entries'];
        $errors = $inspection['errors'];
        $warnings = [];

        if (! $enabled || $errors !== []) {
            return new SeoMigrationAuditResult(
                $enabled,
                $entries,
                $errors,
                $warnings,
            );
        }

        if ($entries === []) {
            $errors[] = 'SEO migration is enabled but the legacy URL inventory contains no URLs.';

            return new SeoMigrationAuditResult(
                true,
                [],
                $errors,
                $warnings,
            );
        }

        $redirectErrors = $this->redirects->validationErrors();
        $redirectConfigurationValid = $redirectErrors === [];

        $errors = [
            ...$errors,
            ...$redirectErrors,
        ];

        foreach ($entries as $entry) {
            $path = $entry['path'];

            if ($entry['outcome'] === 'unaccounted') {
                $errors[] = "Legacy URL [{$path}] has no migration outcome.";

                continue;
            }

            if ($entry['outcome'] === 'preserved') {
                if ($redirectConfigurationValid
                    && $this->redirects->resolve($path) !== null
                ) {
                    $errors[] = "Legacy URL [{$path}] is marked preserved but runtime redirect configuration redirects it.";
                }

                $errors = [
                    ...$errors,
                    ...$this->pageDestinationErrors(
                        $path,
                        "Preserved legacy URL [{$path}]",
                    ),
                ];

                continue;
            }

            if ($entry['outcome'] === 'redirected') {
                if (! $redirectConfigurationValid) {
                    continue;
                }

                $redirect = $this->redirects->resolve($path);

                if ($redirect === null) {
                    $errors[] = "Legacy URL [{$path}] is marked redirected but has no runtime redirect.";

                    continue;
                }

                if ($redirect['to'] !== $entry['target']) {
                    $errors[] = sprintf(
                        'Legacy URL [%s] inventory target [%s] does not match runtime redirect target [%s].',
                        $path,
                        $entry['target'],
                        $redirect['to'],
                    );
                }

                if (! in_array($redirect['status'], [301, 308], true)) {
                    $errors[] = "Legacy URL [{$path}] must use a permanent 301 or 308 runtime redirect.";
                }

                $targetPath = $this->redirects->internalTargetPath(
                    (string) $entry['target']
                );

                if ($targetPath !== null) {
                    $errors = [
                        ...$errors,
                        ...$this->pageDestinationErrors(
                            $targetPath,
                            "Redirect target [{$targetPath}] for legacy URL [{$path}]",
                        ),
                    ];
                } else {
                    $warnings[] = "Legacy URL [{$path}] redirects outside the selected site; target availability and indexability cannot be verified locally.";
                }

                continue;
            }

            if ($entry['outcome'] === 'retired') {
                if ($entry['notes'] === null) {
                    $errors[] = "Retired legacy URL [{$path}] requires a non-blank note documenting the retirement.";
                }

                if ($redirectConfigurationValid
                    && $this->redirects->resolve($path) !== null
                ) {
                    $errors[] = "Retired legacy URL [{$path}] still has a runtime redirect.";
                }

                if ($this->pages->resolvePath($path) !== null) {
                    $errors[] = "Retired legacy URL [{$path}] still resolves to a configured public page.";
                }
            }
        }

        return new SeoMigrationAuditResult(
            true,
            $entries,
            array_values(array_unique($errors)),
            array_values(array_unique($warnings)),
        );
    }

    /**
     * @return list<string>
     */
    public function validationErrors(?string $basePath = null): array
    {
        return $this->audit($basePath)->errors;
    }

    /**
     * @return list<string>
     */
    private function pageDestinationErrors(
        string $path,
        string $context,
    ): array {
        try {
            $page = $this->pages->resolvePath($path);
        } catch (InvalidArgumentException $exception) {
            return [
                "{$context} is invalid: {$exception->getMessage()}",
            ];
        }

        if ($page === null) {
            return [
                "{$context} does not resolve to a configured public page.",
            ];
        }

        $definition = $this->pages->all()[$page['key']] ?? [];
        $meta = is_array($definition)
            && is_array($definition['meta'] ?? null)
                ? $definition['meta']
                : [];

        $requestedIndexable = $meta['indexable']
            ?? config('site.seo.default_indexable', true);

        $errors = [];

        if ($requestedIndexable !== true) {
            $errors[] = "{$context} is configured as non-indexable.";
        }

        $canonical = $page['meta']['canonical'] ?? null;
        $canonicalPath = is_string($canonical)
            ? parse_url($canonical, PHP_URL_PATH)
            : null;

        if (! is_string($canonicalPath) || $canonicalPath === '') {
            $canonicalPath = '/';
        }

        try {
            $canonicalPath = $this->redirects->normalizePath($canonicalPath);
        } catch (InvalidArgumentException) {
            $errors[] = "{$context} has an invalid canonical path.";

            return $errors;
        }

        if ($canonicalPath !== $path) {
            $errors[] = "{$context} does not self-canonicalize to [{$path}].";
        }

        return $errors;
    }
}