<?php

declare(strict_types=1);

namespace Shanginn\Jev;

use Amp\Cancellation;
use Shanginn\Jev\Exception\{ApiException, AuthenticationException, RateLimitException};
use Shanginn\Jev\Http\{RetryPolicy, Transport};
use Shanginn\Jev\Request\DecisionRequest;
use Shanginn\Jev\Response\{DecisionResponse, ResponseDecoder};

final readonly class JevClient
{
    public function __construct(private Transport $transport, private RetryPolicy $retryPolicy = new RetryPolicy(), private ResponseDecoder $decoder = new ResponseDecoder()) {}

    public function decide(DecisionRequest $request, ?Cancellation $cancellation = null): DecisionResponse
    {
        $json = json_encode($request, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        for ($retry = 0; ; ++$retry) {
            $cancellation?->throwIfRequested();
            $response = $this->transport->send($json, $cancellation);
            $requestId = $response->header('x-request-id');
            if ($response->status >= 200 && $response->status < 300) {
                return $this->decoder->decode($response->body, $request, $requestId);
            }
            $retryAfter = self::retryAfter($response->header('retry-after'));
            $delay = $this->retryPolicy->delay($response->status, $retry, $retryAfter);
            if ($delay !== null) {
                \Amp\delay($delay, cancellation: $cancellation);
                continue;
            }
            $class = match ($response->status) {
                401, 403 => AuthenticationException::class,
                429 => RateLimitException::class,
                default => ApiException::class,
            };
            throw new $class($response->status, $requestId, $retryAfter);
        }
    }

    private static function retryAfter(?string $header): ?float
    {
        if ($header === null) {
            return null;
        }
        if (is_numeric($header)) {
            $value = (float) $header;
            return is_finite($value) ? max(0.0, $value) : null;
        }
        $timestamp = strtotime($header);
        return $timestamp === false ? null : (float) max(0, $timestamp - time());
    }
}
