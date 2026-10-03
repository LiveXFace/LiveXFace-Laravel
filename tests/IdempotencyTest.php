<?php

namespace LiveXFace\Tests;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\Create;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use LiveXFace\Exceptions\LiveXFaceApiException;
use LiveXFace\Exceptions\LiveXFaceNetworkException;
use LiveXFace\LiveXFaceClient;
use PHPUnit\Framework\TestCase;

final class IdempotencyTest extends TestCase
{
    private const FACE = ['id' => 'f-1', 'externalId' => 'a', 'collectionId' => 'col'];

    private HttpFactory $http;

    /** @var Request[] every attempt, including the ones whose connection dropped */
    private array $requests = [];

    /** @var float[] */
    private array $sleeps = [];

    protected function setUp(): void
    {
        $this->http = new HttpFactory();
    }

    private function client(int $maxRetries = 0): LiveXFaceClient
    {
        return new LiveXFaceClient(
            'lxf_test',
            'https://api.test/api/v1',
            http: $this->http,
            maxRetries: $maxRetries,
            sleep: function (float $seconds) {
                $this->sleeps[] = $seconds;
            },
        );
    }

    /** Answer each attempt with the next of $answers; 'drop' fails the connection. */
    private function answer(array ...$answers): void
    {
        $this->http->fake(function (Request $request) use (&$answers) {
            $this->requests[] = $request;
            $answer = array_shift($answers);
            if ($answer === ['drop']) {
                return Create::rejectionFor(new ConnectException('Connection reset by peer', $request->toPsrRequest()));
            }
            [$status, $body, $headers] = $answer + [2 => []];

            return HttpFactory::response($body, $status, $headers);
        });
    }

    private static function error(string $code): array
    {
        return ['success' => false, 'requestId' => 'req-9', 'error' => ['code' => $code, 'message' => $code]];
    }

    /** @return array<int, string> */
    private function keys(): array
    {
        return array_map(fn (Request $r) => $r->header('Idempotency-Key')[0] ?? '', $this->requests);
    }

    public function testRateLimitExposesRetryAfterWithRetriesOff(): void
    {
        $this->answer([429, self::error('RATE_LIMIT_EXCEEDED'), ['Retry-After' => '12']]);

        try {
            $this->client()->faces()->identify('col', 'img');
            $this->fail('expected LiveXFaceApiException');
        } catch (LiveXFaceApiException $e) {
            $this->assertSame(429, $e->status);
            $this->assertSame('RATE_LIMIT_EXCEEDED', $e->errorCode);
            $this->assertSame('req-9', $e->requestId);
            $this->assertSame(12, $e->retryAfter);
            $this->assertSame(12, $e->getRetryAfter());
        }
        $this->assertCount(1, $this->requests);
    }

