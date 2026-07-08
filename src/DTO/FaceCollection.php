<?php

namespace FrApiaas\DTO;

final class FaceCollection
{
    public function __construct(
        public readonly string $id,
        public readonly string $organizationId,
        public readonly string $name,
        public readonly string $description,
        public readonly int $faceCount,
        public readonly ?int $retentionDays,
        public readonly string $createdAt,
    ) {
    }

    public static function fromArray(array $d): self
    {
        return new self(
            id: $d['id'],
            organizationId: $d['organization_id'] ?? '',
            name: $d['name'] ?? '',
            description: $d['description'] ?? '',
            faceCount: $d['face_count'] ?? 0,
            retentionDays: $d['retention_days'] ?? null,
            createdAt: $d['created_at'] ?? '',
        );
    }
}
