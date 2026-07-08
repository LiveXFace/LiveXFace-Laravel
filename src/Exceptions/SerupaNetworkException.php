<?php

namespace Serupa\Exceptions;

use RuntimeException;
use Throwable;

/** Raised when the request never produced an API response (DNS, timeout, TLS). */
class SerupaNetworkException extends RuntimeException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
