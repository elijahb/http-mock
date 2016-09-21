<?php
namespace InterNations\Component\HttpMock\Tests\PHPUnit;

use InterNations\Component\Testing\AbstractTestCase;
use InterNations\Component\HttpMock\PHPUnit\HttpMockTrait;
use Symfony\Component\HttpFoundation\Response;

/** @large */
class HttpMockMultiPHPUnitIntegrationTest extends AbstractTestCase
{
    use HttpMockTrait;

    public static function setUpBeforeClass()
    {
        static::setUpHttpMockBeforeClass(null, null, null, 'firstNamedServer');
        static::setUpHttpMockBeforeClass(static::getHttpMockDefaultPort() + 1, null, null, 'secondNamedServer');
    }

    public static function tearDownAfterClass()
    {
        static::tearDownHttpMockAfterClass();
    }

    public function setUp()
    {
        $this->setUpHttpMock();
    }

    public function tearDown()
    {
        $this->tearDownHttpMock();
    }

    public static function getPaths()
    {
        return [
            [
                '/foo',
                '/bar',
            ]
        ];
    }

    /** @dataProvider getPaths */
    public function testSimpleRequest($path)
    {
        $this->http['firstNamedServer']->mock
            ->when()
                ->pathIs($path)
            ->then()
                ->body($path . ' body')
            ->end();
        $this->http['firstNamedServer']->setUp();

        $this->assertSame($path . ' body', (string) $this->http['firstNamedServer']->client->get($path)->getBody());

        $request = $this->http['firstNamedServer']->requests->latest();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame($path, $request->getPath());

        $request = $this->http['firstNamedServer']->requests->last();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame($path, $request->getPath());

        $request = $this->http['firstNamedServer']->requests->first();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame($path, $request->getPath());

        $request = $this->http['firstNamedServer']->requests->at(0);
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame($path, $request->getPath());

        $request = $this->http['firstNamedServer']->requests->pop();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame($path, $request->getPath());

        $this->assertSame($path . ' body', (string) $this->http['firstNamedServer']->client->get($path)->getBody());

        $request = $this->http['firstNamedServer']->requests->shift();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame($path, $request->getPath());

        $this->setExpectedException(
            'UnexpectedValueException',
            'Expected status code 200 from "/_request/last", got 404'
        );
        $this->http['firstNamedServer']->requests->pop();
    }

    public function testErrorLogOutput()
    {
        $this->http['firstNamedServer']->mock
            ->when()
                ->callback(static function () {error_log('error output');})
            ->then()
            ->end();
        $this->http['firstNamedServer']->setUp();

        $this->http['firstNamedServer']->client->get('/foo');

        // Should fail during tear down as we have an error_log() on the server side
        try {
            $this->tearDown();
            $this->fail('Exception expected');
        } catch (\PHPUnit_Framework_ExpectationFailedException $e) {
            $this->assertContains('HTTP mock server standard error output should be empty', $e->getMessage());
        }
    }

    public function testFailedRequest()
    {
        $response = $this->http['firstNamedServer']->client->get('/foo');
        $this->assertSame('404', $response->getStatusCode());
        $this->assertSame('No matching expectation found', (string) $response->getBody());
    }

    public function testStopServer()
    {
        $this->http['firstNamedServer']->server->stop();
    }

    /** @depends testStopServer */
    public function testHttpServerIsRestartedIfATestStopsIt()
    {
        $response = $this->http['firstNamedServer']->client->get('/');
        $this->assertSame('404', $response->getStatusCode());
    }

