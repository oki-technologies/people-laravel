<?php

declare(strict_types=1);

namespace Oki\People\Laravel\Http;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * The SDK's PSR-18 client, sent through Laravel's own HTTP client.
 *
 * So `Http::fake()` reaches it in tests, and the application's HTTP
 * middleware and logging see People's calls like any other.
 */
final readonly class LaravelClient implements ClientInterface
{
    public function __construct(
        private Factory $http,
        private int $timeout,
    ) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $headers = array_map(fn (array $values): string => implode(', ', $values), $request->getHeaders());

        try {
            return $this->http
                ->withHeaders($headers)
                ->timeout($this->timeout)
                ->connectTimeout($this->timeout)
                ->send($request->getMethod(), (string) $request->getUri(), ['body' => (string) $request->getBody()])
                ->toPsrResponse();
        } catch (ConnectionException $e) {
            throw new class($e->getMessage(), $request, $e) extends RuntimeException implements NetworkExceptionInterface
            {
                public function __construct(string $message, private readonly RequestInterface $request, ConnectionException $previous)
                {
                    parent::__construct($message, previous: $previous);
                }

                public function getRequest(): RequestInterface
                {
                    return $this->request;
                }
            };
        }
    }
}
