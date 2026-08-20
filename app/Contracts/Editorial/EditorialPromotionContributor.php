<?php

namespace App\Contracts\Editorial;

interface EditorialPromotionContributor
{
    public function key(): string;

    /**
     * Return the selected Feature's reviewed/promotable editorial state.
     *
     * @return array<string, mixed>
     */
    public function exportSection(): array;

    /**
     * Validate one decoded snapshot section against the target runtime.
     *
     * @param array<string, mixed> $section
     * @return list<string>
     */
    public function validationErrors(array $section): array;

    /**
     * Replace the target Feature's promotable editorial state with the
     * already-validated snapshot section.
     *
     * @param array<string, mixed> $section
     */
    public function applySection(array $section): void;
}