<?php

namespace App\Support\Editorial;

use DateTimeImmutable;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Throwable;

final class EditorialSnapshotCodec
{
    public const FORMAT = 'engage-seo-editorial-snapshot';

    public const VERSION = 1;

    public function __construct(
        private readonly Filesystem $files,
    ) {
    }

    /**
     * @param array<string, array<string, mixed>> $sections
     * @return array<string, mixed>
     */
    public function create(array $sections): array
    {
        $clientKey = config('client.key');

        if (! is_string($clientKey) || trim($clientKey) === '') {
            throw new RuntimeException(
                'A selected Engage SEO client is required before exporting editorial state.'
            );
        }

        $environment = config('app.env');

        if (! is_string($environment) || trim($environment) === '') {
            throw new RuntimeException(
                'Application environment is required before exporting editorial state.'
            );
        }

        $document = [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'client_key' => trim($clientKey),
            'source_environment' => trim($environment),
            'generated_at' => now()->utc()->toIso8601String(),
            'sections' => $sections,
        ];

        $document['checksum'] = $this->checksum($document);

        return $document;
    }

    /**
     * @return array<string, mixed>
     */
    public function read(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException(
                "Editorial snapshot does not exist: {$path}"
            );
        }

        $contents = file_get_contents($path);

        if (! is_string($contents)) {
            throw new RuntimeException(
                "Unable to read editorial snapshot: {$path}"
            );
        }

        return $this->decode($contents);
    }

    /**
     * @param array<string, mixed> $document
     */
    public function write(string $path, array $document): void
    {
        $directory = dirname($path);
        $this->files->ensureDirectoryExists($directory);

        $encoded = $this->encode($document);

        if ($this->files->put($path, $encoded) === false) {
            throw new RuntimeException(
                "Unable to write editorial snapshot: {$path}"
            );
        }
    }

    /**
     * @param array<string, mixed> $document
     */
    public function encode(array $document): string
    {
        $this->assertDocumentShape($document);

        try {
            return json_encode(
                $document,
                JSON_PRETTY_PRINT
                    | JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR,
            ).PHP_EOL;
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Unable to encode editorial snapshot: '.$exception->getMessage()
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function decode(string $contents): array
    {
        try {
            $document = json_decode(
                $contents,
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(
                'Editorial snapshot contains invalid JSON: '.$exception->getMessage()
            );
        }

        if (! is_array($document) || array_is_list($document)) {
            throw new InvalidArgumentException(
                'Editorial snapshot must be a JSON object.'
            );
        }

        $this->assertDocumentShape($document);

        $expected = $this->checksum($document);
        $actual = $document['checksum'];

        if (! hash_equals($expected, $actual)) {
            throw new InvalidArgumentException(
                'Editorial snapshot checksum does not match its contents.'
            );
        }

        return $document;
    }

    /**
     * @param array<string, mixed> $document
     */
    public function checksum(array $document): string
    {
        unset($document['checksum']);

        try {
            $encoded = json_encode(
                $this->canonicalize($document),
                JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Unable to checksum editorial snapshot: '.$exception->getMessage()
            );
        }

        return 'sha256:'.hash('sha256', $encoded);
    }

    /**
     * @param array<string, mixed> $document
     */
    private function assertDocumentShape(array $document): void
    {
        $allowed = [
            'format',
            'version',
            'client_key',
            'source_environment',
            'generated_at',
            'sections',
            'checksum',
        ];

        $unknown = array_values(array_diff(
            array_keys($document),
            $allowed,
        ));

        if ($unknown !== []) {
            sort($unknown);

            throw new InvalidArgumentException(
                'Editorial snapshot contains unsupported top-level key(s): '
                .implode(', ', $unknown).'.'
            );
        }

        if (($document['format'] ?? null) !== self::FORMAT) {
            throw new InvalidArgumentException(
                'Editorial snapshot format is unsupported.'
            );
        }

        if (($document['version'] ?? null) !== self::VERSION) {
            throw new InvalidArgumentException(
                'Editorial snapshot version is unsupported.'
            );
        }

        foreach (['client_key', 'source_environment', 'generated_at'] as $key) {
            $value = $document[$key] ?? null;

            if (! is_string($value) || trim($value) === '') {
                throw new InvalidArgumentException(
                    "Editorial snapshot [{$key}] must be a non-blank string."
                );
            }
        }

        if (preg_match(
            '/^[a-z0-9][a-z0-9_-]*$/',
            (string) $document['client_key'],
        ) !== 1) {
            throw new InvalidArgumentException(
                'Editorial snapshot [client_key] is invalid.'
            );
        }

        if (preg_match(
            '/^[a-z0-9][a-z0-9_-]*$/',
            (string) $document['source_environment'],
        ) !== 1) {
            throw new InvalidArgumentException(
                'Editorial snapshot [source_environment] is invalid.'
            );
        }

        try {
            new DateTimeImmutable((string) $document['generated_at']);
        } catch (Throwable) {
            throw new InvalidArgumentException(
                'Editorial snapshot [generated_at] is invalid.'
            );
        }

        $sections = $document['sections'] ?? null;

        if (! is_array($sections) || ($sections !== [] && array_is_list($sections))) {
            throw new InvalidArgumentException(
                'Editorial snapshot [sections] must be an object keyed by contributor.'
            );
        }

        $checksum = $document['checksum'] ?? null;

        if (! is_string($checksum)
            || preg_match('/^sha256:[a-f0-9]{64}$/', $checksum) !== 1
        ) {
            throw new InvalidArgumentException(
                'Editorial snapshot [checksum] is invalid.'
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