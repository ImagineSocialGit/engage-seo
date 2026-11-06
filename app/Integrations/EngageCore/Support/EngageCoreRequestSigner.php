<?php

namespace App\Integrations\EngageCore\Support;

use Illuminate\Support\Str;
use InvalidArgumentException;

final class EngageCoreRequestSigner
{
    public const VERSION = 'v1';

    /**
     * @return array<string, string>
     */
    public function headers(
        string $method,
        string $requestPath,
        string $clientId,
        string $secret,
        string $body = '',
        ?int $timestamp = null,
        ?string $nonce = null,
    ): array {
        $method = strtoupper(trim($method));
        $requestPath = trim($requestPath);
        $clientId = trim($clientId);
        $nonce = strtolower(trim($nonce ?? (string) Str::uuid()));
        $timestamp ??= now()->getTimestamp();

        if ($method === ''
            || $requestPath === ''
            || ! str_starts_with($requestPath, '/')
            || str_contains($requestPath, '?')
            || str_contains($requestPath, '#')
            || $clientId === ''
            || $secret === ''
            || $timestamp < 1
            || ! Str::isUuid($nonce)
        ) {
            throw new InvalidArgumentException(
                'Engage Core signing requires a method, path without query/fragment, client ID, secret, timestamp, and UUID nonce.',
            );
        }

        $signature = hash_hmac(
            'sha256',
            $this->canonicalRequest(
                method: $method,
                requestPath: $requestPath,
                clientId: $clientId,
                timestamp: $timestamp,
                nonce: $nonce,
                body: $body,
            ),
            $secret,
        );

        return [
            'X-Engage-Client' => $clientId,
            'X-Engage-Timestamp' => (string) $timestamp,
            'X-Engage-Nonce' => $nonce,
            'X-Engage-Signature' => self::VERSION.'='.$signature,
        ];
    }

    public function canonicalRequest(
        string $method,
        string $requestPath,
        string $clientId,
        int $timestamp,
        string $nonce,
        string $body = '',
    ): string {
        return implode("\n", [
            self::VERSION,
            trim($clientId),
            (string) $timestamp,
            strtolower(trim($nonce)),
            strtoupper(trim($method)),
            trim($requestPath),
            strtolower(hash('sha256', $body)),
        ]);
    }
}