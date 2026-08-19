<?php

namespace App\Support\SetupValidation;

use App\Contracts\SetupValidation\SetupValidationContributor;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Throwable;

final class SetupValidationRegistry
{
    /**
     * @var list<class-string<SetupValidationContributor>>
     */
    private array $contributors = [];

    public function __construct(
        private readonly Container $container,
    ) {
    }

    /**
     * @param class-string<SetupValidationContributor> $contributor
     */
    public function register(string $contributor): void
    {
        if (! class_exists($contributor)
            || ! is_subclass_of($contributor, SetupValidationContributor::class)
        ) {
            throw new InvalidArgumentException(
                'Setup validation contributor must implement '
                .SetupValidationContributor::class
                .": {$contributor}"
            );
        }

        if (! in_array($contributor, $this->contributors, true)) {
            $this->contributors[] = $contributor;
        }
    }

    /**
     * @return list<SetupValidationContributor>
     */
    public function contributors(): array
    {
        return array_map(
            fn (string $contributor): SetupValidationContributor => $this->container->make($contributor),
            $this->contributors,
        );
    }

    /**
     * @return list<string>
     */
    public function validationErrors(?string $basePath = null): array
    {
        $errors = [];

        foreach ($this->contributors() as $contributor) {
            try {
                $contributed = $contributor->validationErrors($basePath);
            } catch (Throwable $exception) {
                $errors[] = sprintf(
                    'Setup validation contributor [%s] failed: %s',
                    $contributor::class,
                    $exception->getMessage(),
                );

                continue;
            }

            if (! is_array($contributed) || ! array_is_list($contributed)) {
                $errors[] = sprintf(
                    'Setup validation contributor [%s] must return a list of strings.',
                    $contributor::class,
                );

                continue;
            }

            foreach ($contributed as $error) {
                if (! is_string($error) || trim($error) === '') {
                    $errors[] = sprintf(
                        'Setup validation contributor [%s] returned an invalid error value.',
                        $contributor::class,
                    );

                    continue;
                }

                $errors[] = trim($error);
            }
        }

        return array_values(array_unique($errors));
    }
}