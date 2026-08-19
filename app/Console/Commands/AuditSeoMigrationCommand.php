<?php

namespace App\Console\Commands;

use App\Support\Seo\Migration\SeoMigrationAuditor;
use Illuminate\Console\Command;

final class AuditSeoMigrationCommand extends Command
{
    protected $signature = 'seo:migration:audit';

    protected $description = 'Audit legacy URL coverage before replacing an existing public site';

    public function handle(SeoMigrationAuditor $auditor): int
    {
        $result = $auditor->audit();

        if (! $result->enabled) {
            foreach ($result->errors as $error) {
                $this->error($error);
            }

            if ($result->errors === []) {
                $this->error('Old-platform SEO migration is not enabled for the selected client.');
            }

            return self::FAILURE;
        }

        if ($result->entries !== []) {
            $this->table(
                ['Legacy path', 'Outcome', 'Target', 'Notes'],
                array_map(
                    static fn (array $entry): array => [
                        $entry['path'],
                        $entry['outcome'],
                        $entry['target'] ?? '',
                        $entry['notes'] ?? '',
                    ],
                    $result->entries,
                ),
            );
        }

        $counts = $result->counts();

        $this->newLine();
        $this->line("Legacy URLs: {$counts['total']}");
        $this->line("Preserved: {$counts['preserved']}");
        $this->line("Redirected: {$counts['redirected']}");
        $this->line("Retired: {$counts['retired']}");
        $this->line("Unaccounted: {$counts['unaccounted']}");

        foreach ($result->warnings as $warning) {
            $this->warn($warning);
        }

        foreach ($result->errors as $error) {
            $this->error($error);
        }

        if (! $result->valid()) {
            return self::FAILURE;
        }

        $this->info('Old-platform SEO migration coverage is complete.');

        return self::SUCCESS;
    }
}