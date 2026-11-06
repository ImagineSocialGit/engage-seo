<?php

namespace App\Integrations\EngageCore\Data;

use InvalidArgumentException;

final readonly class PublishedForm
{
    private const KEY_PATTERN = '/^[a-z][a-z0-9_]*$/D';

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $rules
     * @param  array<string, mixed>  $layout
     * @param  array<string, mixed>  $settings
     * @param  list<array<string, mixed>>  $fields
     */
    public function __construct(
        public int $definitionId,
        public int $versionId,
        public int $versionNumber,
        public string $key,
        public string $name,
        public ?string $description,
        public string $category,
        public bool $isPublic,
        public array $schema,
        public array $rules,
        public array $layout,
        public array $settings,
        public array $fields,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $definitionId = self::positiveInteger(
            $payload['definition_id'] ?? null,
            'definition_id',
        );
        $versionId = self::positiveInteger(
            $payload['version_id'] ?? null,
            'version_id',
        );
        $versionNumber = self::positiveInteger(
            $payload['version_number'] ?? null,
            'version_number',
        );
        $key = self::requiredString($payload['key'] ?? null, 'key');

        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw new InvalidArgumentException(
                'Published form key must use lowercase snake_case and begin with a letter.',
            );
        }

        $name = self::requiredString($payload['name'] ?? null, 'name');
        $description = self::nullableString(
            $payload['description'] ?? null,
            'description',
        );
        $category = self::requiredString(
            $payload['category'] ?? null,
            'category',
        );
        $isPublic = $payload['is_public'] ?? null;

        if (! is_bool($isPublic)) {
            throw new InvalidArgumentException(
                'Published form is_public must be a boolean.',
            );
        }

        $schema = self::associativeArray(
            $payload['schema'] ?? null,
            'schema',
        );
        $rules = self::arrayValue($payload['rules'] ?? null, 'rules');
        $layout = self::arrayValue($payload['layout'] ?? null, 'layout');
        $settings = self::arrayValue(
            $payload['settings'] ?? null,
            'settings',
        );
        $fields = self::fieldList($payload['fields'] ?? null);

        return new self(
            definitionId: $definitionId,
            versionId: $versionId,
            versionNumber: $versionNumber,
            key: $key,
            name: $name,
            description: $description,
            category: $category,
            isPublic: $isPublic,
            schema: $schema,
            rules: $rules,
            layout: $layout,
            settings: $settings,
            fields: $fields,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'definition_id' => $this->definitionId,
            'version_id' => $this->versionId,
            'version_number' => $this->versionNumber,
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'is_public' => $this->isPublic,
            'schema' => $this->schema,
            'rules' => $this->rules,
            'layout' => $this->layout,
            'settings' => $this->settings,
            'fields' => $this->fields,
        ];
    }

    /**
     * @return list<string>
     */
    public function fieldKeys(): array
    {
        return array_values(array_map(
            static fn (array $field): string => (string) $field['key'],
            $this->fields,
        ));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function field(string $key): ?array
    {
        foreach ($this->fields as $field) {
            if (($field['key'] ?? null) === $key) {
                return $field;
            }
        }

        return null;
    }

    private static function positiveInteger(mixed $value, string $field): int
    {
        if (! is_int($value) || $value < 1) {
            throw new InvalidArgumentException(
                "Published form {$field} must be a positive integer.",
            );
        }

        return $value;
    }

    private static function requiredString(mixed $value, string $field): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(
                "Published form {$field} must be a non-empty string.",
            );
        }

        return trim($value);
    }

    private static function nullableString(mixed $value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException(
                "Published form {$field} must be a string or null.",
            );
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array<string, mixed>
     */
    private static function associativeArray(mixed $value, string $field): array
    {
        if (! is_array($value) || array_is_list($value)) {
            throw new InvalidArgumentException(
                "Published form {$field} must be an object-shaped array.",
            );
        }

        return $value;
    }

    /**
     * @return array<mixed>
     */
    private static function arrayValue(mixed $value, string $field): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException(
                "Published form {$field} must be an array.",
            );
        }

        return $value;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function fieldList(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw new InvalidArgumentException(
                'Published form fields must be a list.',
            );
        }

        $keys = [];

        foreach ($value as $index => $field) {
            if (! is_array($field)) {
                throw new InvalidArgumentException(
                    "Published form field {$index} must be an array.",
                );
            }

            $key = self::requiredString(
                $field['key'] ?? null,
                "fields.{$index}.key",
            );

            if (preg_match(self::KEY_PATTERN, $key) !== 1) {
                throw new InvalidArgumentException(
                    "Published form field key [{$key}] is invalid.",
                );
            }

            if (isset($keys[$key])) {
                throw new InvalidArgumentException(
                    "Published form contains duplicate field key [{$key}].",
                );
            }

            $keys[$key] = true;

            self::requiredString(
                $field['label'] ?? null,
                "fields.{$index}.label",
            );
            self::requiredString(
                $field['type'] ?? null,
                "fields.{$index}.type",
            );

            if (! array_key_exists('required', $field)
                || ! is_bool($field['required'])
            ) {
                throw new InvalidArgumentException(
                    "Published form field [{$key}] required must be a boolean.",
                );
            }
        }

        /** @var list<array<string, mixed>> $value */
        return $value;
    }
}