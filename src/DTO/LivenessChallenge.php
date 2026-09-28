<?php

namespace LiveXFace\DTO;

/**
 * One challenge of an active liveness check (blink, head turn, passive
 * anti-spoof). $passed is null when the challenge could not be evaluated;
 * $metrics keeps every other key the API returned (scores, counts, angles).
 */
final class LivenessChallenge
{
    /** @param array<string, mixed> $metrics */
    public function __construct(
        public readonly ?bool $passed,
        public readonly bool $available,
        public readonly array $metrics,
    ) {
    }

    public static function fromArray(array $d): self
    {
        $passed = $d['passed'] ?? null;
        $available = (bool) ($d['available'] ?? false);
        unset($d['passed'], $d['available']);

        return new self(
            passed: $passed === null ? null : (bool) $passed,
            available: $available,
            metrics: $d,
        );
    }
}
