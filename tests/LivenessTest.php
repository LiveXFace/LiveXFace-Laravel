<?php

namespace LiveXFace\Tests;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use LiveXFace\LiveXFaceClient;
use PHPUnit\Framework\TestCase;

final class LivenessTest extends TestCase
{
    private HttpFactory $http;
    private LiveXFaceClient $client;

    protected function setUp(): void
    {
        $this->http = new HttpFactory();
        $this->client = new LiveXFaceClient('lxf_test', 'https://api.test/api/v1', http: $this->http);
    }

    private function fake(array $data): void
    {
        $this->http->fake(['*' => HttpFactory::response(['success' => true, 'data' => $data])]);
    }

    /** @return array<string, array{name: string, contents: mixed, filename?: string}> */
    private function parts(Request $request): array
    {
        $parts = [];
        foreach ($request->data() as $part) {
            $parts[$part['name']] = $part;
        }

        return $parts;
    }

    private function onlyRequest(): Request
    {
        $recorded = $this->http->recorded();
        $this->assertCount(1, $recorded);

        return $recorded[0][0];
    }

    public function testActiveLivenessSendsFramesAndParsesToken(): void
    {
        $this->fake([
            'isLive' => true,
            'overallScore' => 0.93,
            'framesAnalyzed' => 6,
            'framesWithFace' => 6,
            'challenges' => [
                'blink' => ['passed' => true, 'available' => true, 'blinkCount' => 2],
                'headTurn' => ['passed' => true, 'available' => true, 'yawRange' => 31.5],
                'passiveAntispoof' => ['passed' => null, 'available' => false],
            ],
            'livenessToken' => 'lt_abc',
            'livenessTokenExpiresAt' => '2026-09-28T10:05:00Z',
        ]);

        $frames = array_map(fn ($i) => "jpeg-{$i}", range(0, 5));
        $result = $this->client->faces()->activeLiveness('col_1', $frames);

        $request = $this->onlyRequest();
        $this->assertSame('POST', $request->method());
        $this->assertSame('https://api.test/api/v1/collections/col_1/active-liveness', $request->url());
        $this->assertTrue($request->isMultipart());
        $parts = $this->parts($request);
        $this->assertSame(array_map(fn ($i) => "frame_{$i}", range(0, 5)), array_keys($parts));
        $this->assertSame('jpeg-3', $parts['frame_3']['contents']);

        $this->assertTrue($result->isLive);
        $this->assertSame(0.93, $result->overallScore);
        $this->assertSame(6, $result->framesWithFace);
        $this->assertSame('lt_abc', $result->livenessToken);
        $this->assertSame('2026-09-28T10:05:00Z', $result->livenessTokenExpiresAt);
        $this->assertTrue($result->blink->passed);
        $this->assertSame(['blinkCount' => 2], $result->blink->metrics);
        $this->assertSame(31.5, $result->headTurn->metrics['yawRange']);
        $this->assertNull($result->passiveAntispoof->passed);
        $this->assertFalse($result->passiveAntispoof->available);
    }

    public function testFailedActiveLivenessHasNoToken(): void
    {
        $this->fake([
            'isLive' => false,
            'overallScore' => 0.21,
            'framesAnalyzed' => 5,
            'framesWithFace' => 5,
            'challenges' => [
                'blink' => ['passed' => false, 'available' => true, 'blinkCount' => 0],
                'headTurn' => ['passed' => true, 'available' => true],
                'passiveAntispoof' => ['passed' => true, 'available' => true, 'score' => 0.8],
            ],
        ]);

        $result = $this->client->faces()->activeLiveness('col_1', array_fill(0, 5, 'x'));

        $this->assertFalse($result->isLive);
        $this->assertFalse($result->blink->passed);
        $this->assertNull($result->livenessToken);
        $this->assertNull($result->livenessTokenExpiresAt);
    }

    public function testRegisterSendsLivenessTokenOnlyWhenGiven(): void
    {
        $face = ['id' => 'f1', 'externalId' => 'u1', 'collectionId' => 'col_1'];
        $this->fake($face);

        $this->client->faces()->register('col_1', 'img', 'u1', livenessToken: 'lt_abc');
        $parts = $this->parts($this->onlyRequest());
        $this->assertSame('lt_abc', $parts['liveness_token']['contents']);

        $this->setUp();
        $this->fake($face);
        $this->client->faces()->register('col_1', 'img', 'u1');
        $parts = $this->parts($this->onlyRequest());
        $this->assertArrayNotHasKey('liveness_token', $parts);
        $this->assertSame('u1', $parts['external_id']['contents']);
    }

    public function testBatchEntriesSerializeLivenessToken(): void
    {
        $items = [
            ['external_id' => 'u1', 'image' => 'a', 'liveness_token' => 'lt_1'],
            ['external_id' => 'u2', 'image' => 'b'],
        ];
        $expected = [
            ['externalId' => 'u1', 'metadata' => [], 'livenessToken' => 'lt_1'],
            ['externalId' => 'u2', 'metadata' => []],
        ];

        $this->fake(['results' => [], 'total' => 2, 'succeeded' => 2, 'failed' => 0]);
        $this->client->faces()->batchRegister('col_1', $items);
        $request = $this->onlyRequest();
        $this->assertStringEndsWith('/collections/col_1/faces/batch', $request->url());
        $this->assertSame($expected, json_decode($this->parts($request)['entries']['contents'], true));

        $this->setUp();
        $this->fake(['id' => 'job_1', 'status' => 'queued']);
        $this->client->faces()->batchRegisterAsync('col_1', $items);
        $request = $this->onlyRequest();
        $this->assertStringEndsWith('/collections/col_1/faces/batch-async', $request->url());
        $this->assertSame($expected, json_decode($this->parts($request)['entries']['contents'], true));
    }
}
