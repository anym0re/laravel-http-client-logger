<?php

namespace Bilfeldt\LaravelHttpClientLogger\Middleware;

use Bilfeldt\LaravelHttpClientLogger\HttpLoggerInterface;
use Bilfeldt\LaravelHttpClientLogger\HttpLoggingFilterInterface;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class LoggingMiddleware
{
    protected HttpLoggerInterface $logger;

    protected HttpLoggingFilterInterface $filter;

    public function __construct(HttpLoggerInterface $logger, HttpLoggingFilterInterface $filter)
    {
        $this->logger = $logger;
        $this->filter = $filter;
    }

    /**
     * Called when the middleware is handled by the client.
     *
     * @param array $context
     *
     * @return callable
     */
    public function __invoke($context = [], $config = []): callable
    {
        return function (callable $handler) use ($context, $config): callable {
            return function (RequestInterface $request, array $options) use ($context, $config, $handler): PromiseInterface {
                $start = microtime(true);

                $promise = $handler($request, $options);

                return $promise->then(
                    function (ResponseInterface $response) use ($context, $config, $request, $start) {
                        $sec = microtime(true) - $start;

                        $body = $this->encoding($response->getBody());
                        $response = $response->withBody($body);


                        if ($this->filter->shouldLog($request, $response, $sec, $context, $config)) {
                            $this->logger->log($request, $response, $sec, $context, $config);
                        }

                        return $response;
                    }
                );
            };
        };
    }

    private function encoding(StreamInterface $stream): StreamInterface
    {
        $encode = array('UTF-8','EUC-KR');
        $content = $stream->getContents();
        $str_encode = mb_detect_encoding($content, $encode);
        if($str_encode === 'EUC-KR') {
            $content = mb_convert_encoding($content,'UTF-8',$str_encode);
        }


        return Psr7\Utils::streamFor($content);
    }
}
