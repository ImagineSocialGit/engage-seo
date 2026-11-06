<?php

namespace App\Integrations\EngageCore\Services;

use App\Integrations\EngageCore\Contracts\EngageCoreFormsClient;
use App\Integrations\EngageCore\Data\FormSubmissionRequest;
use App\Integrations\EngageCore\Data\FormSubmissionResult;
use App\Integrations\EngageCore\Data\PublishedForm;
use App\Integrations\EngageCore\Exceptions\EngageCoreFormsException;
use App\Integrations\EngageCore\Support\EngageCoreRequestSigner;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use JsonException;

final class HttpEngageCoreFormsClient implements EngageCoreFormsClient
{
    private const FORM_KEY_PATTERN = '/^[a-z][a-z0-9_]*$/D';

    public function __construct(
        private readonly EngageCoreRequestSigner $signer,
        private readonly EngageCoreFormsConfiguration $configuration,
    ) {
    }

    public function publishedForm(string $formKey): PublishedForm
    {
        $formKey = $this->validatedFormKey($formKey);
        $configuration = $this->configuration->resolve();
        $path = $configuration['forms_path'].'/'.rawurlencode($formKey);
        $response = $this->sendWithRetry(
            method: 'GET',
            path: $path,
            body: '',
            configuration: $configuration,
            operation: 'published-form retrieval',
        );

        $this->throwForFailure(
            response: $response,
            operation: 'published-form retrieval',
            formKey: $formKey,
        );

        $data = $this->responseData($response, 'published-form');

        try {
            $form = PublishedForm::fromArray($data);
        } catch (InvalidArgumentException $exception) {
            throw new EngageCoreFormsException(
                EngageCoreFormsException::INVALID_RESPONSE,
                'Engage Core returned a published-form response that does not satisfy the Artist Sites contract.',
                $exception,
                requestId: $this->responseRequestId($response),
            );
        }

        if ($form->key !== $formKey || ! $form->isPublic) {
            throw new EngageCoreFormsException(
                EngageCoreFormsException::INVALID_RESPONSE,
                'Engage Core returned an invalid published-form identity.',
                requestId: $this->responseRequestId($response),
            );
        }

        return $form;
    }

    public function submit(FormSubmissionRequest $submission): FormSubmissionResult
    {
        $formKey = $this->validatedFormKey($submission->formKey);
        $configuration = $this->configuration->resolve();
        $path = $configuration['forms_path'].'/'.rawurlencode($formKey).'/submissions';

        try {
            $body = $submission->body();
        } catch (JsonException $exception) {
            throw new EngageCoreFormsException(
                EngageCoreFormsException::REQUEST_REJECTED,
                'The form submission could not be serialized for Engage Core.',
                $exception,
            );
        }

        $response = $this->sendWithRetry(
            method: 'POST',
            path: $path,
            body: $body,
            configuration: $configuration,
            operation: 'form submission',
        );

        $this->throwForFailure(
            response: $response,
            operation: 'form submission',
            formKey: $formKey,
        );

        $data = $this->responseData($response, 'form-submission');

        try {
            return FormSubmissionResult::fromArray(
                data: $data,
                expectedFormKey: $formKey,
                requestId: $this->responseRequestId($response),
            );
        } catch (InvalidArgumentException $exception) {
            throw new EngageCoreFormsException(
                EngageCoreFormsException::INVALID_RESPONSE,
                'Engage Core returned a form-submission response that does not satisfy the Artist Sites contract.',
                $exception,
                requestId: $this->responseRequestId($response),
            );
        }
    }

    /**
     * @param  array{
     *     base_url: string,
     *     client_key: string,
     *     signing_secret: string,
     *     forms_path: string,
     *     timeout_seconds: int,
     *     connect_timeout_seconds: int,
     *     retry_attempts: int,
     *     retry_sleep_milliseconds: int
     * }  $configuration
     */
    private function sendWithRetry(
        string $method,
        string $path,
        string $body,
        array $configuration,
        string $operation,
    ): Response {
        $attempts = max(1, $configuration['retry_attempts']);
        $sleepMicroseconds = max(
            0,
            $configuration['retry_sleep_milliseconds'],
        ) * 1000;
        $lastConnectionException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $headers = $this->signer->headers(
                method: $method,
                requestPath: $path,
                clientId: $configuration['client_key'],
                secret: $configuration['signing_secret'],
                body: $body,
            );

            try {
                $request = Http::acceptJson()
                    ->withHeaders($headers)
                    ->connectTimeout($configuration['connect_timeout_seconds'])
                    ->timeout($configuration['timeout_seconds']);

                $response = $method === 'GET'
                    ? $request->get($configuration['base_url'].$path)
                    : $request
                        ->withBody($body, 'application/json')
                        ->post($configuration['base_url'].$path);
            } catch (ConnectionException $exception) {
                $lastConnectionException = $exception;

                if ($attempt === $attempts) {
                    break;
                }

                $this->sleep($sleepMicroseconds);

                continue;
            }

            if (! $this->isRetryableResponse($response)
                || $attempt === $attempts
            ) {
                return $response;
            }

            $this->sleep($sleepMicroseconds);
        }

