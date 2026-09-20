<?php

namespace LiveXFace\DTO;

final class VerifyResult
{
    public function __construct(
        public readonly bool $match,
        public readonly float $confidence,
        public readonly float $thresholdUsed,
        public readonly ?string $faceId,
    ) {
    }

    public static function fromArray(array $d): self
    {
        return new self(
            match: $d['match'] ?? false,
            confidence: (float) ($d['confidence'] ?? 0),
            thresholdUsed: (float) ($d['thresholdUsed'] ?? $d['threshold'] ?? 0),
            faceId: $d['faceId'] ?? null,
        );
    }
}
