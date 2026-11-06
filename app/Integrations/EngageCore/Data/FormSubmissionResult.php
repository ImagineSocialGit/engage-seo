<?php

namespace App\Integrations\EngageCore\Data;

use InvalidArgumentException;

final readonly class FormSubmissionResult
{
    private const FORM_KEY_PATTERN = '/^[a-z][a-z0-9_]*$/D';

    public function __construct(
        public int $submissionId,
        public string $formKey,
        public int $formVersionId,
        public int $formVersionNumber,
        public ?int $contactId,
        public string $status,
        public ?string $submittedAt,
        public bool $replayed,
        public ?string $requestId,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(
        array $data,
        string $expectedFormKey,
        ?string $requestId = null,
    ): self {
        $submissionId = self::positiveInteger(
            $data['submission_id'] ?? null,
            'submission_id',
        );
        $formKey = self::requiredString($data['form_key'] ?? null, 'form_key');

        if (preg_match(self::FORM_KEY_PATTERN, $formKey) !== 1
            || $formKey !== $expectedFormKey
        ) {
            throw new InvalidArgumentException(
                'Form submission result form_key does not match the requested form.',
            );
        }

        $version = $data['form_version'] ?? null;

        if (! is_array($version)) {
            throw new InvalidArgumentException(
                'Form submission result form_version must be an object-shaped array.',
            );
        }

        $contactId = $data['contact_id'] ?? null;

        if ($contactId !== null && (! is_int($contactId) || $contactId < 1)) {
            throw new InvalidArgumentException(
                'Form submission result contact_id must be a positive integer or null.',
            );
        }

        $submittedAt = $data['submitted_at'] ?? null;

        if ($submittedAt !== null && ! is_string($submittedAt)) {
            throw new InvalidArgumentException(
                'Form submission result submitted_at must be a string or null.',
            );
        }

        $replayed = $data['replayed'] ?? null;

        if (! is_bool($replayed)) {
            throw new InvalidArgumentException(
                'Form submission result replayed must be a boolean.',
            );
        }

        return new self(
            submissionId: $submissionId,
            formKey: $formKey,
            formVersionId: self::positiveInteger(
                $version['id'] ?? null,
                'form_version.id',
            ),
            formVersionNumber: self::positiveInteger(
                $version['number'] ?? null,
                'form_version.number',
            ),
            contactId: $contactId,
            status: self::requiredString($data['status'] ?? null, 'status'),
            submittedAt: $submittedAt,
            replayed: $replayed,
            requestId: $requestId,
        );
    }

    private static function positiveInteger(mixed $value, string $field): int
    {
        if (! is_int($value) || $value < 1) {
            throw new InvalidArgumentException(
                "Form submission result {$field} must be a positive integer.",
            );
        }

        return $value;
    }

    private static function requiredString(mixed $value, string $field): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(
                "Form submission result {$field} must be a non-empty string.",
            );
        }

        return trim($value);
    }
}