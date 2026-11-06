<?php

namespace App\Integrations\EngageCore\Exceptions;

use RuntimeException;
use Throwable;

final class EngageCoreFormsException extends RuntimeException
{
    public const CONFIGURATION = 'configuration';

    public const AUTHENTICATION = 'authentication';

    public const NOT_FOUND = 'not_found';

    public const UNAVAILABLE = 'unavailable';

    public const REQUEST_REJECTED = 'request_rejected';

    public const INVALID_RESPONSE = 'invalid_response';

    public const VALIDATION = 'validation';

    public const CONFLICT = 'conflict';

    public const RATE_LIMITED = 'rate_limited';

    /**
     * @param  array<string, list<string>>  $validationErrors
     */
    public function __construct(
        public readonly string $kind,
        string $message,
        ?Throwable $previous = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $requestId = null,
        public readonly array $validationErrors = [],
    ) {
        parent::__construct($message, previous: $previous);
    }
}