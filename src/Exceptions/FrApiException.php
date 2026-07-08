<?php

namespace FrApiaas\Exceptions;

use RuntimeException;

/**
 * Raised for any non-2xx FR-APIaaS response. Carries the API error code
 * (e.g. NO_FACE_DETECTED, SEAT_LIMIT_REACHED, RATE_LIMIT_EXCEEDED), the HTTP
 * status, and the request id for support correlation.
 */
class FrApiException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status,
        public readonly ?string $requestId = null,
    ) {
        parent::__construct($message, $status);
    }

    public function isNoFaceDetected(): bool
    {
        return $this->errorCode === 'NO_FACE_DETECTED';
    }

    public function isRateLimited(): bool
    {
        return $this->status === 429;
    }
}
