<?php

namespace App\Features\Forms\Services;

use App\Integrations\EngageCore\Data\FormSubmissionVerification;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

final class TurnstileFormVerifier
{
    public function verify(Request $request, string $action): ?FormSubmissionVerification
    {
        $token = $request->input('cf-turnstile-response');
        $secret = config('site_forms.turnstile.secret_key');
        $siteKey = config('site_forms.turnstile.site_key');

        if (! is_string($token) || trim($token) === '' || strlen($token) > 2048
            || ! is_string($secret) || trim($secret) === ''
            || ! is_string($siteKey) || trim($siteKey) === ''
            || preg_match('/^[A-Za-z0-9_-]{1,32}$/D', $action) !== 1
        ) {
            return null;
        }

        try {
            $response = Http::asForm()
                ->connectTimeout(2)
                ->timeout(max(1, (int) config('site_forms.turnstile.timeout_seconds', 5)))
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ]);

            if (! $response->successful() || $response->json('success') !== true) {
                return null;
            }

            // Cloudflare's official test keys return fixed test metadata.
            // Accept a successful test response only in local/testing environments.
            if (app()->environment('local', 'testing')
                && $siteKey === '1x00000000000000000000AA'
                && $secret === '1x0000000000000000000000000000000AA'
            ) {
                return new FormSubmissionVerification(
                    provider: 'turnstile',
                    verifiedAt: CarbonImmutable::now()->toDateTimeImmutable(),
                    hostname: strtolower($request->getHost()),
                    action: $action,
                );
            }

            if ($response->json('action') !== $action) {
                return null;
            }

            $hostname = $response->json('hostname');
            $challengeTime = $response->json('challenge_ts');
            if (! is_string($hostname) || ! is_string($challengeTime)
                || ! hash_equals(strtolower($request->getHost()), strtolower($hostname))
            ) {
                return null;
            }

            $verifiedAt = CarbonImmutable::parse($challengeTime);
            if ($verifiedAt->isAfter(now()->addSeconds(60))
                || $verifiedAt->isBefore(now()->subSeconds(
                    max(30, (int) config('site_forms.turnstile.max_age_seconds', 300)),
                ))
            ) {
                return null;
            }

            return new FormSubmissionVerification(
                provider: 'turnstile',
                verifiedAt: $verifiedAt->toDateTimeImmutable(),
                hostname: strtolower($hostname),
                action: $action,
            );
        } catch (Throwable) {
            return null; // Provider/network/configuration failures must deny submission.
        }
    }
}