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
            collectionId: $d['collection_id'] ?? '',
            externalId: $d['external_id'] ?? '',
            metadata: is_array($d['metadata'] ?? null) ? $d['metadata'] : [],
            imageUrl: $d['image_url'] ?? null,
            createdAt: $d['created_at'] ?? '',
        );
    }
}