        throw new EngageCoreFormsException(
            EngageCoreFormsException::UNAVAILABLE,
            "Engage Core could not be reached for {$operation}.",
            $lastConnectionException,
        );
    }

    private function sleep(int $sleepMicroseconds): void
    {
        if ($sleepMicroseconds > 0) {
            usleep($sleepMicroseconds);
        }
    }

    private function isRetryableResponse(Response $response): bool
    {
        return $response->status() === 429 || $response->serverError();
    }

    private function throwForFailure(
        Response $response,
        string $operation,
        string $formKey,
    ): void {
        if ($response->successful()) {
            return;
        }

        $status = $response->status();
        $errorCode = $this->responseErrorCode($response);
        $requestId = $this->responseRequestId($response);

        if ($status === 404) {
            throw new EngageCoreFormsException(
                EngageCoreFormsException::NOT_FOUND,
                "Engage Core has no available public form [{$formKey}].",
                errorCode: $errorCode,
                requestId: $requestId,
            );
        }

        if ($status === 401) {
            throw new EngageCoreFormsException(
                EngageCoreFormsException::AUTHENTICATION,
                'Engage Core rejected the Artist Sites service credential.',
                errorCode: $errorCode,
                requestId: $requestId,
            );
        }

        if ($status === 409) {
            throw new EngageCoreFormsException(
                EngageCoreFormsException::CONFLICT,
                'Engage Core rejected the form request because its replay identity conflicted.',
                errorCode: $errorCode,
                requestId: $requestId,
            );
        }

        if ($status === 422) {
            throw new EngageCoreFormsException(
                EngageCoreFormsException::VALIDATION,
                'Engage Core rejected the submitted form values.',
                errorCode: $errorCode,
                requestId: $requestId,
                validationErrors: $this->responseValidationErrors($response),
            );
        }

        if ($status === 429) {
            throw new EngageCoreFormsException(
                EngageCoreFormsException::RATE_LIMITED,
                'Engage Core temporarily rate-limited the form request.',
                errorCode: $errorCode,
                requestId: $requestId,
            );
        }

        if ($response->serverError()) {
            throw new EngageCoreFormsException(
                EngageCoreFormsException::UNAVAILABLE,
                "Engage Core is temporarily unavailable for {$operation}.",
                errorCode: $errorCode,
                requestId: $requestId,
            );
        }

        throw new EngageCoreFormsException(
            EngageCoreFormsException::REQUEST_REJECTED,
            "Engage Core rejected the {$operation} request.",
            errorCode: $errorCode,
            requestId: $requestId,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function responseData(Response $response, string $contract): array
    {
        $payload = $response->json();

        if (! is_array($payload) || ! is_array($payload['data'] ?? null)) {
            throw new EngageCoreFormsException(
                EngageCoreFormsException::INVALID_RESPONSE,
                "Engage Core returned an invalid {$contract} response envelope.",
                requestId: $this->responseRequestId($response),
            );
        }

        return $payload['data'];
    }

    private function responseErrorCode(Response $response): ?string
    {
        $value = $response->json('error.code');

        return is_string($value) && trim($value) !== ''
            ? trim($value)
            : null;
    }

    private function responseRequestId(Response $response): ?string
    {
        $value = $response->json('request_id');

        return is_string($value) && trim($value) !== ''
            ? trim($value)
            : null;
    }

    /**
     * @return array<string, list<string>>
     */
    private function responseValidationErrors(Response $response): array
    {
        $errors = $response->json('error.details.errors');

        if (! is_array($errors)) {
            return [];
        }

        $normalized = [];

        foreach ($errors as $field => $messages) {
            if (! is_string($field)
                || $field === ''
                || ! is_array($messages)
            ) {
                continue;
            }

            $safeMessages = [];

            foreach ($messages as $message) {
                if (is_string($message) && trim($message) !== '') {
                    $safeMessages[] = trim($message);
                }
            }

            if ($safeMessages !== []) {
                $normalized[$field] = array_values(array_unique($safeMessages));
            }
        }

        return $normalized;
    }

    private function validatedFormKey(string $formKey): string
    {
        $formKey = trim($formKey);

        if (preg_match(self::FORM_KEY_PATTERN, $formKey) !== 1) {
            throw new EngageCoreFormsException(
                EngageCoreFormsException::CONFIGURATION,
                'Engage Core form key must use lowercase snake_case and begin with a letter.',
            );
        }

        return $formKey;
    }

}