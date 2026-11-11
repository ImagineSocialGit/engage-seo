<?php

namespace App\Support\Deployment\Credentials;

use RuntimeException;
use Throwable;

/** Closed diagnostics: exceptions and configuration values never enter reports. */
final class SeoCredentialRuntimeFailure extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('credential_runtime_inventory_failed');
    }

    public static function finding(Throwable $error, string $stage): string
    {
        $reasons = [
            'runtime_target_identity_mismatch', 'runtime_context_invalid', 'runtime_origin_invalid',
            'runtime_binding_ownership_invalid', 'runtime_registry_unavailable',
            'runtime_inventory_arguments_invalid',
        ];
        if ($error instanceof self && in_array($error->reason, $reasons, true)) {
            return $error->reason;
        }
        if ($stage === 'selected_environment') {
            return match ($error->getMessage()) {
                'Unsafe selected SEO client directory.' => 'selected_environment_directory_unsafe',
                'Unsafe selected SEO package configuration.' => 'selected_package_configuration_unsafe',
                'Unsafe selected SEO environment file.' => 'selected_environment_unsafe_file',
                'Selected SEO environment file is not private.' => 'selected_environment_permissions_invalid',
                'Invalid selected SEO environment contents.' => 'selected_environment_contents_invalid',
                'Invalid or duplicate selected SEO environment key.' => 'selected_environment_key_invalid',
                'Unsupported selected SEO environment syntax.',
                'Unsupported selected SEO environment quotation.',
                'Unsupported selected SEO environment escaping.' => 'selected_environment_syntax_invalid',
                default => 'selected_environment_read_failed',
            };
        }

        return $stage === 'runtime_inventory' ? 'runtime_inventory_invalid' : 'runtime_inspection_unavailable';
    }
}