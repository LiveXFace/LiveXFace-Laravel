<?php

namespace Idemity\DTO;

final class IdentifyResult
{
    /** @param IdentifyMatch[] $matches */
    public function __construct(
        public readonly array $matches,
        public readonly int $queryTimeMs,
    ) {
    }

    public static function fromArray(array $d): self
    {
        return new self(
            matches: array_map(IdentifyMatch::fromArray(...), $d['matches'] ?? []),
            queryTimeMs: (int) ($d['queryTimeMs'] ?? 0),
        );
    }

    public function best(): ?IdentifyMatch
    {
        return $this->matches[0] ?? null;
    }
}
