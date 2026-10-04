<?php

namespace LiveXFace\Resources;

use LiveXFace\DTO\ActiveLivenessResult;
use LiveXFace\DTO\AttributesResult;
use LiveXFace\DTO\BatchJob;
use LiveXFace\DTO\BatchResponse;
use LiveXFace\DTO\Face;
use LiveXFace\DTO\IdentifyResult;
use LiveXFace\DTO\LivenessResult;
use LiveXFace\DTO\LivenessSession;
use LiveXFace\DTO\LivenessSessionResult;
use LiveXFace\DTO\VerifyResult;
use LiveXFace\Exceptions\LiveXFaceApiException;
use LiveXFace\LiveXFaceClient;

/**
 * Face enrollment and recognition operations, scoped to a collection.
 *
 * Images are raw bytes (e.g. file_get_contents(...) or an uploaded file's
 * ->get()); JPEG and PNG are supported.
 */
class FacesResource
{
    public function __construct(private readonly LiveXFaceClient $client)
    {
    }

    /**
     * Enroll a face into a collection. Pass the $livenessToken from a passed
     * completeLivenessSession() when the collection requires liveness.
     *
     * An $idempotencyKey (e.g. LiveXFaceClient::generateIdempotencyKey())
     * makes a repeat of this call within 24 hours replay the first answer
     * instead of enrolling the face twice.
     */
    public function register(
        string $collectionId,
        string $image,
        string $externalId,
        array $metadata = [],
        string $filename = 'image.jpg',
        ?string $livenessToken = null,
        ?string $idempotencyKey = null,
    ): Face {
        $fields = ['external_id' => $externalId];
        if ($metadata !== []) {
            $fields['metadata'] = json_encode($metadata);
        }
        if ($livenessToken !== null) {
            $fields['liveness_token'] = $livenessToken;
        }

        $data = $this->client->call(
            fn ($req) => $req->attach('image', $image, $filename)
                ->post("/collections/{$collectionId}/faces", $fields),
            $idempotencyKey,
            idempotent: true,
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

    /** 1:1 verification — compare a probe image against a stored face. */
    public function verify(
        string $collectionId,
        string $image,
        string $faceId,
        ?float $threshold = null,
        string $filename = 'image.jpg',
    ): VerifyResult {
        $fields = ['face_id' => $faceId];
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

    /**
     * Stateless active liveness: analyze 5 to 50 frames (raw JPEG/PNG bytes,
     * in capture order) for a blink, a head turn and passive anti-spoofing.
     * Returns a verdict only and issues no liveness token; to enrol with
     * liveness, use createLivenessSession() and completeLivenessSession().
     *
     * @param string[] $frames
     */
    public function activeLiveness(string $collectionId, array $frames): ActiveLivenessResult
    {
        $data = $this->client->call(
            fn ($req) => $this->attachFrames($req, $frames)
                ->post("/collections/{$collectionId}/active-liveness")
        );

        return ActiveLivenessResult::fromArray($data);
    }

    /**
     * Start a liveness session bound to this collection. Show the person its
     * $challenges in order, capture frames while they perform them, and pass
     * the frames to completeLivenessSession() before $expiresAt (60 s by
     * default).
     */
    public function createLivenessSession(string $collectionId): LivenessSession
    {
        // No body: post() would send an empty JSON array.
        $data = $this->client->call(
            fn ($req) => $req->send('POST', "/collections/{$collectionId}/liveness-sessions")
        );

        return LivenessSession::fromArray($data);
    }

    /**
     * Submit 5 to 50 frames (raw JPEG/PNG bytes, in capture order) for a
     * liveness session, once. A pass carries a single-use livenessToken for
     * register() or a batch entry. Set $mirrored when the frames are
     * horizontally mirrored, as a selfie preview is.
     *
     * Any submission except one with fewer than 5 frames uses the session
     * up, so this call is never retried after it may have reached the server
     * (network error, 5xx), even with retries on: on SERVICE_BUSY or a
     * network error, create a new session. 422 LIVENESS_SESSION_INVALID means
     * the session is unknown, expired, already used or bound elsewhere.
     *
     * @param string[] $frames
     */
    public function completeLivenessSession(
        string $collectionId,
        string $sessionId,
        array $frames,
        bool $mirrored = false,
    ): LivenessSessionResult {
        $data = $this->client->call(
            fn ($req) => $this->attachFrames($req, $frames)
                ->post("/collections/{$collectionId}/liveness-sessions/{$sessionId}", [
                    'mirrored' => $mirrored ? 'true' : 'false',
                ]),
            singleUse: true,
        );

        return LivenessSessionResult::fromArray($data);
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
     * @param array<int, array{external_id: string, image: string, metadata?: array, filename?: string, liveness_token?: string}> $items
     */
    public function batchRegister(string $collectionId, array $items, ?string $idempotencyKey = null): BatchResponse
    {
        $data = $this->client->call(
            fn ($req) => $this->attachBatch($req, $items)
                ->post("/collections/{$collectionId}/faces/batch", [
                    'entries' => $this->batchEntries($items),
                ]),
            $idempotencyKey,
            idempotent: true,
        );

        return BatchResponse::fromArray($data);
    }

    /**
     * Submit up to 100 faces for asynchronous registration. Returns the job
     * immediately; poll getBatchJob() until $job->isFinished().
     *
     * @param array<int, array{external_id: string, image: string, metadata?: array, filename?: string, liveness_token?: string}> $items
     */
    public function batchRegisterAsync(string $collectionId, array $items, ?string $idempotencyKey = null): BatchJob
    {
        $data = $this->client->call(
            fn ($req) => $this->attachBatch($req, $items)
                ->post("/collections/{$collectionId}/faces/batch-async", [
                    'entries' => $this->batchEntries($items),
                ]),
            $idempotencyKey,
            idempotent: true,
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

    private function attachFrames(object $req, array $frames): object
    {
        foreach (array_values($frames) as $i => $frame) {
            $req = $req->attach("frame_{$i}", $frame, "frame_{$i}.jpg");
        }

        return $req;
    }

    private function attachBatch(object $req, array $items): object
    {
        foreach (array_values($items) as $i => $item) {
            if (! isset($item['external_id'], $item['image'])) {
                throw new LiveXFaceApiException('INVALID_INPUT', "batch item {$i} needs external_id and image", 0);
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
        // The entries are JSON, so the key is camelCase. It was external_id,
        // which the API does not read, falling back to the file's name. That
        // hid the bug by default, since each file is named after its external
        // id, but a batch that passed its own filenames was enrolled under
        // names like "IMG_0192" instead of the ids it gave.
        return json_encode(array_map(
            fn ($item) => [
                'externalId' => $item['external_id'],
                'metadata' => (object) ($item['metadata'] ?? []),
            ] + (isset($item['liveness_token']) ? ['livenessToken' => $item['liveness_token']] : []),
            array_values($items),
        ));
    }
}
