<?php

namespace App\Console\Commands;

use App\Support\SetupValidation\ClientSetupValidator;
use Illuminate\Console\Command;

class ValidateSetupCommand extends Command
{
    protected $signature = 'setup:validate';

    protected $description = 'Validate the selected Engage SEO client setup';

    public function handle(ClientSetupValidator $validator): int
    {
        $result = $validator->validate();

        if ($result->valid()) {
            $this->info('Selected Engage SEO client setup is valid.');

            return self::SUCCESS;
        }

        foreach ($result->errors as $error) {
            $this->error($error);
        }

        return self::FAILURE;
    }
}