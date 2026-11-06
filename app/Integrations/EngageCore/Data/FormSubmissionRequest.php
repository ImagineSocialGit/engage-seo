<?php

namespace App\Integrations\EngageCore\Data;

use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;

final readonly class FormSubmissionRequest
{
    private const FORM_KEY_PATTERN = '/^[a-z][a-z0-9_]*$/D';

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $formKey,
        public string $externalId,
        public array $values,
        public array $meta = [],
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?FormSubmissionVerification $verification = null,
    ) {
        if (preg_match(self::FORM_KEY_PATTERN, $this->formKey) !== 1) {
            throw new InvalidArgumentException(
                'Engage Core form key must use lowercase snake_case and begin with a letter.',
            );
        }

        if (! Str::isUuid($this->externalId)) {
            throw new InvalidArgumentException(
                'Engage Core form submission external ID must be a UUID.',
            );
        }

        if (array_key_exists('_forms', $this->meta)) {
            throw new InvalidArgumentException(
                'Engage Core form submission meta key [_forms] is reserved.',
            );
        }

        if ($this->ipAddress !== null
            && filter_var($this->ipAddress, FILTER_VALIDATE_IP) === false
        ) {
            throw new InvalidArgumentException(
                'Engage Core form submission IP address is invalid.',
            );
        }

        if ($this->userAgent !== null
            && mb_strlen($this->userAgent) > 65535
        ) {
            throw new InvalidArgumentException(
                'Engage Core form submission user agent is too long.',
            );
        }
    }

    /**
     * Return the exact JSON bytes that are signed and sent to Engage Core.
     *
     * @throws JsonException
     */
    public function body(): string
    {
        $payload = [
            'external_id' => strtolower($this->externalId),
            'values' => (object) $this->values,
        ];

        if ($this->meta !== []) {
            $payload['meta'] = (object) $this->meta;
        }

        $provenance = [];

        if ($this->ipAddress !== null) {
            $provenance['ip_address'] = $this->ipAddress;
        }

        if ($this->userAgent !== null) {
            $provenance['user_agent'] = $this->userAgent;
        }

        if ($provenance !== []) {
            $payload['provenance'] = (object) $provenance;
        }

        if ($this->verification !== null) {
            $payload['verification'] = (object) $this->verification->toArray();
        }

        return json_encode(
            $payload,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }
}