<?php

namespace LiveXFace\DTO;

final class Face
{
    public function __construct(
        public readonly string $id,
        public readonly string $collectionId,
        public readonly string $externalId,
        public readonly array $metadata,
        public readonly ?string $imageUrl,
        public readonly string $createdAt,
    ) {
    }

    public static function fromArray(array $d): self
    {
        return new self(
            id: $d['id'],
            collectionId: $d['collectionId'] ?? '',
            externalId: $d['externalId'] ?? '',
            metadata: is_array($d['metadata'] ?? null) ? $d['metadata'] : [],
            imageUrl: $d['imageUrl'] ?? null,
            createdAt: $d['createdAt'] ?? '',
        );
    }
}
