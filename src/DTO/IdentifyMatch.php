<?php

namespace Serupa\DTO;

final class IdentifyMatch
{
    public function __construct(
        public readonly string $faceId,
        public readonly string $externalId,
        public readonly float $confidence,
        public readonly array $metadata,
    ) {
    }

    public static function fromArray(array $d): self
    {
        return new self(
            faceId: $d['faceId'] ?? '',
            externalId: $d['externalId'] ?? '',
            confidence: (float) ($d['confidence'] ?? 0),
            metadata: is_array($d['metadata'] ?? null) ? $d['metadata'] : [],
        );
    }
}
