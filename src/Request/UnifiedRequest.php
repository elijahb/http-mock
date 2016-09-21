<?php
namespace InterNations\Component\HttpMock\Request;

use BadMethodCallException;
use GuzzleHttp\Collection;
use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Post\PostBodyInterface;
use GuzzleHttp\Query;
use GuzzleHttp\Stream\StreamInterface;
use GuzzleHttp\Url;

class UnifiedRequest
{
    /**
     * @var RequestInterface
     */
    private $wrapped;

    /**
     * @var string
     */
    private $userAgent;

    public function __construct(RequestInterface $wrapped, array $params = [])
    {
        $this->wrapped = $wrapped;
        $this->init($params);
    }

    /**
     * Get the user agent of the request
     *
     * @return string
     */
    public function getUserAgent()
    {
        return $this->userAgent;
    }

    /**
     * Get the body of the request if set
     *
     * @return StreamInterface|null
     */
    public function getBody()
    {
        return $this->wrapped->getBody();
    }

    /**
     * Get a POST field from the request
     *
     * @param string $field Field to retrieve
     *
     * @return mixed|null
     */
    public function getPostField($field)
    {
        parse_str($this->wrapped->getBody(), $fields);
        return $fields[$field];
    }

    /**
     * Get application and plugin specific parameters set on the message.
     *
     * @return Collection
     */
    public function getConfig()
    {
        return $this->wrapped->getConfig();
    }

    /**
     * Retrieve an HTTP header by name. Performs a case-insensitive search of all headers.
     *
     * @param string $header Header to retrieve.
     *
     * @return string|null Returns NULL if no matching header is found.
     *                     Returns a Header object if found.
     */
    public function getHeader($header)
    {
        return $this->wrapped->getHeader($header);
    }

    /**
     * Get all headers as a collection
     *
     * @return array
     */
    public function getHeaders()
    {
        return $this->wrapped->getHeaders();
    }

    /**
     * Get an array of message header lines
     *
     * @return array
     */
    public function getHeaderLines()
    {
        return $this->wrapped->getHeaders();
    }

    /**
     * Check if the specified header is present.
     *
     * @param string $header The header to check.
     *
     * @return boolean Returns TRUE or FALSE if the header is present
     */
    public function hasHeader($header)
    {
        return $this->wrapped->hasHeader($header);
    }

    /**
     * Get the raw message headers as a string
     *
     * @return string
     */
    public function getRawHeaders()
    {
        return join("\n", $this->wrapped->getHeaders());
    }

    /**
     * Get the collection of key value pairs that will be used as the query
     * string in the request
     *
     * @return Query
     */
    public function getQuery()
    {
        return $this->wrapped->getQuery();
    }

    /**
     * Get the HTTP method of the request
     *
     * @return string
     */
    public function getMethod()
    {
        return $this->wrapped->getMethod();
    }

    /**
     * Get the URI scheme of the request (http, https, ftp, etc)
     *
     * @return string
     */
    public function getScheme()
    {
        return $this->wrapped->getScheme();
    }

    /**
     * Get the host of the request
     *
     * @return string
     */
    public function getHost()
    {
        return $this->wrapped->getHost();
    }

    /**
     * Get the HTTP protocol version of the request
     *
     * @return string
     */
    public function getProtocolVersion()
    {
        return $this->wrapped->getProtocolVersion();
    }

    /**
     * Get the path of the request (e.g. '/', '/index.html')
     *
     * @return string
     */
    public function getPath()
    {
        return $this->wrapped->getPath();
    }

    /**
     * Get the port that the request will be sent on if it has been set
     *
     * @return integer|null
     */
    public function getPort()
    {
        return $this->wrapped->getPort();
    }

    /**
     * Get the username to pass in the URL if set
     *
     * @return string|null
     */
    public function getUsername()
    {
        return $this->wrapped->getHeader('Php-Auth-User');
    }

    /**
     * Get the password to pass in the URL if set
     *
     * @return string|null
     */
    public function getPassword()
    {
        return $this->wrapped->getHeader('Php-Auth-Pw');
    }

    /**
     * Get the full URL of the request (e.g. 'http://www.guzzle-project.com/')
     * scheme://username:password@domain:port/path?query_string#fragment
     *
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->wrapped->getUrl();
    }

    private function init(array $params)
    {
        foreach ($params as $property => $value) {
            if (property_exists($this, $property)) {
                $this->{$property} = $value;
            }
        }
    }
}
