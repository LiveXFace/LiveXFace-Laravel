<?php

namespace FrApiaas\DTO;

/** An asynchronous batch registration job. Status: queued|processing|done|failed. */
final class BatchJob
{
    /** @param BatchJobResult[] $results */
    public function __construct(
        public readonly string $id,
        public readonly string $collectionId,
        public readonly string $status,
        public readonly int $total,
        public readonly int $processed,
        public readonly int $succeeded,
        public readonly int $failed,
        public readonly array $results,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['done', 'failed'], true);
    }

    public static function fromArray(array $d): self
    {
        return new self(
            id: $d['id'],
            collectionId: $d['collection_id'] ?? '',
            status: $d['status'] ?? '',
            total: (int) ($d['total'] ?? 0),
            processed: (int) ($d['processed'] ?? 0),
            succeeded: (int) ($d['succeeded'] ?? 0),
            failed: (int) ($d['failed'] ?? 0),
            results: array_map(BatchJobResult::fromArray(...), $d['results'] ?? []),
            createdAt: $d['created_at'] ?? '',
            updatedAt: $d['updated_at'] ?? '',
        );
    }
}
