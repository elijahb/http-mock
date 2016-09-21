<?php
namespace InterNations\Component\HttpMock;

use Countable;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Message\MessageFactory;
use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Post\PostBodyInterface;
use InterNations\Component\HttpMock\Request\UnifiedRequest;
use UnexpectedValueException;

class RequestCollectionFacade implements Countable
{
    private $client;

    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * @return UnifiedRequest
     */
    public function latest()
    {
        return $this->getRecordedRequest('/_request/last');
    }

    /**
     * @return UnifiedRequest
     */
    public function last()
    {
        return $this->getRecordedRequest('/_request/last');
    }

    /**
     * @return UnifiedRequest
     */
    public function first()
    {
        return $this->getRecordedRequest('/_request/first');
    }

    /**
     * @param integer $position
     * @return UnifiedRequest
     */
    public function at($position)
    {
       return $this->getRecordedRequest('/_request/' . $position);
    }

    /**
     * @return UnifiedRequest
     */
    public function pop()
    {
        return $this->deleteRecordedRequest('/_request/last');
    }

    /**
     * @return UnifiedRequest
     */
    public function shift()
    {
        return $this->deleteRecordedRequest('/_request/first');
    }

    public function count()
    {
        $response = $this->client
            ->get('/_request/count');

        return (int) $response->getBody()->getContents();
    }

    /**
     * @param Response $response
     * @param string $path
     * @throws UnexpectedValueException
     * @return UnifiedRequest
     */
    private function parseRequestFromResponse(Response $response, $path)
    {
        try {
            $requestInfo = Util::deserialize($response->getBody());
        } catch (UnexpectedValueException $e) {
            throw new UnexpectedValueException(
                sprintf('Cannot deserialize response from "%s": "%s"', $path, $response->getBody()),
                null,
                $e
            );
        }

        $factory = new MessageFactory();
        $request = $factory->fromMessage($requestInfo['request']);
        $params = $this->configureRequest(
            $request,
            $requestInfo['server'],
            isset($requestInfo['enclosure']) ? $requestInfo['enclosure'] : []
        );

        return new UnifiedRequest($request, $params);
    }

    private function configureRequest(RequestInterface $request, array $server, array $enclosure)
    {
        if (isset($server['HTTP_HOST'])) {
            $request->setHost($server['HTTP_HOST']);
        }

        if (isset($server['HTTP_PORT'])) {
            $request->setPort($server['HTTP_PORT']);
        }

        if (isset($server['PHP_AUTH_USER'])) {
            $request->setHeader('Php-Auth-User', $server['PHP_AUTH_USER']);

            if (isset($server['PHP_AUTH_PW'])) {
                $request->setHeader('Php-Auth-Pw', $server['PHP_AUTH_PW']);
            }
        }

        $params = [];

        if (isset($server['HTTP_USER_AGENT'])) {
            $params['userAgent'] = $server['HTTP_USER_AGENT'];
        }

        $body = $request->getBody();

        if ($body instanceof PostBodyInterface) {
            $body->replaceFields($enclosure);
        }

        return $params;
    }

    private function getRecordedRequest($path)
    {
        $response = $this->client
            ->get($path);

        return $this->parseResponse($response, $path);
    }

    private function deleteRecordedRequest($path)
    {
        $response = $this->client
            ->delete($path);

        return $this->parseResponse($response, $path);
    }

    private function parseResponse(Response $response, $path)
    {
        $statusCode = intval($response->getStatusCode());

        if ($statusCode !== 200) {
            throw new UnexpectedValueException(
                sprintf('Expected status code 200 from "%s", got %d', $path, $statusCode)
            );
        }

        $contentType = $response->hasHeader('content-type')
            ? $response->getHeader('content-type')
            : '';

        if (substr($contentType, 0, 10) !== 'text/plain') {
            throw new UnexpectedValueException(
                sprintf('Expected content type "text/plain" from "%s", got "%s"', $path, $contentType)
            );
        }

        return $this->parseRequestFromResponse($response, $path);
    }
}
