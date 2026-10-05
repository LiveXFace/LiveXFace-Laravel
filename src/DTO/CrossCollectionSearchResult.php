<?php

namespace LiveXFace\DTO;

final class CrossCollectionSearchResult
{
    /**
     * @param CrossCollectionSearchMatch[] $matches
     * @param SkippedCollection[] $skippedCollections
     */
    public function __construct(
        public readonly array $matches,
        public readonly int $queryTimeMs,
        public readonly int $collectionsSearched,
        public readonly array $skippedCollections,
    ) {
    }

    public static function fromArray(array $d): self
    {
        return new self(
            matches: array_map(CrossCollectionSearchMatch::fromArray(...), $d['matches'] ?? []),
            queryTimeMs: (int) ($d['queryTimeMs'] ?? 0),
            collectionsSearched: (int) ($d['collectionsSearched'] ?? 0),
            skippedCollections: array_map(SkippedCollection::fromArray(...), $d['skippedCollections'] ?? []),
        );
    }
}
