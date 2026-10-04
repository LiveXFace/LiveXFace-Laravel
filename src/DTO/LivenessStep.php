<?php

namespace LiveXFace\DTO;

/** One step of a liveness session ("blink", "turn_left", "turn_right") and whether it was performed in order. */
final class LivenessStep
{
    public function __construct(
        public readonly string $type,
        public readonly bool $passed,
    ) {
    }

    public static function fromArray(array $d): self
    {
        return new self(type: $d['type'] ?? '', passed: (bool) ($d['passed'] ?? false));
    }
}
