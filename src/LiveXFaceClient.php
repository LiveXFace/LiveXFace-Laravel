<?php

namespace LiveXFace;

use LiveXFace\Exceptions\LiveXFaceApiException;
use LiveXFace\Exceptions\LiveXFaceNetworkException;
use LiveXFace\Resources\FacesResource;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/**
 * LiveXFace client.
 *
 * Collections are created and managed in the LiveXFace dashboard; the API has
 * no endpoints for that, so the client has no collection operations.
 *
 * In Laravel, resolve it from the container (bound by LiveXFaceServiceProvider)
 * or use the LiveXFace facade:
 *
 *     $result = LiveXFace::faces()->identify($collectionId, $imageBytes, topK: 3);
 *
 * Built on Laravel's HTTP client, so `Http::fake()` works in your tests.
 */
class LiveXFaceClient
{
    public readonly FacesResource $faces;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://api.livexface.com/api/v1',
        private readonly int $timeout = 30,
        private ?HttpFactory $http = null,
    ) {
        $this->http ??= new HttpFactory();
        $this->faces = new FacesResource($this);
    }

    public function faces(): FacesResource
    {
        return $this->faces;
    }

    /** @internal */
    public function request(): PendingRequest
    {
        return $this->http
            ->baseUrl(rtrim($this->baseUrl, '/'))
            ->withHeaders(['X-API-Key' => $this->apiKey])
            ->acceptJson()
            ->timeout($this->timeout);
    }

    /**
     * Unwrap the standard LiveXFace envelope {success, data, error, requestId}.
     *
     * @internal
     * @return array<string, mixed>
     */
    public function unwrap(Response $response): array
    {
        // A delete answers 204 with no body. That used to be reported as
        // PARSE_ERROR, so every successful delete threw.
        if ($response->status() === 204) {
            return [];
        }

        $body = $response->json();
        if (! is_array($body)) {
            // An unknown route answers with a plain-text 404, not the JSON
            // envelope; report the HTTP status rather than a parse failure.
            if (! $response->successful()) {
                throw new LiveXFaceApiException(
                    'HTTP_'.$response->status(),
                    'Request failed with HTTP '.$response->status(),
                    $response->status(),
                );
            }
            throw new LiveXFaceApiException('PARSE_ERROR', 'Unparseable API response', $response->status());
        }

        if ($response->successful() && ($body['success'] ?? false)) {
            $data = $body['data'] ?? [];

            return is_array($data) ? $data : [];
        }

        $error = $body['error'] ?? [];

        throw new LiveXFaceApiException(
            $error['code'] ?? 'UNKNOWN_ERROR',
            $error['message'] ?? 'An unknown error occurred',
            $response->status(),
            $body['requestId'] ?? null,
        );
    }

    /**
     * Send a request built by $send, translating transport failures.
     *
     * @internal
     * @param callable(PendingRequest): Response $send
     * @return array<string, mixed>
     */
    public function call(callable $send): array
    {
        try {
            return $this->unwrap($send($this->request()));
        } catch (ConnectionException $e) {
            throw new LiveXFaceNetworkException('LiveXFace request failed: '.$e->getMessage(), $e);
        }
    }
}
