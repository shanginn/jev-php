<?php

declare(strict_types=1);

namespace Shanginn\Jev\Http;

use Amp\Cancellation;
use Amp\CancelledException;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Shanginn\Jev\Exception\TransportException;

final readonly class AmpTransport implements Transport
{
    public const string ENDPOINT = 'https://openrouter.ai/api/alpha/decisions';
    private HttpClient $client;

    public function __construct(
        #[\SensitiveParameter]
        private string $apiKey,
        private string $endpoint = self::ENDPOINT,
        private float $timeout = 60.0,
        private ?string $httpReferer = null,
        private ?string $appTitle = null,
        private ?string $appCategories = null,
        ?HttpClient $client = null,
    ) {
        if (trim($apiKey) === '' || preg_match('/[\r\n]/', $apiKey)) {
            throw new \InvalidArgumentException('A non-empty API key without line breaks is required.');
        }
        $url = parse_url($endpoint);
        if ($url === false || ($url['scheme'] ?? '') !== 'https' || empty($url['host']) || isset($url['user'], $url['pass']) || isset($url['user']) || isset($url['query']) || isset($url['fragment'])) {
            throw new \InvalidArgumentException('The endpoint must be an HTTPS URL without credentials, query or fragment.');
        }
        if (!is_finite($timeout) || $timeout <= 0) {
            throw new \InvalidArgumentException('Timeout must be finite and positive.');
        }
        foreach ([$httpReferer, $appTitle, $appCategories] as $value) {
            if ($value !== null && preg_match('/[\r\n]/', $value)) {
                throw new \InvalidArgumentException('Attribution headers cannot contain line breaks.');
            }
        }
        // Retries belong to JevClient; do not follow a redirect with credentials.
        $this->client = $client ?? (new HttpClientBuilder())->followRedirects(0)->retry(0)->build();
    }

    public function send(string $json, ?Cancellation $cancellation = null): HttpResponse
    {
        $cancellation?->throwIfRequested();
        try {
            $request = new Request($this->endpoint, 'POST');
            $request->setHeader('Authorization', 'Bearer ' . $this->apiKey);
            $request->setHeader('Content-Type', 'application/json');
            $request->setHeader('Accept', 'application/json');
            foreach (['HTTP-Referer' => $this->httpReferer, 'X-OpenRouter-Title' => $this->appTitle, 'X-OpenRouter-Categories' => $this->appCategories] as $name => $value) {
                if ($value !== null) {
                    $request->setHeader($name, $value);
                }
            }
            $request->setBody($json);
            $request->setTransferTimeout($this->timeout);
            $request->setInactivityTimeout($this->timeout);
            $request->setTcpConnectTimeout(min(10.0, $this->timeout));
            $request->setBodySizeLimit(16 * 1024 * 1024);
            $response = $this->client->request($request, $cancellation);
            return new HttpResponse($response->getStatus(), $response->getBody()->buffer($cancellation), $response->getHeaders());
        } catch (CancelledException $exception) {
            throw $exception;
        } catch (\Throwable) {
            // Transport exceptions may retain the Authorization header in their request.
            throw new TransportException('The Decisions HTTP request failed or timed out.');
        }
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return ['endpoint' => $this->endpoint, 'timeout' => $this->timeout, 'apiKey' => '[redacted]'];
    }

    /** @return never */
    public function __serialize(): array
    {
        throw new \LogicException('A transport containing credentials cannot be serialized.');
    }
}
