<?php

namespace App\Contracts\SetupValidation;

interface SetupValidationContributor
{
    /**
     * Return selected-client setup errors contributed by an optional platform
     * capability such as a Feature.
     *
     * @return list<string>
     */
    public function validationErrors(?string $basePath = null): array;
}