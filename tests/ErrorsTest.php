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

    public function testSearchParsesSkipsAndTypedProfileMismatch(): void
    {
        $http = new HttpFactory();
        $http->fake(['*' => HttpFactory::response(['success' => true, 'data' => ['matches' => [], 'queryTimeMs' => 3, 'collectionsSearched' => 1, 'skippedCollections' => [['id' => 'c2', 'name' => 'Legacy', 'reason' => 'embedding_profile_mismatch']]]])]);
        $client = new LiveXFaceClient('lxf_test', 'https://api.test/api/v1', http: $http);
        $result = $client->faces()->search('img', ['c1', 'c2']);
        $this->assertSame('embedding_profile_mismatch', $result->skippedCollections[0]->reason);

        $conflictHttp = new HttpFactory();
        $conflictHttp->fake(['*' => HttpFactory::response(['success' => false, 'error' => ['code' => 'EMBEDDING_PROFILE_MISMATCH', 'message' => 'no compatible collections']], 409)]);
        $conflictClient = new LiveXFaceClient('lxf_test', 'https://api.test/api/v1', http: $conflictHttp);
        try {
            $conflictClient->faces()->search('img');
            $this->fail('expected LiveXFaceApiException');
        } catch (LiveXFaceApiException $e) {
            $this->assertSame(409, $e->status);
            $this->assertSame('EMBEDDING_PROFILE_MISMATCH', $e->errorCode);
        }
    }
}
