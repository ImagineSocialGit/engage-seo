<?php

namespace App\Integrations\EngageCore\Services;

use App\Integrations\EngageCore\Exceptions\EngageCoreFormsException;

final class EngageCoreFormsConfiguration
{
    private const CLIENT_ID_PATTERN = '/^[a-z][a-z0-9_.-]*$/D';

    /**
     * @return array{
     *     base_url: string,
     *     client_key: string,
     *     signing_secret: string,
     *     forms_path: string,
     *     timeout_seconds: int,
     *     connect_timeout_seconds: int,
     *     retry_attempts: int,
     *     retry_sleep_milliseconds: int
     * }
     */
    public function resolve(?bool $production = null): array
    {
        $production ??= app()->environment('production');

        $baseUrl = rtrim(trim((string) config('engage_core.base_url')), '/');
        $clientKey = trim((string) config('engage_core.client_key'));
        $signingSecret = (string) config('engage_core.signing_secret');
        $formsPath = '/'.ltrim(
            trim((string) config('engage_core.forms.path', '/forms')),
            '/',
        );

        $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
        $host = parse_url($baseUrl, PHP_URL_HOST);
        $basePath = parse_url($baseUrl, PHP_URL_PATH);
        $query = parse_url($baseUrl, PHP_URL_QUERY);
        $fragment = parse_url($baseUrl, PHP_URL_FRAGMENT);

        if (! is_string($host)
            || $host === ''
            || ! in_array($scheme, ['http', 'https'], true)
            || ($basePath !== null && $basePath !== '' && $basePath !== '/')
            || $query !== null
            || $fragment !== null
        ) {
            throw new EngageCoreFormsException(
                EngageCoreFormsException::CONFIGURATION,
                'ENGAGE_SEO_CORE_FORMS_URL must be a complete http or https origin without a path, query, or fragment.',
            );
        }

        if ($production && $scheme !== 'https') {
            throw new EngageCoreFormsException(
                EngageCoreFormsException::CONFIGURATION,
                'ENGAGE_SEO_CORE_FORMS_URL must use https in production.',
            );
        }

        if ($clientKey === ''
            || strlen($clientKey) > 128
            || preg_match(self::CLIENT_ID_PATTERN, $clientKey) !== 1
        ) {
            throw new EngageCoreFormsException(
                EngageCoreFormsException::CONFIGURATION,
                'ENGAGE_SEO_CORE_FORMS_CLIENT_ID must contain the lowercase Engage Core external Forms client ID.',
            );
        }

        if (strlen($signingSecret) < 32) {
            throw new EngageCoreFormsException(
                EngageCoreFormsException::CONFIGURATION,
                'ENGAGE_SEO_CORE_FORMS_SIGNING_SECRET must contain at least 32 characters.',
            );
        }

        if ($formsPath === '/'
            || str_contains($formsPath, '..')
            || str_contains($formsPath, '?')
            || str_contains($formsPath, '#')
        ) {
            throw new EngageCoreFormsException(
                EngageCoreFormsException::CONFIGURATION,
                'ENGAGE_CORE_FORMS_PATH must be an absolute path without query or fragment components.',
            );
        }

        return [
            'base_url' => $baseUrl,
            'client_key' => $clientKey,
            'signing_secret' => $signingSecret,
            'forms_path' => rtrim($formsPath, '/'),
            'timeout_seconds' => max(
                1,
                (int) config('engage_core.forms.timeout_seconds', 5),
            ),
            'connect_timeout_seconds' => max(
                1,
                (int) config(
                    'engage_core.forms.connect_timeout_seconds',
                    2,
                ),
            ),
            'retry_attempts' => max(
                1,
                (int) config('engage_core.forms.retry_attempts', 2),
            ),
            'retry_sleep_milliseconds' => max(
                0,
                (int) config(
                    'engage_core.forms.retry_sleep_milliseconds',
                    150,
                ),
            ),
        ];
    }
}