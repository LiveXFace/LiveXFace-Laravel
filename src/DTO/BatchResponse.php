<?php

namespace FrApiaas\DTO;

final class BatchResponse
{
    /** @param BatchFaceResult[] $results */
    public function __construct(
        public readonly int $succeeded,
        public readonly int $failed,
        public readonly array $results,
    ) {
    }

    public static function fromArray(array $d): self
    {
        return new self(
            succeeded: (int) ($d['succeeded'] ?? 0),
            failed: (int) ($d['failed'] ?? 0),
            results: array_map(BatchFaceResult::fromArray(...), $d['results'] ?? []),
        );
    }
}
