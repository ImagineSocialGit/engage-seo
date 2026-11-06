<?php

namespace App\Integrations\EngageCore\Services;

use App\Integrations\EngageCore\Contracts\EngageCoreFormsClient;
use App\Integrations\EngageCore\Data\FormSubmissionRequest;
use App\Integrations\EngageCore\Data\FormSubmissionResult;
use App\Integrations\EngageCore\Data\PublishedForm;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\Repository;
use InvalidArgumentException;

final class CachedEngageCoreFormsClient implements EngageCoreFormsClient
{
    public function __construct(
        private readonly HttpEngageCoreFormsClient $client,
        private readonly CacheManager $cache,
    ) {
    }

    public function publishedForm(string $formKey): PublishedForm
    {
        $formKey = trim($formKey);
        $seconds = max(
            0,
            (int) config('engage_core.forms.cache_seconds', 300),
        );

        if ($seconds === 0) {
            return $this->client->publishedForm($formKey);
        }

        $repository = $this->repository();
        $key = $this->cacheKey($formKey);
        $payload = $repository->remember(
            $key,
            now()->addSeconds($seconds),
            fn (): array => $this->client
                ->publishedForm($formKey)
                ->toArray(),
        );

        if (is_array($payload)) {
            try {
                return PublishedForm::fromArray($payload);
            } catch (InvalidArgumentException) {
                // A deploy may change the DTO contract while an older cache
                // entry is still alive. Discard it and re-resolve from Core.
            }
        }

        $repository->forget($key);
        $form = $this->client->publishedForm($formKey);
        $repository->put(
            $key,
            $form->toArray(),
            now()->addSeconds($seconds),
        );

        return $form;
    }

    public function submit(FormSubmissionRequest $submission): FormSubmissionResult
    {
        return $this->client->submit($submission);
    }

    private function repository(): Repository
    {
        $store = config('engage_core.forms.cache_store');

        return is_string($store) && trim($store) !== ''
            ? $this->cache->store(trim($store))
            : $this->cache->store();
    }

    private function cacheKey(string $formKey): string
    {
        $clientKey = trim((string) config('engage_core.client_key'));

        return implode(':', [
            'engage-core',
            'forms',
            'published',
            hash('sha256', $clientKey),
            hash('sha256', $formKey),
        ]);
    }
}