<?php
namespace InterNations\Component\HttpMock;

use GuzzleHttp\Client;
use GuzzleHttp\Event\EventInterface;
use GuzzleHttp\Exception\RequestException;
use hmmmath\Fibonacci\FibonacciFactory;
use Symfony\Component\Process\Process;
use RuntimeException;
use GuzzleHttp\Exception\CurlException;

class Server extends Process
{
    private $port;

    private $host;

    private $client;

    public function __construct($port, $host)
    {
        $this->port = $port;
        $this->host = $host;
        parent::__construct(
            sprintf(
                'exec php -dalways_populate_raw_post_data=-1 -derror_log= -S %s -t public/ public/index.php',
                $this->getConnectionString()
            ),
            __DIR__ . '/../'
        );
        $this->setTimeout(null);
    }

    public function start(callable $callback = null)
    {
        parent::start($callback);

        $this->pollWait();
    }

    public function stop($timeout = 10, $signal = null)
    {
        return parent::stop($timeout, $signal);
    }

    public function getClient()
    {
        return $this->client ?: $this->client = $this->createClient();
    }

    private function createClient()
    {
        $client = new Client([
            'base_url' => $this->getBaseUrl()
        ]);
        $client->getEmitter()->on(
            'error',
            static function (EventInterface $event) {
                $event->stopPropagation();
            }
        );

        return $client;
    }

    public function getBaseUrl()
    {
        return sprintf('http://%s', $this->getConnectionString());
    }

    public function getConnectionString()
    {
        return sprintf('%s:%d', $this->host, $this->port);
    }

    /**
     * @param Expectation[] $expectations
     * @throws RuntimeException
     */
    public function setUp(array $expectations)
    {
        /** @var Expectation $expectation */
        foreach ($expectations as $expectation) {
            $request = $this->getClient()->createRequest('POST', '/_expectation');
            $request->getBody()->replaceFields(
                [
                    'matcher'  => serialize($expectation->getMatcherClosures()),
                    'limiter'  => serialize($expectation->getLimiter()),
                    'response' => serialize($expectation->getResponse()),
                ]
            );
            $response = $this->getClient()->send($request);
            if (intval($response->getStatusCode()) !== 201) {
                throw new RuntimeException('Could not set up expectations');
            }
        }
    }

    public function clean()
    {
        if (!$this->isRunning()) {
            $this->start();
        }

        $this->getClient()->delete('/_all');
    }

    private function pollWait()
    {
        foreach (FibonacciFactory::sequence(50000, 10000) as $sleepTime) {
            try {
                usleep($sleepTime);
                $this->getClient()->head('/_me');
                break;
            } catch (RequestException $e) {
                continue;
            }
        }
    }
}