    public function testLimitDurationOfAResponse()
    {
        $this->http['firstNamedServer']->mock
            ->once()
            ->when()
                ->methodIs('POST')
            ->then()
                ->body('POST METHOD')
            ->end();
        $this->http['firstNamedServer']->setUp();
        $firstResponse = $this->http['firstNamedServer']->client->post('/');
        $this->assertSame('200', $firstResponse->getStatusCode());
        $secondResponse = $this->http['firstNamedServer']->client->post('/');
        $this->assertSame('410', $secondResponse->getStatusCode());
        $this->assertSame('Expectation no longer applicable', $secondResponse->getBody()->getContents());

        $this->http['firstNamedServer']->mock
            ->exactly(2)
            ->when()
                ->methodIs('POST')
            ->then()
                ->body('POST METHOD')
            ->end();
        $this->http['firstNamedServer']->setUp();
        $firstResponse = $this->http['firstNamedServer']->client->post('/');
        $this->assertSame('200', $firstResponse->getStatusCode());
        $secondResponse = $this->http['firstNamedServer']->client->post('/');
        $this->assertSame('200', $secondResponse->getStatusCode());
        $thirdResponse = $this->http['firstNamedServer']->client->post('/');
        $this->assertSame('410', $thirdResponse->getStatusCode());
        $this->assertSame('Expectation no longer applicable', $thirdResponse->getBody()->getContents());

        $this->http['firstNamedServer']->mock
            ->any()
            ->when()
                ->methodIs('POST')
            ->then()
                ->body('POST METHOD')
            ->end();
        $this->http['firstNamedServer']->setUp();
        $firstResponse = $this->http['firstNamedServer']->client->post('/');
        $this->assertSame('200', $firstResponse->getStatusCode());
        $secondResponse = $this->http['firstNamedServer']->client->post('/');
        $this->assertSame('200', $secondResponse->getStatusCode());
        $thirdResponse = $this->http['firstNamedServer']->client->post('/');
        $this->assertSame('200', $thirdResponse->getStatusCode());
    }

    public function testCallbackOnResponse()
    {
        $this->http['firstNamedServer']->mock
            ->when()
                ->methodIs('POST')
            ->then()
                ->callback(static function(Response $response) {$response->setContent('CALLBACK');})
            ->end();
        $this->http['firstNamedServer']->setUp();
        $this->assertSame('CALLBACK', $this->http['firstNamedServer']->client->post('/')->getBody()->getContents());
    }

    public function testComplexResponse()
    {
        $this->http['firstNamedServer']->mock
            ->when()
                ->methodIs('POST')
            ->then()
                ->body('BODY')
                ->statusCode(201)
                ->header('X-Foo', 'Bar')
            ->end();
        $this->http['firstNamedServer']->setUp();
        $request = $this->http['firstNamedServer']->client->createRequest('POST', '/');
        $request->getBody()->replaceFields(['post-key' => 'post-value']);
        $response = $this->http['firstNamedServer']->client->send($request);
        $this->assertSame('BODY', $response->getBody()->getContents());
        $this->assertSame('201', $response->getStatusCode());
        $this->assertSame('Bar', (string) $response->getHeader('X-Foo'));
        $this->assertSame('post-value', $this->http['firstNamedServer']->requests->latest()->getPostField('post-key'));
    }

    public function testPutRequest()
    {
        $this->http['firstNamedServer']->mock
            ->when()
                ->methodIs('PUT')
            ->then()
                ->body('BODY')
                ->statusCode(201)
                ->header('X-Foo', 'Bar')
            ->end();
        $this->http['firstNamedServer']->setUp();
        $request = $this->http['firstNamedServer']->client->createRequest('PUT', '/', ['body' => ['put-key' => 'put-value']]);
        $response = $this->http['firstNamedServer']->client->send($request);
        $this->assertSame('BODY', $response->getBody()->getContents());
        $this->assertSame('201', $response->getStatusCode());
        $this->assertSame('Bar', (string) $response->getHeader('X-Foo'));
        $this->assertSame('put-value', $this->http['firstNamedServer']->requests->latest()->getPostField('put-key'));
    }

    public function testPostRequest()
    {
        $this->http['firstNamedServer']->mock
            ->when()
                ->methodIs('POST')
            ->then()
                ->body('BODY')
            ->statusCode(201)
                ->header('X-Foo', 'Bar')
            ->end();
        $this->http['firstNamedServer']->setUp();
        $request = $this->http['firstNamedServer']->client->createRequest('POST', '/', ['body' => ['post-key' => 'post-value']]);
        $response = $this->http['firstNamedServer']->client->send($request);
        $this->assertSame('BODY', $response->getBody()->getContents());
        $this->assertSame('201', $response->getStatusCode());
        $this->assertSame('Bar', (string) $response->getHeader('X-Foo'));
        $this->assertSame('post-value', $this->http['firstNamedServer']->requests->latest()->getPostField('post-key'));
    }

    public function testFatalError()
    {
        if (version_compare(PHP_VERSION, '7.0', '<')) {
            $this->markTestSkipped('Comment in to test if fatal errors are properly handled');
        }

        $this->setExpectedException('Error', 'Cannot instantiate abstract class');
        new \PHPUnit_Framework_TestCase();
    }
}
