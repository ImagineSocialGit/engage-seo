<?php

namespace App\Support\Clients;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use SplFileInfo;

final class ClientBootstrapService
{
    private const RUN_TABLE = 'client_bootstrap_runs';

    public function __construct(
        private readonly Application $app,
        private readonly ClientBootstrapRegistry $registry,
    ) {
    }

    /**
     * @return list<array{
     *     contributor: string,
     *     version: int,
     *     status: 'applied'|'skipped'
     * }>
     */
    public function run(): array
    {
        $clientKey = $this->clientKey();

        if (! Schema::hasTable(self::RUN_TABLE)) {
            throw new RuntimeException(
                'Client bootstrap tracking is not installed; run migrations before client:bootstrap.'
            );
        }

        $contributors = $this->registry->keyedContributors();
        $documents = $this->documents($clientKey);

        foreach (array_keys($documents) as $key) {
            if (! array_key_exists($key, $contributors)) {
                throw new InvalidArgumentException(
                    "Selected client bootstrap source [{$key}] has no registered contributor."
                );
            }
        }

        $prepared = [];

        foreach ($documents as $key => $document) {
            $existing = $this->existingRun($clientKey, $key);

            if ($existing !== null) {
                $this->assertExistingRunMatches(
                    $existing,
                    $key,
                    $document['version'],
                    $document['checksum'],
                );

                $prepared[] = [
                    'key' => $key,
                    'version' => $document['version'],
                    'status' => 'skipped',
                    'checksum' => $document['checksum'],
                    'payload' => $document['data'],
                    'contributor' => $contributors[$key],
                ];

                continue;
            }

            $errors = $contributors[$key]->validationErrors(
                $document['data']
            );

            $this->assertValidationErrors(
                $key,
                $errors,
            );

            $prepared[] = [
                'key' => $key,
                'version' => $document['version'],
                'status' => 'applied',
                'checksum' => $document['checksum'],
                'payload' => $document['data'],
                'contributor' => $contributors[$key],
            ];
        }

        return DB::transaction(
            function () use ($clientKey, $prepared): array {
                $results = [];

                foreach ($prepared as $item) {
                    $existing = DB::table(self::RUN_TABLE)
                        ->where('client_key', $clientKey)
                        ->where('contributor_key', $item['key'])
                        ->lockForUpdate()
                        ->first();

                    if ($existing !== null) {
                        $this->assertExistingRunMatches(
                            $existing,
                            $item['key'],
                            $item['version'],
                            $item['checksum'],
                        );

                        $results[] = [
                            'contributor' => $item['key'],
                            'version' => $item['version'],
                            'status' => 'skipped',
                        ];

                        continue;
                    }

                    $item['contributor']->apply($item['payload']);

                    DB::table(self::RUN_TABLE)->insert([
                        'client_key' => $clientKey,
                        'contributor_key' => $item['key'],
                        'version' => $item['version'],
                        'checksum' => $item['checksum'],
                        'applied_at' => now(),
                    ]);

                    $results[] = [
                        'contributor' => $item['key'],
                        'version' => $item['version'],
                        'status' => 'applied',
                    ];
                }

                return $results;
            }
        );
    }

    private function clientKey(): string
    {
        $value = config('client.key');

        if (! is_string($value)
            || preg_match('/^[a-z0-9][a-z0-9_-]*$/', trim($value)) !== 1
        ) {
            throw new RuntimeException(
                'A valid selected Engage SEO client is required before running client:bootstrap.'
            );
        }

        return trim($value);
    }

