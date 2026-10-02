<?php

namespace App\Console\Commands;

use App\Support\Clients\ClientBootstrapService;
use Illuminate\Console\Command;
use Throwable;

final class BootstrapClientCommand extends Command
{
    protected $signature = 'client:bootstrap';

    protected $description = 'Apply immutable source-controlled initial state for the selected Engage SEO client';

    public function handle(ClientBootstrapService $bootstrap): int
    {
        try {
            $results = $bootstrap->run();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($results === []) {
            $this->info('No selected-client bootstrap sources are configured.');

            return self::SUCCESS;
        }

        $this->table(
            ['Contributor', 'Version', 'Status'],
            array_map(
                static fn (array $result): array => [
                    $result['contributor'],
                    (string) $result['version'],
                    $result['status'],
                ],
                $results,
            ),
        );

        $this->info('Selected-client bootstrap is current.');

        return self::SUCCESS;
    }
}