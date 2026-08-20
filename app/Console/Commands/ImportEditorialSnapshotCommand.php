<?php

namespace App\Console\Commands;

use App\Support\Editorial\EditorialPromotionService;
use App\Support\Editorial\EditorialSnapshotCodec;
use Illuminate\Console\Command;
use Throwable;

final class ImportEditorialSnapshotCommand extends Command
{
    protected $signature = 'editorial:import
        {snapshot : Snapshot path to apply}
        {--force : Acknowledge replacement of target editorial state}';

    protected $description = 'Apply a validated editorial promotion snapshot with an automatic rollback backup';

    public function handle(
        EditorialPromotionService $promotion,
        EditorialSnapshotCodec $codec,
    ): int {
        if (! $this->option('force')) {
            $this->error(
                'Editorial import requires --force because it replaces the target promotable editorial state.'
            );

            return self::FAILURE;
        }

        try {
            $path = $this->absolutePath($this->argument('snapshot'));
            $document = $codec->read($path);
            $errors = $promotion->validationErrors($document);

            if ($errors !== []) {
                foreach ($errors as $error) {
                    $this->error($error);
                }

                return self::FAILURE;
            }

            $backup = $promotion->exportDocument();
            $backupPath = storage_path(
                'app/private/editorial/backups/'
                .$backup['client_key']
                .'-editorial-backup-'
                .now()->utc()->format('Ymd-His-u')
                .'.json'
            );

            $codec->write($backupPath, $backup);
            $promotion->apply($document);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Editorial snapshot applied successfully.');
        $this->line('Applied snapshot: '.$path);
        $this->line('Rollback backup: '.$backupPath);

        return self::SUCCESS;
    }

    private function absolutePath(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException(
                'Editorial snapshot path must be a non-blank string.'
            );
        }

        $value = trim($value);

        return str_starts_with($value, '/')
            ? $value
            : base_path($value);
    }
}