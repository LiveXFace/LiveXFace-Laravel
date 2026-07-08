<?php

namespace Serupa\Resources;

use Serupa\DTO\AttributesResult;
use Serupa\DTO\BatchJob;
use Serupa\DTO\BatchResponse;
use Serupa\DTO\Face;
use Serupa\DTO\IdentifyResult;
use Serupa\DTO\LivenessResult;
use Serupa\DTO\VerifyResult;
use Serupa\Exceptions\SerupaApiException;
use Serupa\SerupaClient;

/**
 * Face enrollment and recognition operations, scoped to a collection.
 *
 * Images are raw bytes (e.g. file_get_contents(...) or an uploaded file's
 * ->get()); JPEG and PNG are supported.
 */
class FacesResource
{
    public function __construct(private readonly SerupaClient $client)
    {
    }

    /** Enroll a face into a collection. */
    public function register(
        string $collectionId,
        string $image,
        string $externalId,
        array $metadata = [],
        string $filename = 'image.jpg',
    ): Face {
        $fields = ['external_id' => $externalId];
        if ($metadata !== []) {
            $fields['metadata'] = json_encode($metadata);
        }

        $data = $this->client->call(
            fn ($req) => $req->attach('image', $image, $filename)
                ->post("/collections/{$collectionId}/faces", $fields)
        );

        return Face::fromArray($data);
    }

    /** @return array{faces: Face[], total: int} */
    public function list(string $collectionId, int $limit = 50, int $offset = 0): array
    {
        $data = $this->client->call(
            fn ($req) => $req->get("/collections/{$collectionId}/faces", [
                'limit' => $limit,
                'offset' => $offset,
            ])
        );

        return [
            'faces' => array_map(Face::fromArray(...), $data['faces'] ?? []),
            'total' => $data['total'] ?? 0,
        ];
    }

    public function get(string $collectionId, string $faceId): Face
    {
        $data = $this->client->call(
            fn ($req) => $req->get("/collections/{$collectionId}/faces/{$faceId}")
        );

        return Face::fromArray($data);
    }

    public function delete(string $collectionId, string $faceId): void
    {
        $this->client->call(
            fn ($req) => $req->delete("/collections/{$collectionId}/faces/{$faceId}")
        );
    }

    /** GDPR erasure — delete all faces registered under an external id. */
    public function deleteByExternalId(string $collectionId, string $externalId): void
    {
        $this->client->call(
            fn ($req) => $req->delete("/collections/{$collectionId}/faces", [
                'external_id' => $externalId,
            ])
        );
    }

    /** 1:1 verification — compare a probe image against a stored face. */
    public function verify(
        string $collectionId,
        string $image,
        ?string $faceId = null,
        ?float $threshold = null,
        string $filename = 'image.jpg',
    ): VerifyResult {
        $fields = [];
        if ($faceId !== null) {
            $fields['face_id'] = $faceId;
        }
        if ($threshold !== null) {
            $fields['threshold'] = (string) $threshold;
        }

        $data = $this->client->call(
            fn ($req) => $req->attach('image', $image, $filename)
                ->post("/collections/{$collectionId}/verify", $fields)
        );

        return VerifyResult::fromArray($data);
    }

    /** 1:N identification — find the closest matching faces in a collection. */
    public function identify(
        string $collectionId,
        string $image,
        int $topK = 5,
        ?float $threshold = null,
        string $filename = 'image.jpg',
    ): IdentifyResult {
        $fields = ['top_k' => (string) $topK];
        if ($threshold !== null) {
            $fields['threshold'] = (string) $threshold;
        }

        $data = $this->client->call(
            fn ($req) => $req->attach('image', $image, $filename)
                ->post("/collections/{$collectionId}/identify", $fields)
        );

        return IdentifyResult::fromArray($data);
    }

    /** Passive liveness detection (anti-spoofing). */
    public function liveness(
        string $collectionId,
        string $image,
        string $filename = 'image.jpg',
    ): LivenessResult {
        $data = $this->client->call(
            fn ($req) => $req->attach('image', $image, $filename)
                ->post("/collections/{$collectionId}/liveness")
        );

        return LivenessResult::fromArray($data);
    }

    /** Compare two images directly, without enrolling either. */
    public function compare(
        string $image1,
        string $image2,
        ?float $threshold = null,
    ): VerifyResult {
        $fields = [];
        if ($threshold !== null) {
            $fields['threshold'] = (string) $threshold;
        }

        $data = $this->client->call(
            fn ($req) => $req->attach('image1', $image1, 'image1.jpg')
                ->attach('image2', $image2, 'image2.jpg')
                ->post('/compare', $fields)
        );

        return VerifyResult::fromArray($data);
    }

    /**
     * Detect face attributes (age, gender, emotion, glasses, mask, head pose,
     * landmarks) for all faces in an image. No face is enrolled.
     */
    public function attributes(
        string $collectionId,
        string $image,
        string $filename = 'image.jpg',
    ): AttributesResult {
        $data = $this->client->call(
            fn ($req) => $req->attach('image', $image, $filename)
                ->post("/collections/{$collectionId}/attributes")
        );

        return AttributesResult::fromArray($data);
    }

    /**
     * Synchronously register up to 20 faces in one request.
     *
     * @param array<int, array{external_id: string, image: string, metadata?: array, filename?: string}> $items
     */
    public function batchRegister(string $collectionId, array $items): BatchResponse
    {
        $data = $this->client->call(
            fn ($req) => $this->attachBatch($req, $items)
                ->post("/collections/{$collectionId}/faces/batch", [
                    'entries' => $this->batchEntries($items),
                ])
        );

        return BatchResponse::fromArray($data);
    }

    /**
     * Submit up to 100 faces for asynchronous registration. Returns the job
     * immediately; poll getBatchJob() until $job->isFinished().
     *
     * @param array<int, array{external_id: string, image: string, metadata?: array, filename?: string}> $items
     */
    public function batchRegisterAsync(string $collectionId, array $items): BatchJob
    {
        $data = $this->client->call(
            fn ($req) => $this->attachBatch($req, $items)
                ->post("/collections/{$collectionId}/faces/batch-async", [
                    'entries' => $this->batchEntries($items),
                ])
        );

        return BatchJob::fromArray($data);
    }

    /** Fetch the status (and per-image results) of an async batch job. */
    public function getBatchJob(string $collectionId, string $jobId): BatchJob
    {
        $data = $this->client->call(
            fn ($req) => $req->get("/collections/{$collectionId}/batch/{$jobId}")
        );

        return BatchJob::fromArray($data);
    }

    private function attachBatch(object $req, array $items): object
    {
        foreach (array_values($items) as $i => $item) {
            if (! isset($item['external_id'], $item['image'])) {
                throw new SerupaApiException('INVALID_INPUT', "batch item {$i} needs external_id and image", 0);
            }
            $req = $req->attach(
                "images[{$i}]",
                $item['image'],
                $item['filename'] ?? "{$item['external_id']}.jpg",
            );
        }

        return $req;
    }

    private function batchEntries(array $items): string
    {
        return json_encode(array_map(
            fn ($item) => [
                'external_id' => $item['external_id'],
                'metadata' => (object) ($item['metadata'] ?? []),
            ],
            array_values($items),
        ));
    }
}
