<?php

namespace App\Integrations\EngageCore\Exceptions;

use RuntimeException;

final class EngageCoreDonationsException extends RuntimeException
{
    /** @param array<string, list<string>> $fields */
    public function __construct(
        public readonly string $kind,
        public readonly array $fields = [],
    ) {
        parent::__construct('Engage Core donation checkout: '.$kind);
    }
}