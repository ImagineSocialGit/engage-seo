<?php

namespace App\Integrations\EngageCore\Data;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

final readonly class FormSubmissionVerification
{
    private const ID_PATTERN = '/^[a-z][a-z0-9_.-]*$/D';

    public string $provider;

    public ?string $hostname;

    public ?string $action;

    public function __construct(
        string $provider,
        public DateTimeImmutable $verifiedAt,
        ?string $hostname = null,
        ?string $action = null,
    ) {
        $provider = strtolower(trim($provider));

        if (preg_match(self::ID_PATTERN, $provider) !== 1
            || mb_strlen($provider) > 64
        ) {
            throw new InvalidArgumentException(
                'Engage Core verification provider must use a lowercase provider identifier no longer than 64 characters.',
            );
        }

        $this->provider = $provider;
        $this->hostname = $this->nullableValue(
            $hostname,
            'hostname',
            253,
            lowercase: true,
        );
        $this->action = $this->nullableValue(
            $action,
            'action',
            64,
        );
    }

    /**
     * @return array{
     *     provider: string,
     *     outcome: string,
     *     verified_at: string,
     *     hostname: string|null,
     *     action: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'outcome' => 'passed',
            'verified_at' => $this->verifiedAt->format(DateTimeInterface::ATOM),
            'hostname' => $this->hostname,
            'action' => $this->action,
        ];
    }

    private function nullableValue(
        ?string $value,
        string $label,
        int $maximumLength,
        bool $lowercase = false,
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > $maximumLength
            || preg_match('/[\\x00-\\x1F\\x7F]/', $value) === 1
        ) {
            throw new InvalidArgumentException(
                "Engage Core verification {$label} is invalid.",
            );
        }

        return $lowercase ? strtolower($value) : $value;
    }
}