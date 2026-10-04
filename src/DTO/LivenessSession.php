<?php

namespace LiveXFace\DTO;

/**
 * A liveness session: the steps the person must perform, in order, before
 * $expiresAt. Each step is "blink", "turn_left" or "turn_right" (the
 * person's own left and right).
 */
final class LivenessSession
{
    /** @param string[] $challenges */
    public function __construct(
        public readonly string $sessionId,
        public readonly array $challenges,
        public readonly string $expiresAt,
    ) {
    }

    public static function fromArray(array $d): self
    {
        return new self(
            sessionId: $d['sessionId'] ?? '',
            challenges: array_map(fn ($c) => $c['type'], $d['challenges'] ?? []),
            expiresAt: $d['expiresAt'] ?? '',
        );
    }
}
