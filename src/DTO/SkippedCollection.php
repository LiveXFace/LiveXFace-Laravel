<?php

namespace LiveXFace\DTO;

final class SkippedCollection
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $reason,
    ) {
    }

    public static function fromArray(array $d): self
    {
        return new self(
            id: $d['id'] ?? '',
            name: $d['name'] ?? '',
            reason: $d['reason'] ?? '',
        );
    }
}
