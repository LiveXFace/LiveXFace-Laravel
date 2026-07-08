<?php

namespace FrApiaas\DTO;

/** Outcome for one image of an async batch job. */
final class BatchJobResult
{
    public function __construct(
        public readonly int $index,
        public readonly string $externalId,
        public readonly ?string $faceId,
        public readonly ?string $error,
    ) {
    }

    public static function fromArray(array $d): self
    {
        return new self(
            index: (int) ($d['index'] ?? 0),
            externalId: $d['external_id'] ?? '',
            faceId: $d['face_id'] ?? null,
            error: $d['error'] ?? null,
        );
    }
}
