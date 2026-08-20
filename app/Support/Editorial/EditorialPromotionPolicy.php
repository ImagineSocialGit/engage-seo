<?php

namespace App\Support\Editorial;

final class EditorialPromotionPolicy
{
    /**
     * @param array<string, mixed> $document
     * @return list<string>
     */
    public function importErrors(array $document): array
    {
        $target = config('app.env');
        $source = $document['source_environment'] ?? null;

        if (! is_string($target) || trim($target) === '') {
            return ['Target application environment is not configured.'];
        }

        if (! is_string($source) || trim($source) === '') {
            return ['Editorial snapshot source environment is not configured.'];
        }

        $target = trim($target);
        $source = trim($source);

        if ($target === 'production'
            && ! in_array($source, ['staging', 'production'], true)
        ) {
            return [
                "Production editorial imports may come only from staging or a production rollback snapshot; source was [{$source}].",
            ];
        }

        if ($target === 'staging') {
            return [
                'Staging does not accept editorial snapshot imports. Staging is the editorial working environment; promote forward from staging instead of replacing its state from a snapshot.',
            ];
        }

        return [];
    }
}