<?php

namespace LiveXFace\DTO;

/** Attributes of one detected face. Optional analyses may be null. */
final class FaceAttributes
{
    public function __construct(
        public readonly int $age,
        public readonly string $gender,
        public readonly float $detScore,
        public readonly array $bbox,
        public readonly ?array $landmarks5pt,
        public readonly ?array $landmarks106,
        public readonly ?array $headPose,
        public readonly ?array $emotion,
        public readonly ?array $glasses,
        public readonly ?array $mask,
    ) {
    }

    public static function fromArray(array $d): self
    {
        return new self(
            age: (int) ($d['age'] ?? 0),
            gender: $d['gender'] ?? '',
            detScore: (float) ($d['detScore'] ?? 0),
            bbox: $d['bbox'] ?? [],
            landmarks5pt: $d['landmarks5pt'] ?? null,
            landmarks106: $d['landmarks106'] ?? null,
            headPose: $d['headPose'] ?? null,
            emotion: $d['emotion'] ?? null,
            glasses: $d['glasses'] ?? null,
            mask: $d['mask'] ?? null,
        );
    }
}