    /**
     * @return array<string, array{
     *     version: int,
     *     data: array<string, mixed>,
     *     checksum: string
     * }>
     */
    private function documents(string $clientKey): array
    {
        $directory = $this->app->basePath(
            "client/{$clientKey}/bootstrap"
        );

        if (! is_dir($directory)) {
            return [];
        }

        $entries = scandir($directory);

        if ($entries === false) {
            throw new RuntimeException(
                "Unable to read selected client bootstrap directory: {$directory}"
            );
        }

        $documents = [];

        foreach ($entries as $entry) {
            if ($entry === '.'
                || $entry === '..'
                || str_starts_with($entry, '.')
            ) {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$entry;
            $info = new SplFileInfo($path);

            if (! $info->isFile()
                || strtolower($info->getExtension()) !== 'php'
            ) {
                throw new InvalidArgumentException(
                    "Selected client bootstrap directory may contain only root-level PHP source files: {$entry}"
                );
            }

            $key = $info->getBasename('.php');

            if (preg_match('/^[a-z0-9][a-z0-9_-]*$/', $key) !== 1) {
                throw new InvalidArgumentException(
                    "Selected client bootstrap filename has invalid contributor key [{$key}]."
                );
            }

            $document = require $path;

            if (! is_array($document)) {
                throw new InvalidArgumentException(
                    "Selected client bootstrap source [{$key}] must return an array."
                );
            }

            $unknown = array_values(array_diff(
                array_keys($document),
                ['version', 'data'],
            ));

            if ($unknown !== []) {
                sort($unknown);

                throw new InvalidArgumentException(
                    "Selected client bootstrap source [{$key}] contains unsupported key(s): "
                    .implode(', ', $unknown).'.'
                );
            }

            $version = $document['version'] ?? null;
            $data = $document['data'] ?? null;

            if (! is_int($version) || $version < 1) {
                throw new InvalidArgumentException(
                    "Selected client bootstrap source [{$key}.version] must be a positive integer."
                );
            }

            if (! is_array($data)) {
                throw new InvalidArgumentException(
                    "Selected client bootstrap source [{$key}.data] must be an array."
                );
            }

            $documents[$key] = [
                'version' => $version,
                'data' => $data,
                'checksum' => $this->checksum([
                    'version' => $version,
                    'data' => $data,
                ]),
            ];
        }

        ksort($documents);

        return $documents;
    }

    private function existingRun(
        string $clientKey,
        string $contributorKey,
    ): ?object {
        return DB::table(self::RUN_TABLE)
            ->where('client_key', $clientKey)
            ->where('contributor_key', $contributorKey)
            ->first();
    }

    private function assertExistingRunMatches(
        object $existing,
        string $key,
        int $version,
        string $checksum,
    ): void {
        if ((int) $existing->version === $version
            && hash_equals((string) $existing->checksum, $checksum)
        ) {
            return;
        }

        throw new RuntimeException(
            "Client bootstrap [{$key}] was already applied at version "
            .(int) $existing->version
            .' with a different source. Initial bootstrap state is immutable after application; '
            .'use the Feature editorial/promotion workflow or an explicit data migration for later changes.'
        );
    }

    /**
     * @param list<string> $errors
     */
    private function assertValidationErrors(
        string $key,
        array $errors,
    ): void {
        foreach ($errors as $error) {
            if (! is_string($error) || trim($error) === '') {
                throw new InvalidArgumentException(
                    "Client bootstrap contributor [{$key}] returned an invalid validation error."
                );
            }
        }

        if ($errors === []) {
            return;
        }

        throw new InvalidArgumentException(
            "Client bootstrap [{$key}] is invalid:\n- "
            .implode("\n- ", array_map('trim', $errors))
        );
    }

    /**
     * @param array<string, mixed> $document
     */
    private function checksum(array $document): string
    {
        try {
            return hash(
                'sha256',
                json_encode(
                    $this->canonicalize($document),
                    JSON_UNESCAPED_SLASHES
                        | JSON_UNESCAPED_UNICODE
                        | JSON_THROW_ON_ERROR,
                ),
            );
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(
                'Selected client bootstrap source must contain JSON-serializable values: '
                .$exception->getMessage()
            );
        }
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(
                fn (mixed $item): mixed => $this->canonicalize($item),
                $value,
            );
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }
}