    public function testBusyEnrolmentIsRetriedAfterRetryAfterWithOneGeneratedKey(): void
    {
        $this->answer(
            [503, self::error('SERVICE_BUSY'), ['Retry-After' => '5']],
            [201, ['success' => true, 'data' => self::FACE]],
        );

        $face = $this->client(maxRetries: 3)->faces()->register('col', 'img', 'a');

        $this->assertSame('f-1', $face->id);
        $this->assertCount(2, $this->requests);
        [$first, $second] = $this->keys();
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $first);
        $this->assertSame($first, $second);
        $this->assertSame([5.0], $this->sleeps);
    }

    public function testDroppedEnrolmentIsRetriedAndReturnsTheReplay(): void
    {
        $this->answer(
            ['drop'],
            [201, ['success' => true, 'data' => self::FACE], ['Idempotent-Replayed' => 'true']],
        );

        $face = $this->client(maxRetries: 2)->faces()->register('col', 'img', 'a', idempotencyKey: 'key-1');

        $this->assertSame('f-1', $face->id);
        $this->assertSame(['key-1', 'key-1'], $this->keys());
        // The multipart body is rebuilt for the second attempt.
        $this->assertSame('img', collect($this->requests[1]->data())->firstWhere('name', 'image')['contents']);
    }

    public function testValidationErrorIsNotRetried(): void
    {
        $this->answer([422, self::error('NO_FACE_DETECTED')]);

        try {
            $this->client(maxRetries: 3)->faces()->register('col', 'img', 'a');
            $this->fail('expected LiveXFaceApiException');
        } catch (LiveXFaceApiException $e) {
            $this->assertTrue($e->isNoFaceDetected());
        }
        $this->assertCount(1, $this->requests);
    }

    public function testRetriesStopAtMaxAndSurfaceTheLastError(): void
    {
        $busy = [503, self::error('SERVICE_BUSY')];
        $this->answer($busy, $busy, $busy);

        try {
            $this->client(maxRetries: 2)->faces()->batchRegisterAsync('col', [['external_id' => 'a', 'image' => 'img']]);
            $this->fail('expected LiveXFaceApiException');
        } catch (LiveXFaceApiException $e) {
            $this->assertSame('SERVICE_BUSY', $e->errorCode);
            $this->assertNull($e->retryAfter);
        }
        $this->assertCount(3, $this->requests);
        // Backoff without Retry-After: full jitter under 0.5 s * 2^attempt.
        $this->assertCount(2, $this->sleeps);
        $this->assertLessThanOrEqual(0.5, $this->sleeps[0]);
        $this->assertLessThanOrEqual(1.0, $this->sleeps[1]);
    }

    public function testCallerKeyIsSentAndNoKeyWithoutRetries(): void
    {
        $this->answer(
            [201, ['success' => true, 'data' => self::FACE]],
            [200, ['success' => true, 'data' => ['succeeded' => 1, 'failed' => 0, 'results' => []]]],
            [201, ['success' => true, 'data' => self::FACE]],
        );
        $client = $this->client();

        $client->faces()->register('col', 'img', 'a', idempotencyKey: 'caller-key');
        $client->faces()->batchRegister('col', [['external_id' => 'a', 'image' => 'img']], idempotencyKey: 'batch-key');
        $client->faces()->register('col', 'img', 'a');

        $this->assertSame(['caller-key', 'batch-key', ''], $this->keys());
        $this->assertFalse($this->requests[2]->hasHeader('Idempotency-Key'));
    }

    public function testGeneratedKeysAreDistinctUuidV4(): void
    {
        $a = LiveXFaceClient::generateIdempotencyKey();
        $b = LiveXFaceClient::generateIdempotencyKey();

        $uuidV4 = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';
        $this->assertMatchesRegularExpression($uuidV4, $a);
        $this->assertMatchesRegularExpression($uuidV4, $b);
        $this->assertNotSame($a, $b);
    }

    public function testPlainPostIsNotRetriedOnNetworkError(): void
    {
        $this->answer(['drop'], ['drop']);
        try {
            $this->client(maxRetries: 3)->faces()->identify('col', 'img');
            $this->fail('expected LiveXFaceNetworkException');
        } catch (LiveXFaceNetworkException) {
        }
        $this->assertCount(1, $this->requests);
        $this->assertFalse($this->requests[0]->hasHeader('Idempotency-Key'));
    }

    public function testPlainPostIsNotRetriedOn500(): void
    {
        $this->answer([500, self::error('INTERNAL_ERROR')], [500, self::error('INTERNAL_ERROR')]);
        try {
            $this->client(maxRetries: 3)->faces()->verify('col', 'img', 'f-1');
            $this->fail('expected LiveXFaceApiException');
        } catch (LiveXFaceApiException $e) {
            $this->assertSame(500, $e->status);
        }
        $this->assertCount(1, $this->requests);
    }

    public function testPlainPostIsRetriedOn503(): void
    {
        $this->answer(
            [503, self::error('SERVICE_BUSY'), ['Retry-After' => '1']],
            [200, ['success' => true, 'data' => ['matches' => []]]],
        );

        $this->client(maxRetries: 1)->faces()->identify('col', 'img');

        $this->assertCount(2, $this->requests);
        $this->assertSame([1.0], $this->sleeps);
    }

    public function testGetIsRetriedOnNetworkError(): void
    {
        $this->answer(['drop'], [200, ['success' => true, 'data' => self::FACE]]);

        $face = $this->client(maxRetries: 1)->faces()->get('col', 'f-1');

        $this->assertSame('f-1', $face->id);
        $this->assertCount(2, $this->requests);
    }
}
