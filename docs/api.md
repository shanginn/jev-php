# API reference

## Layers

`Jev::create($apiKey, ...)` constructs the default Amp transport and client. Reuse this instance across requests. `Jev` provides `choice`, `score`, `noul`, `decide`, `evaluate` and `evaluateWithResponse`. Each accepts optional `RequestOptions` and Amp `Cancellation`.

For direct request objects or a custom transport:

```php
use Shanginn\Jev\{Jev, JevClient};
use Shanginn\Jev\Http\{AmpTransport, RetryPolicy};
use Shanginn\Jev\Question\Noul;
use Shanginn\Jev\Request\DecisionRequest;

$client = new JevClient(
    new AmpTransport(getenv('OPENROUTER_API_KEY') ?: ''),
    new RetryPolicy(maxRetries: 0),
);
$response = $client->decide(new DecisionRequest('Refund please.', ['refund' => new Noul('Is a refund requested?')]));
$jev = new Jev($client);
```

`Transport::send(string $json, ?Cancellation $cancellation): HttpResponse` is the only interface a fake or alternative transport needs. A custom transport owns authentication, TLS, timeout, redirect and cancellation behavior. An injected Amp `HttpClient` also owns its own retry/redirect configuration; the default transport disables both so retries occur in one place and redirects cannot forward credentials.

## Questions

| Class | Constructor | Answer |
| --- | --- | --- |
| `Choice` | `instructions`, `criteria: array<string\|int, string>` | `ChoiceAnswer` |
| `Noul` | `instructions`, optional `yes` and `no` strings | `NoulAnswer` |
| `Score` | `instructions`, `criteria: list<string>` with 2–10 entries | `ScoreAnswer` |

`Choice::fromEnum($instructions, $enumClass, $descriptions = [])` uses backed enum values as labels and defaults descriptions to case names. Both Noul descriptions must be supplied together. Numeric choice and question keys are serialized as JSON objects, not arrays. Use `new stdClass()` for an empty state object and `[]` for an empty state array.

Question objects are reusable readonly value objects and also constructor-parameter attributes. Unknown `Question` implementations are not supported by the built-in decoder.

## Answers

| Answer | Fields |
| --- | --- |
| `ChoiceAnswer` | `choice: string`, `probabilities: ?array<string\|int, float>`, `confidence: ?float` |
| `NoulAnswer` | `noul: float`; `isYes(float $threshold = 0.5): bool` |
| `ScoreAnswer` | `score: float`, `probabilities: ?array<int, float>`, `legend: ?array<int, string>`, `confidence: ?float` |

Each implements `Answer::type(): QuestionType`. `ChoiceAnswer::enum($enumClass)` returns the actual backed enum case, with generic static inference. `probability($labelOrEnum)` returns a probability or `null` if unavailable. Missing enum cases throw `UnexpectedValueException`.

`DecisionResponse` contains `model`, keyed `answers`, `Usage`, and nullable `id`, `provider`, `requestId`. `answer($id)` gets any answer; `choice`, `noul`, and `score` enforce its type. Missing ids throw `OutOfBoundsException`; the wrong getter throws `LogicException`.

`Usage` contains `inputTokens`, `outputTokens`, optional `cost` in USD and `totalTokens()`. Costs are provider-reported, not estimated by the SDK. `TypedResponse<T>` combines `value: T` with `response: DecisionResponse`.

## Request options

```php
use Shanginn\Jev\Request\{MaxPrice, Percentiles, ProviderPreferences, ProviderSort, RequestOptions, Trace};

$options = new RequestOptions(
    provider: new ProviderPreferences(
        order: ['typesafe'],
        allowFallbacks: false,
        requireParameters: true,
        dataCollection: 'deny',
        zdr: true,
        only: ['typesafe'],
        ignore: [],
        quantizations: ['fp16'],
        sort: new ProviderSort(by: 'latency', partition: 'none'),
        maxPrice: new MaxPrice(prompt: '0.10', completion: '0.10'),
        preferredMinThroughput: new Percentiles(p50: 100, p90: 50),
        preferredMaxLatency: 5.0,
        enforceDistillableText: false,
    ),
    sessionId: 'ticket-123',
    user: 'opaque-customer-id',
    trace: new Trace(
        traceId: 'trace-123', traceName: 'triage', spanName: 'decision',
        generationName: 'routing', parentSpanId: 'parent-123',
        metadata: ['release' => '1.0.0'],
    ),
);
```

This demonstrates every field, not a recommended combination. Constraints can leave no eligible provider; a field's availability depends on OpenRouter and the model/provider. No privacy or retention setting is implied unless you explicitly request it.

Provider preferences mirror OpenRouter's schema. Null fields are omitted; `false`, zero, and empty lists are preserved. Sort accepts a string (`price`, `latency`, `throughput`, `exacto`) or `ProviderSort`. Provider and quantization strings remain open for future additions. `MaxPrice` accepts decimal strings for `prompt`, `completion`, `request`, `image` and `audio`; prompt/completion units are USD per million tokens. Throughput/latency accept a number or `Percentiles(p50, p75, p90, p99)`.

`sessionId` and `user` support up to 256 Unicode characters. Trace metadata must be JSON-compatible and cannot override named trace fields.

`Jev::create` and `AmpTransport` accept `httpReferer`, `appTitle` and `appCategories`, sent as `HTTP-Referer`, `X-OpenRouter-Title` and `X-OpenRouter-Categories`.

## Validation and errors

The decoder checks JSON object shapes, exact answer ids, question types, selected labels, score bounds, finite probabilities/confidence in [0, 1], probability keys and sums (0.01 rounding tolerance), supplied score legends, and non-negative usage. Unknown response fields are ignored for forward compatibility. Provider omissions of optional fields are represented by `null`. This validates the data contract, not whether an AI decision is correct.

`JevException` is the common SDK exception base. `ApiException` has `status`, `requestId`, and `retryAfter`; HTTP 401/403 specialize to `AuthenticationException`, and 429 to `RateLimitException`. All other non-2xx statuses, including redirects, are API errors. A malformed/error-shaped 2xx body is `InvalidResponseException`. Transport failures become `TransportException` without embedding the original exception or request. Amp cancellation propagates unchanged.

Timeout is per HTTP attempt. With retries, total time may be longer; pass `TimeoutCancellation` to set a deadline for the whole operation. Retry policy defaults: two retries, initial delay 0.5 seconds, maximum delay 10 seconds, statuses `[408,429,500,502,503,504,524,529]`. Configure `statuses` when needed. Serialization, response validation, transport and cancellation failures are not retried.

## Upstream contract

Reviewed against [OpenRouter's OpenAPI schema](https://openrouter.ai/openapi.json), [Decisions documentation](https://openrouter.ai/docs/client-sdks/python/sdks/decisions/README), and [TypeSafe's primitive definitions](https://docs.typesafe.ai/primitives) on 2026-09-18. OpenRouter's alpha API is narrower than TypeSafe's direct `/v1/systemone` endpoint; this package intentionally uses the OpenRouter contract and OpenRouter credentials.
