<?php

namespace Idemity\DTO;

final class BatchFaceResult
{
    public function __construct(
        public readonly string $externalId,
        public readonly ?Face $face,
        public readonly ?string $error,
    ) {
    }

    public static function fromArray(array $d): self
    {
        return new self(
            externalId: $d['external_id'] ?? '',
            face: isset($d['face']) ? Face::fromArray($d['face']) : null,
            error: $d['error'] ?? null,
        );
    }
}
