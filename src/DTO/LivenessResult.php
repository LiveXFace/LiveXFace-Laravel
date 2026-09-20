<?php

namespace LiveXFace\DTO;

final class LivenessResult
{
    public function __construct(
        public readonly bool $isLive,
        public readonly float $livenessScore,
        public readonly bool $faceDetected,
        public readonly int $faceCount,
    ) {
    }

    public static function fromArray(array $d): self
    {
        return new self(
            isLive: $d['isLive'] ?? false,
            livenessScore: (float) ($d['livenessScore'] ?? 0),
            faceDetected: $d['faceDetected'] ?? false,
            faceCount: (int) ($d['faceCount'] ?? 0),
        );
    }
}
