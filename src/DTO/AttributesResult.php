<?php

namespace LiveXFace\DTO;

final class AttributesResult
{
    /** @param FaceAttributes[] $faces */
    public function __construct(
        public readonly bool $faceDetected,
        public readonly int $faceCount,
        public readonly ?FaceAttributes $primary,
        public readonly array $faces,
        public readonly ?array $imageSize,
    ) {
    }

    public static function fromArray(array $d): self
    {
        return new self(
            faceDetected: $d['faceDetected'] ?? false,
            faceCount: (int) ($d['faceCount'] ?? 0),
            primary: isset($d['primary']) ? FaceAttributes::fromArray($d['primary']) : null,
            faces: array_map(FaceAttributes::fromArray(...), $d['faces'] ?? []),
            imageSize: $d['imageSize'] ?? null,
        );
    }
}
