<?php

namespace App\Support\SetupValidation;

use App\Support\Clients\ClientEnvironmentDefinition;
use App\Support\Features\FeatureManager;
use App\Support\Pages\PageRepository;
use App\Support\Sections\SectionManager;
use App\Support\Site\SitePresentationResolver;
use App\Support\Verticals\VerticalManager;
use Dotenv\Dotenv;
use Illuminate\Foundation\Application;
use Throwable;

final class ClientSetupValidator
{
    public function __construct(
        private readonly Application $app,
        private readonly FeatureManager $features,
        private readonly VerticalManager $verticals,
        private readonly PageRepository $pages,
        private readonly SectionManager $sections,
        private readonly SitePresentationResolver $sitePresentation,
    ) {
    }

    public function validate(?string $basePath = null): SetupValidationResult
    {
        $errors = [];
        $basePath ??= $this->app->basePath();

        $clientKey = config('client.key');

        if (! is_string($clientKey) || trim($clientKey) === '') {
            return new SetupValidationResult([
                'No Engage SEO client is selected.',
            ]);
        }

        $clientKey = trim($clientKey);

        if (! preg_match('/^[a-z0-9][a-z0-9_-]*$/', $clientKey)) {
            $errors[] = "Configured client key is invalid: {$clientKey}.";
        }

        $clientDirectory = rtrim($basePath, DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.'clients'
            .DIRECTORY_SEPARATOR.$clientKey;

        $requiredPaths = [
            'config/client.php' => 'file',
            'config/features.php' => 'file',
            'config/site.php' => 'file',
            'config/pages' => 'directory',
            '.env.example' => 'file',
            '.env' => 'file',
            'resources/views' => 'directory',
            'resources/images/raw' => 'directory',
        ];

        if (! is_dir($clientDirectory)) {
            $errors[] = "Selected client directory does not exist: {$clientDirectory}.";

            return new SetupValidationResult($errors);
        }

        foreach ($requiredPaths as $relativePath => $type) {
            $path = $clientDirectory.DIRECTORY_SEPARATOR.$relativePath;
            $exists = $type === 'directory'
                ? is_dir($path)
                : is_file($path);

            if (! $exists) {
                $errors[] = "Selected client is missing required {$type}: {$relativePath}.";
            }
        }

        $clientConfigPath = $clientDirectory.DIRECTORY_SEPARATOR.'config/client.php';

        if (is_file($clientConfigPath)) {
            $clientConfig = require $clientConfigPath;

            if (! is_array($clientConfig)) {
                $errors[] = 'Client config/client.php must return an array.';
            } elseif (($clientConfig['key'] ?? null) !== $clientKey) {
                $errors[] = 'Selected client key does not match config/client.php.';
            }
        }

        $featuresConfigPath = $clientDirectory.DIRECTORY_SEPARATOR.'config/features.php';

        if (is_file($featuresConfigPath)) {
            $featuresConfig = require $featuresConfigPath;

            if (! is_array($featuresConfig)) {
                $errors[] = 'Client config/features.php must return an array.';
            }
        }

        $siteConfigPath = $clientDirectory.DIRECTORY_SEPARATOR.'config/site.php';

        if (is_file($siteConfigPath)) {
            $siteConfig = require $siteConfigPath;

            if (! is_array($siteConfig)) {
                $errors[] = 'Client config/site.php must return an array.';
            }
        }

        $timezone = config('client.timezone');

        if (! is_string($timezone)
            || ! in_array($timezone, timezone_identifiers_list(), true)
        ) {
            $errors[] = 'Selected client timezone is missing or invalid.';
        }

        try {
            $this->verticals->assertValid();
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }

        try {
            $this->features->assertValid();
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }

        $errors = [
            ...$errors,
            ...$this->pages->validationErrors(),
            ...$this->sections->validationErrors(),
            ...$this->sitePresentation->validationErrors(),
        ];

        $rootEnvironmentPath = rtrim($basePath, DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.'.env';

        if (is_file($rootEnvironmentPath)) {
            $rootValues = Dotenv::createArrayBacked(
                $basePath,
                '.env',
            )->safeLoad();

            $rootConflicts = array_values(array_intersect(
                array_keys($rootValues),
                ClientEnvironmentDefinition::clientOwnedKeys(),
            ));

            sort($rootConflicts);

            if ($rootConflicts !== []) {
                $errors[] = sprintf(
                    'Root .env contains selected-client-owned key(s): %s.',
                    implode(', ', $rootConflicts),
                );
            }
        }

        $clientEnvironmentPath = $clientDirectory.DIRECTORY_SEPARATOR.'.env';

        if (is_file($clientEnvironmentPath)) {
            $clientValues = Dotenv::createArrayBacked(
                $clientDirectory,
                '.env',
            )->safeLoad();

            $unsupportedKeys = array_values(array_diff(
                array_keys($clientValues),
                ClientEnvironmentDefinition::clientOwnedKeys(),
            ));

            sort($unsupportedKeys);

            if ($unsupportedKeys !== []) {
                $errors[] = sprintf(
                    'Client .env contains root-owned or unsupported key(s): %s.',
                    implode(', ', $unsupportedKeys),
                );
            }

            $missingRequiredKeys = array_values(array_diff(
                ClientEnvironmentDefinition::requiredClientKeys(),
                array_keys($clientValues),
            ));

            sort($missingRequiredKeys);

            if ($missingRequiredKeys !== []) {
                $errors[] = sprintf(
                    'Client .env is missing required key(s): %s.',
                    implode(', ', $missingRequiredKeys),
                );
            }

            $appUrl = $clientValues['APP_URL'] ?? null;

            if (is_string($appUrl) && trim($appUrl) !== '') {
                $scheme = parse_url($appUrl, PHP_URL_SCHEME);
                $host = parse_url($appUrl, PHP_URL_HOST);

                if (! in_array($scheme, ['http', 'https'], true)
                    || ! is_string($host)
                    || trim($host) === ''
                ) {
                    $errors[] = 'Client APP_URL must be an absolute http or https URL.';
                }
            } elseif (array_key_exists('APP_URL', $clientValues)) {
                $errors[] = 'Client APP_URL must not be blank.';
            }

            foreach (['DB_DATABASE', 'DB_USERNAME'] as $requiredNonBlankKey) {
                $value = $clientValues[$requiredNonBlankKey] ?? null;

                if (array_key_exists($requiredNonBlankKey, $clientValues)
                    && (! is_string($value) || trim($value) === '')
                ) {
                    $errors[] = "Client {$requiredNonBlankKey} must not be blank.";
                }
            }
        }

        return new SetupValidationResult(array_values(array_unique($errors)));
    }
}