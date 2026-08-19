<?php

namespace App\Support\Seo\Migration;

final class SeoMigrationAuditResult
{
    /**
     * @param list<array{path: string, outcome: string, target: ?string, notes: ?string, line: int}> $entries
     * @param list<string> $errors
     * @param list<string> $warnings
     */
    public function __construct(
        public readonly bool $enabled,
        public readonly array $entries,
        public readonly array $errors,
        public readonly array $warnings,
    ) {
    }

    public function valid(): bool
    {
        return $this->errors === [];
    }

    /**
     * @return array{total: int, preserved: int, redirected: int, retired: int, unaccounted: int}
     */
    public function counts(): array
    {
        $counts = [
            'total' => count($this->entries),
            'preserved' => 0,
            'redirected' => 0,
            'retired' => 0,
            'unaccounted' => 0,
        ];

        foreach ($this->entries as $entry) {
            $outcome = $entry['outcome'];

            if (array_key_exists($outcome, $counts)) {
                $counts[$outcome]++;
            }
        }

        return $counts;
    }
}