<?php

namespace LiveXFace\Tests;

use Illuminate\Http\Client\Factory as HttpFactory;
use LiveXFace\Exceptions\LiveXFaceApiException;
use LiveXFace\LiveXFaceClient;
use PHPUnit\Framework\TestCase;

final class ErrorsTest extends TestCase
{
    public function testApiExceptionExposesDetails(): void
    {
        $http = new HttpFactory();
        $http->fake(['*' => HttpFactory::response([
            'success' => false,
            'requestId' => 'r-1',
            'error' => [
                'code' => 'MULTIPLE_FACES',
                'message' => 'multiple faces detected',
                'details' => ['faceCount' => 2, 'faces' => []],
            ],
        ], 422)]);
        $client = new LiveXFaceClient('lxf_test', 'https://api.test/api/v1', http: $http);

        try {
            $client->faces()->register('col', 'img', 'a');
            $this->fail('expected LiveXFaceApiException');
        } catch (LiveXFaceApiException $e) {
            $this->assertSame('MULTIPLE_FACES', $e->errorCode);
            $this->assertSame(2, $e->details['faceCount']);
            $this->assertSame('r-1', $e->requestId);
        }
    }
}
