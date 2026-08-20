<?php

namespace App\Support\Editorial;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class EditorialPromotionService
{
    public function __construct(
        private readonly EditorialPromotionRegistry $registry,
        private readonly EditorialSnapshotCodec $codec,
        private readonly EditorialPromotionPolicy $policy,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function exportDocument(): array
    {
        $contributors = $this->registry->keyedContributors();

        if ($contributors === []) {
            throw new RuntimeException(
                'No enabled Feature contributes promotable editorial state.'
            );
        }

        $sections = [];

        foreach ($contributors as $key => $contributor) {
            $sections[$key] = $contributor->exportSection();
        }

        return $this->codec->create($sections);
    }

    /**
     * @param array<string, mixed> $document
     * @return list<string>
     */
    public function validationErrors(array $document): array
    {
        $errors = [];
        $clientKey = config('client.key');
        $snapshotClient = $document['client_key'] ?? null;

        if (! is_string($clientKey) || trim($clientKey) === '') {
            $errors[] = 'A selected Engage SEO client is required before validating an editorial snapshot.';
        } elseif ($snapshotClient !== trim($clientKey)) {
            $errors[] = sprintf(
                'Editorial snapshot client [%s] does not match selected client [%s].',
                is_scalar($snapshotClient) ? (string) $snapshotClient : 'invalid',
                trim($clientKey),
            );
        }

        $errors = [
            ...$errors,
            ...$this->policy->importErrors($document),
        ];

        try {
            $contributors = $this->registry->keyedContributors();
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();

            return array_values(array_unique($errors));
        }

        if ($contributors === []) {
            $errors[] = 'No enabled Feature contributes promotable editorial state.';

            return array_values(array_unique($errors));
        }

        $sections = $document['sections'] ?? null;

        if (! is_array($sections) || ($sections !== [] && array_is_list($sections))) {
            $errors[] = 'Editorial snapshot [sections] must be an object keyed by contributor.';

            return array_values(array_unique($errors));
        }

        $expected = array_keys($contributors);
        $actual = array_keys($sections);

        $missing = array_values(array_diff($expected, $actual));
        $unknown = array_values(array_diff($actual, $expected));

        if ($missing !== []) {
            sort($missing);
            $errors[] = 'Editorial snapshot is missing enabled section(s): '.implode(', ', $missing).'.';
        }

        if ($unknown !== []) {
            sort($unknown);
            $errors[] = 'Editorial snapshot contains section(s) not enabled on the target: '.implode(', ', $unknown).'.';
        }

        foreach ($contributors as $key => $contributor) {
            if (! array_key_exists($key, $sections)) {
                continue;
            }

            $section = $sections[$key];

            if (! is_array($section) || array_is_list($section)) {
                $errors[] = "Editorial snapshot section [{$key}] must be an object.";

                continue;
            }

            try {
                $sectionErrors = $contributor->validationErrors($section);
            } catch (Throwable $exception) {
                $errors[] = "Editorial snapshot section [{$key}] validation failed: {$exception->getMessage()}";

                continue;
            }

            if (! is_array($sectionErrors) || ! array_is_list($sectionErrors)) {
                $errors[] = "Editorial snapshot contributor [{$key}] must return a list of validation errors.";

                continue;
            }

            foreach ($sectionErrors as $error) {
                if (! is_string($error) || trim($error) === '') {
                    $errors[] = "Editorial snapshot contributor [{$key}] returned an invalid validation error.";

                    continue;
                }

                $errors[] = "[{$key}] ".trim($error);
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * @param array<string, mixed> $document
     */
    public function apply(array $document): void
    {
        $errors = $this->validationErrors($document);

        if ($errors !== []) {
            throw new InvalidArgumentException(
                "Editorial snapshot is not safe to apply:\n- ".implode("\n- ", $errors)
            );
        }

        $sections = $document['sections'];
        $contributors = $this->registry->keyedContributors();

        DB::transaction(function () use ($contributors, $sections): void {
            foreach ($contributors as $key => $contributor) {
                $contributor->applySection($sections[$key]);
            }
        });
    }
}