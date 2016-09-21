<?php
namespace InterNations\Component\HttpMock\Tests;

use GuzzleHttp\Post\PostBodyInterface;
use InterNations\Component\HttpMock\Server;
use InterNations\Component\Testing\AbstractTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Message\MessageFactory;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\Message\RequestInterface;
use SuperClosure\SerializableClosure;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @large
 * @group integration
 */
class AppIntegrationTest extends AbstractTestCase
{
    /**
     * @var Server
     */
    private static $server1;

    /**
     * @var Client
     */
    private $client;

    public static function setUpBeforeClass()
    {
        static::$server1 = new Server(HTTP_MOCK_PORT, HTTP_MOCK_HOST);
        static::$server1->start();
    }

    public static function tearDownAfterClass()
    {
        static::assertSame('', (string) static::$server1->getOutput(), (string) static::$server1->getOutput());
        static::assertSame('', (string) static::$server1->getErrorOutput(), (string) static::$server1->getErrorOutput());
        static::$server1->stop();
    }

    public function setUp()
    {
        static::$server1->clean();
        $this->client = static::$server1->getClient();
    }

    public function testSimpleUseCase()
    {
        $request = $this->client->createRequest('POST',
            '/_expectation',
            ['body' =>
                $this->createExpectationParams(
                    [
                        static function ($request) {
                            return $request instanceof Request;
                        }
                    ],
                    new Response('fake body', 200)
                )
            ]
        );
        $response = $this->client->send($request);
        $this->assertSame('', (string) $response->getBody());
        $this->assertEquals(201, $response->getStatusCode());

        $request = $this->client->createRequest('POST', '/foobar',
            [
                'headers' => ['X-Special' => 1],
                'body' => ['post' => 'data']
            ]
        );
        $response = $this->client->send($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame('fake body', (string) $response->getBody());

        $response = $this->client->get('/_request/latest');

        /** @var RequestInterface $request */
        $request = $this->parseRequestFromResponse($response);
        $this->assertSame('1', (string) $request->getHeader('X-Special'));
        $this->assertSame('post=data', (string) $request->getBody());
    }

    public function testRecording()
    {
        $this->client->delete('/_all');

        $this->assertEquals(404, $this->client->get('/_request/latest')->getStatusCode());
        $this->assertEquals(404, $this->client->get('/_request/0')->getStatusCode());
        $this->assertEquals(404, $this->client->get('/_request/first')->getStatusCode());
        $this->assertEquals(404, $this->client->get('/_request/last')->getStatusCode());

        $this->client->get('/req/0');
        $this->client->get('/req/1');
        $this->client->get('/req/2');
        $this->client->get('/req/3');

        $this->assertSame(
            '/req/3',
            $this->parseRequestFromResponse($this->client->get('/_request/last'))->getPath()
        );
        $this->assertSame(
            '/req/0',
            $this->parseRequestFromResponse($this->client->get('/_request/0'))->getPath()
        );
        $this->assertSame(
            '/req/1',
            $this->parseRequestFromResponse($this->client->get('/_request/1'))->getPath()
        );
        $this->assertSame(
            '/req/2',
            $this->parseRequestFromResponse($this->client->get('/_request/2'))->getPath()
        );
        $this->assertSame(
            '/req/3',
            $this->parseRequestFromResponse($this->client->get('/_request/3'))->getPath()
        );
        $this->assertEquals(404, $this->client->get('/_request/4')->getStatusCode());

        $this->assertSame(
            '/req/3',
            $this->parseRequestFromResponse($this->client->delete('/_request/last'))->getPath()
        );
        $this->assertSame(
            '/req/0',
            $this->parseRequestFromResponse($this->client->delete('/_request/first'))->getPath()
        );
        $this->assertSame(
            '/req/1',
            $this->parseRequestFromResponse($this->client->get('/_request/0'))->getPath()
        );
        $this->assertSame(
            '/req/2',
            $this->parseRequestFromResponse($this->client->get('/_request/1'))->getPath()
        );
        $this->assertEquals(404, $this->client->get('/_request/2')->getStatusCode());
    }

    public function testErrorHandling()
    {
        $this->client->delete('/_all');

        $request = $this->client->createRequest('POST', '/_expectation', ['body' => ['matcher' => '']]);
        $response = $this->client->send($request);
        $this->assertEquals(417, $response->getStatusCode());
        $this->assertSame('POST data key "matcher" must be a serialized list of closures', (string) $response->getBody());

        $request = $this->client->createRequest('POST', '/_expectation', ['body' => ['matcher' => ['foo']]]);
        $response = $this->client->send($request);
        $this->assertEquals(417, $response->getStatusCode());
        $this->assertSame('POST data key "matcher" must be a serialized list of closures', (string) $response->getBody());

        $request = $this->client->createRequest('POST', '/_expectation');
        $response = $this->client->send($request);
        $this->assertEquals(417, $response->getStatusCode());
        $this->assertSame('POST data key "response" not found in POST data', (string) $response->getBody());

        $request = $this->client->createRequest('POST', '/_expectation', ['body' => ['response' => '']]);
        $response = $this->client->send($request);
        $this->assertEquals(417, $response->getStatusCode());
        $this->assertSame('POST data key "response" must be a serialized Symfony response', (string) $response->getBody());

        $request = $this->client->createRequest('POST', '/_expectation', ['body' => ['response' => serialize(new Response()), 'limiter' => 'foo']]);
        $response = $this->client->send($request);
        $this->assertEquals(417, $response->getStatusCode());
        $this->assertSame('POST data key "limiter" must be a serialized closure', (string) $response->getBody());
    }

    public function testServerParamsAreRecorded()
    {
        $this->client
            ->get('/foo', ['headers' => [
                    'User-Agent' => 'CUSTOM UA'
                ]
            ]);

        $latestRequest = unserialize($this->client->get('/_request/latest')->getBody());

        $this->assertSame(HTTP_MOCK_HOST, $latestRequest['server']['SERVER_NAME']);
        $this->assertSame(HTTP_MOCK_PORT, $latestRequest['server']['SERVER_PORT']);
        $this->assertSame('CUSTOM UA', $latestRequest['server']['HTTP_USER_AGENT']);
    }

    public function testNewestExpectationsAreFirstEvaluated()
    {
        $this->client->post(
            '/_expectation',
            ['body' =>
                $this->createExpectationParams(
                    [
                        static function ($request) {
                            return $request instanceof Request;
                        }
                    ],
                    new Response('first', 200)
                )
            ]
        );
        $this->assertSame('first', $this->client->get('/')->getBody()->getContents());

        $this->client->post(
            '/_expectation',
            [
                'body' =>
                    $this->createExpectationParams(
                        [
                            static function ($request) {
                                return $request instanceof Request;
                            }
                        ],
                        new Response('second', 200)
                    )
            ]
        );
        $this->assertSame('second', $this->client->get('/')->getBody()->getContents());
    }

    private function parseRequestFromResponse(ResponseInterface $response)
    {
        $body = unserialize($response->getBody());
        $factory = new MessageFactory();
        return $factory->fromMessage($body['request']);
    }

    private function createExpectationParams(array $closures, Response $response)
    {
        foreach ($closures as $index => $closure) {
            $closures[$index] = new SerializableClosure($closure);
        }

        return [
            'matcher'  => serialize($closures),
            'response' => serialize($response),
        ];
    }
}
