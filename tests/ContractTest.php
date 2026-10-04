<?php

namespace LiveXFace\Tests;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use LiveXFace\LiveXFaceClient;
use PHPUnit\Framework\TestCase;

/**
 * Checks every SDK call against the pinned API contract
 * (contract/openapi-<CONTRACT_VERSION>.json): the path and HTTP method exist
 * and every field the contract marks required is sent.
 */
final class ContractTest extends TestCase
{
    private const BASE_URL = 'https://api.test/api/v1';

    private HttpFactory $http;

    /** @var Request[] */
    private array $requests = [];

    protected function setUp(): void
    {
        $this->http = new HttpFactory();
        $this->http->fake(function (Request $request) {
            $this->requests[] = $request;

            // Only Face and BatchJob need a field (id) to parse.
            return HttpFactory::response(['success' => true, 'data' => ['id' => 'x']]);
        });
    }

    private static function root(): string
    {
        return dirname(__DIR__);
    }

    private static function pinnedVersion(): string
    {
        return trim(file_get_contents(self::root().'/CONTRACT_VERSION'));
    }

    private static function contract(): array
    {
        $path = self::root().'/contract/openapi-'.self::pinnedVersion().'.json';

        return json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Every public resource method, called with dummy arguments including the
     * optional ones that add fields. Keyed "Class::method".
     *
     * @return array<string, \Closure(LiveXFaceClient): mixed>
     */
    private static function calls(): array
    {
        $items = [
            ['external_id' => 'a', 'image' => 'img-a', 'metadata' => ['k' => 'v'], 'liveness_token' => 'lt'],
            ['external_id' => 'b', 'image' => 'img-b'],
        ];

        return [
            'FacesResource::register' => fn ($c) => $c->faces()->register(
                'col', 'img', 'ext', ['k' => 'v'], 'a.jpg', 'lt', 'idem-1',
            ),
            'FacesResource::list' => fn ($c) => $c->faces()->list('col', 10, 5),
            'FacesResource::get' => fn ($c) => $c->faces()->get('col', 'f-1'),
            'FacesResource::delete' => fn ($c) => $c->faces()->delete('col', 'f-1'),
            'FacesResource::verify' => fn ($c) => $c->faces()->verify('col', 'img', 'f-1', 0.5, 'a.jpg'),
            'FacesResource::identify' => fn ($c) => $c->faces()->identify('col', 'img', 3, 0.5, 'a.jpg'),
            'FacesResource::liveness' => fn ($c) => $c->faces()->liveness('col', 'img', 'a.jpg'),
            'FacesResource::activeLiveness' => fn ($c) => $c->faces()->activeLiveness('col', ['f0', 'f1', 'f2', 'f3', 'f4']),
            'FacesResource::createLivenessSession' => fn ($c) => $c->faces()->createLivenessSession('col'),
            'FacesResource::completeLivenessSession' => fn ($c) => $c->faces()->completeLivenessSession(
                'col', 'lvs_1', ['f0', 'f1', 'f2', 'f3', 'f4'], true,
            ),
            'FacesResource::compare' => fn ($c) => $c->faces()->compare('img1', 'img2', 0.5),
            'FacesResource::attributes' => fn ($c) => $c->faces()->attributes('col', 'img', 'a.jpg'),
            'FacesResource::batchRegister' => fn ($c) => $c->faces()->batchRegister('col', $items, 'idem-2'),
            'FacesResource::batchRegisterAsync' => fn ($c) => $c->faces()->batchRegisterAsync('col', $items, 'idem-3'),
            'FacesResource::getBatchJob' => fn ($c) => $c->faces()->getBatchJob('col', 'job-1'),
        ];
    }

    public function testConstantMatchesPinnedContract(): void
    {
        $this->assertSame(self::pinnedVersion(), LiveXFaceClient::CONTRACT_VERSION);
        $this->assertSame(LiveXFaceClient::CONTRACT_VERSION, self::contract()['info']['version']);
    }

    public function testEveryPublicResourceMethodIsChecked(): void
    {
        $checked = array_keys(self::calls());
        foreach (glob(self::root().'/src/Resources/*.php') as $file) {
            $class = new \ReflectionClass('LiveXFace\\Resources\\'.basename($file, '.php'));
            foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->isConstructor() || $method->getDeclaringClass()->getName() !== $class->getName()) {
                    continue;
                }
                $name = $class->getShortName().'::'.$method->getName();
                $this->assertContains($name, $checked, "{$name} is not exercised by ContractTest::calls()");
            }
        }
    }

    public function testEverySdkCallIsInTheContract(): void
    {
        $contract = self::contract();
        $client = new LiveXFaceClient('lxf_test', self::BASE_URL, http: $this->http);

        foreach (self::calls() as $name => $call) {
            $this->requests = [];
            $call($client);
            $this->assertNotEmpty($this->requests, "{$name} sent no request");

            foreach ($this->requests as $request) {
                $this->assertInContract($contract, $name, $request);
            }
        }
    }

    private function assertInContract(array $contract, string $name, Request $request): void
    {
        $method = strtolower($request->method());
        $url = parse_url($request->url());
        $path = substr($url['path'], strlen(parse_url(self::BASE_URL, PHP_URL_PATH)));
        $call = "{$name}: ".strtoupper($method)." {$path}";

        $template = self::matchTemplate(array_keys($contract['paths']), $path);
        $this->assertNotNull($template, "{$call} matches no path in contract ".LiveXFaceClient::CONTRACT_VERSION);
        $operation = $contract['paths'][$template][$method] ?? null;
        $this->assertNotNull($operation, "{$call}: contract path {$template} has no ".strtoupper($method));

        parse_str($url['query'] ?? '', $query);
        $sent = array_keys($query);
        if ($request->isMultipart()) {
            $sent = array_merge($sent, array_column($request->data(), 'name'));
        } elseif ($request->isJson()) {
            $sent = array_merge($sent, array_keys($request->json() ?? []));
        }

        $required = [];
        foreach ($operation['parameters'] ?? [] as $param) {
            if (($param['required'] ?? false) && in_array($param['in'], ['query', 'header'], true)) {
                if ($param['in'] === 'header') {
                    $this->assertTrue($request->hasHeader($param['name']), "{$call} omits required header {$param['name']}");
                } else {
                    $required[] = $param['name'];
                }
            }
        }
        foreach ($operation['requestBody']['content'] ?? [] as $content) {
            $schema = $content['schema'];
            if (isset($schema['$ref'])) {
                $schema = $contract['components']['schemas'][basename($schema['$ref'])];
            }
            $required = array_merge($required, $schema['required'] ?? []);
        }

        foreach ($required as $field) {
            // Repeated files are documented by their first name: images[0], frame_0.
            $pattern = '/^'.preg_replace(['/\\\\\[0\\\\\]$/', '/_0$/'], ['\[\d+\]', '_\d+'], preg_quote($field, '/')).'$/';
            $this->assertNotEmpty(preg_grep($pattern, $sent), "{$call} omits required field {$field}");
        }
    }

    /** The template matching $path, preferring literal segments ("/faces/batch" over "/faces/{face_id}"). */
    private static function matchTemplate(array $templates, string $path): ?string
    {
        usort($templates, fn ($a, $b) => substr_count($a, '{') <=> substr_count($b, '{'));
        foreach ($templates as $template) {
            $pattern = '#^'.preg_replace('/\\\\\{[^}]+\\\\\}/', '[^/]+', preg_quote($template, '#')).'$#';
            if (preg_match($pattern, $path)) {
                return $template;
            }
        }

        return null;
    }
}
