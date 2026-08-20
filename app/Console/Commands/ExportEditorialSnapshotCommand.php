<?php

namespace App\Console\Commands;

use App\Support\Editorial\EditorialPromotionService;
use App\Support\Editorial\EditorialSnapshotCodec;
use Illuminate\Console\Command;
use Throwable;

final class ExportEditorialSnapshotCommand extends Command
{
    protected $signature = 'editorial:export
        {output? : Optional output path; defaults to protected storage}';

    protected $description = 'Export reviewed editorial state for controlled environment promotion';

    public function handle(
        EditorialPromotionService $promotion,
        EditorialSnapshotCodec $codec,
    ): int {
        try {
            $document = $promotion->exportDocument();
            $path = $this->outputPath(
                $this->argument('output'),
                (string) $document['client_key'],
            );

            $codec->write($path, $document);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Editorial snapshot exported.');
        $this->line('Client: '.$document['client_key']);
        $this->line('Source environment: '.$document['source_environment']);
        $this->line('Sections: '.implode(', ', array_keys($document['sections'])));
        $this->line('Path: '.$path);
        $this->line('Checksum: '.$document['checksum']);

        if ($document['source_environment'] === 'production') {
            $this->warn(
                'This is a production rollback snapshot. Normal forward promotion should originate from staging.'
            );
        }

        return self::SUCCESS;
    }

    private function outputPath(mixed $value, string $clientKey): string
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return storage_path(
                'app/private/editorial/'
                .$clientKey
                .'-editorial-'
                .now()->utc()->format('Ymd-His-u')
                .'.json'
            );
        }

        if (! is_string($value)) {
            throw new \InvalidArgumentException(
                'Editorial export output path must be a string.'
            );
        }

        $value = trim($value);

        return str_starts_with($value, '/')
            ? $value
            : base_path($value);
    }
}