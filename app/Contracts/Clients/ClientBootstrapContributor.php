<?php

namespace App\Contracts\Clients;

interface ClientBootstrapContributor
{
    public function key(): string;

    /**
     * Validate one selected-client bootstrap payload without mutating state.
     *
     * @param array<string, mixed> $payload
     * @return list<string>
     */
    public function validationErrors(array $payload): array;

    /**
     * Apply one already-validated initial-state payload.
     *
     * @param array<string, mixed> $payload
     */
    public function apply(array $payload): void;
}