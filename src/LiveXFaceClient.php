<?php

namespace LiveXFace;

use LiveXFace\Exceptions\LiveXFaceApiException;
use LiveXFace\Exceptions\LiveXFaceNetworkException;
use LiveXFace\Resources\FacesResource;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Request;
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
 *
 * Retries are off unless $maxRetries > 0 (attempts after the first). Then a
 * 429 or 503 is retried after its Retry-After (capped at $maxRetryDelay
 * seconds) or an exponential backoff with jitter; a network error or other
 * 5xx only for GET, PATCH and DELETE and for calls carrying an idempotency
 * key; any other 4xx never. Enrolment and batch calls get one generated
 * Idempotency-Key per call, sent on every attempt, when the caller gave none.
 * A liveness-session completion is retried only on 429, since any attempt
 * that reached the server may have used the session up.
 */
class LiveXFaceClient
{
    /** The API contract (`/openapi.json` `info.version`) this release was validated against. */
    public const CONTRACT_VERSION = '2.0.0';

    public readonly FacesResource $faces;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://api.livexface.com/api/v1',
        private readonly int $timeout = 30,
        private ?HttpFactory $http = null,
        private readonly int $maxRetries = 0,
        private readonly float $maxRetryDelay = 60.0,
        /** Called with the delay in seconds before each retry; tests inject a recorder. */
        private ?\Closure $sleep = null,
    ) {
        $this->http ??= new HttpFactory();
        $this->sleep ??= static fn (float $seconds) => usleep((int) ($seconds * 1_000_000));
        $this->faces = new FacesResource($this);
    }

    public function faces(): FacesResource
    {
        return $this->faces;
    }

    /** A random UUID v4, for the $idempotencyKey of an enrolment or batch call. */
    public static function generateIdempotencyKey(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
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
                    retryAfter: self::retryAfter($response),
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
            isset($error['details']) && is_array($error['details']) ? $error['details'] : null,
            self::retryAfter($response),
        );
    }

    /** Retry-After in whole seconds; null when absent or not an integer (e.g. an HTTP date). */
    private static function retryAfter(Response $response): ?int
    {
        $value = trim($response->header('Retry-After'));

        return ctype_digit($value) ? (int) $value : null;
    }

    /**
     * Send a request built by $send, translating transport failures and
     * retrying per the client's policy. $send runs again on each attempt, so
     * multipart bodies are rebuilt. $idempotent marks enrolment and batch
     * calls, which get a generated key when retries are on and none was given.
     * $singleUse marks a call the server can act on only once (a liveness
     * session completion): it is retried only on 429, which the rate limiter
     * answers before the request is handled, never on 503 or a network error.
     *
     * @internal
     * @param callable(PendingRequest): Response $send
     * @return array<string, mixed>
     */
    public function call(callable $send, ?string $idempotencyKey = null, bool $idempotent = false, bool $singleUse = false): array
    {
        if ($idempotent && $idempotencyKey === null && $this->maxRetries > 0) {
            $idempotencyKey = self::generateIdempotencyKey();
        }

        for ($attempt = 0; ; $attempt++) {
            $method = null;
            $request = $this->request()->beforeSending(function (Request $r) use (&$method) {
                $method = $r->method();
            });
            if ($idempotencyKey !== null) {
                $request = $request->withHeaders(['Idempotency-Key' => $idempotencyKey]);
            }

            try {
                return $this->unwrap($send($request));
            } catch (ConnectionException $e) {
                $error = new LiveXFaceNetworkException('LiveXFace request failed: '.$e->getMessage(), $e);
                $status = 0;
            } catch (LiveXFaceApiException $e) {
                $error = $e;
                $status = $e->status;
            }

            // Safe to repeat after it may have run: a read, a metadata update,
            // a delete, or a call the server deduplicates by its key.
            $safe = $idempotencyKey !== null || in_array($method, ['GET', 'PATCH', 'DELETE'], true);
            $retryable = $status === 429 || ($status === 503 && ! $singleUse)
                || ($safe && ($error instanceof LiveXFaceNetworkException || $status >= 500));

            if (! $retryable || $attempt >= $this->maxRetries) {
                throw $error;
            }

            $delay = $error instanceof LiveXFaceApiException && $error->retryAfter !== null
                ? $error->retryAfter
                : mt_rand() / mt_getrandmax() * 0.5 * 2 ** $attempt;
            ($this->sleep)(min($delay, $this->maxRetryDelay));
        }
    }
}
