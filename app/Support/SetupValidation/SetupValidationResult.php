<?php

namespace App\Support\SetupValidation;

final readonly class SetupValidationResult
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        public array $errors,
    ) {
    }

    public function valid(): bool
    {
        return $this->errors === [];
    }
}