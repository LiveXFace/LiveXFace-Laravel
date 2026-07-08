<?php

namespace Idemity;

use Idemity\Exceptions\IdemityApiException;
use Idemity\Exceptions\IdemityNetworkException;
use Idemity\Resources\CollectionsResource;
use Idemity\Resources\FacesResource;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/**
 * Idemity client.
 *
 * In Laravel, resolve it from the container (bound by IdemityServiceProvider)
 * or use the Idemity facade:
 *
 *     $result = Idemity::faces()->identify($collectionId, $imageBytes, topK: 3);
 *
 * Built on Laravel's HTTP client, so `Http::fake()` works in your tests.
 */
class IdemityClient
{
    public readonly CollectionsResource $collections;
    public readonly FacesResource $faces;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://api.idemity.com/api/v1',
        private readonly int $timeout = 30,
        private ?HttpFactory $http = null,
    ) {
        $this->http ??= new HttpFactory();
        $this->collections = new CollectionsResource($this);
        $this->faces = new FacesResource($this);
    }

    public function collections(): CollectionsResource
    {
        return $this->collections;
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
     * Unwrap the standard Idemity envelope {success, data, error, request_id}.
     *
     * @internal
     * @return array<string, mixed>
     */
    public function unwrap(Response $response): array
    {
        $body = $response->json();
        if (! is_array($body)) {
            throw new IdemityApiException('PARSE_ERROR', 'Unparseable API response', $response->status());
        }

        if ($response->successful() && ($body['success'] ?? false)) {
            $data = $body['data'] ?? [];

            return is_array($data) ? $data : [];
        }

        $error = $body['error'] ?? [];

        throw new IdemityApiException(
            $error['code'] ?? 'UNKNOWN_ERROR',
            $error['message'] ?? 'An unknown error occurred',
            $response->status(),
            $body['request_id'] ?? null,
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
            throw new IdemityNetworkException('Idemity request failed: '.$e->getMessage(), $e);
        }
    }
}
