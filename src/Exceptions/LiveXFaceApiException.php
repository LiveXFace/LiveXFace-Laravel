<?php

namespace LiveXFace\Exceptions;

use RuntimeException;

/**
 * Raised for any non-2xx LiveXFace response. Carries the API error code
 * (e.g. NO_FACE_DETECTED, SEAT_LIMIT_REACHED, RATE_LIMIT_EXCEEDED), the HTTP
 * status, and the request id for support correlation.
 *
 * Liveness-gated enrolment codes: LIVENESS_TOKEN_REQUIRED (400),
 * LIVENESS_TOKEN_INVALID (422), LIVENESS_FACE_MISMATCH (422).
 */
class LiveXFaceApiException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status,
        public readonly ?string $requestId = null,
        /** Machine-readable context when the API sends it, e.g. faceCount and faces for MULTIPLE_FACES. */
        public readonly ?array $details = null,
    ) {
        parent::__construct($message, $status);
    }

    public function isNoFaceDetected(): bool
    {
        return $this->errorCode === 'NO_FACE_DETECTED';
    }

    /** The token was missing, invalid/expired/reused, or not this face. */
    public function isLivenessTokenError(): bool
    {
        return in_array($this->errorCode, [
            'LIVENESS_TOKEN_REQUIRED',
            'LIVENESS_TOKEN_INVALID',
            'LIVENESS_FACE_MISMATCH',
        ], true);
    }

    public function isRateLimited(): bool
    {
        return $this->status === 429;
    }
}
