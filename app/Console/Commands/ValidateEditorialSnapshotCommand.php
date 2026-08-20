<?php

namespace App\Console\Commands;

use App\Support\Editorial\EditorialPromotionService;
use App\Support\Editorial\EditorialSnapshotCodec;
use Illuminate\Console\Command;
use Throwable;

final class ValidateEditorialSnapshotCommand extends Command
{
    protected $signature = 'editorial:validate
        {snapshot : Snapshot path to validate against the selected target runtime}';

    protected $description = 'Validate an editorial promotion snapshot without mutating the database';

    public function handle(
        EditorialPromotionService $promotion,
        EditorialSnapshotCodec $codec,
    ): int {
        try {
            $path = $this->absolutePath($this->argument('snapshot'));
            $document = $codec->read($path);
            $errors = $promotion->validationErrors($document);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line('Client: '.$document['client_key']);
        $this->line('Source environment: '.$document['source_environment']);
        $this->line('Generated: '.$document['generated_at']);
        $this->line('Sections: '.implode(', ', array_keys($document['sections'])));
        $this->line('Checksum: '.$document['checksum']);

        foreach ($errors as $error) {
            $this->error($error);
        }

        if ($errors !== []) {
            return self::FAILURE;
        }

        $this->info('Editorial snapshot is valid for the selected target runtime.');

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