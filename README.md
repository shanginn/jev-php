# JEV PHP

[![CI](https://github.com/shanginn/jev-php/actions/workflows/ci.yml/badge.svg)](https://github.com/shanginn/jev-php/actions/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/v/shanginn/jev-php)](https://packagist.org/packages/shanginn/jev-php)
[![PHP](https://img.shields.io/packagist/php-v/shanginn/jev-php)](composer.json)
[![License](https://img.shields.io/packagist/l/shanginn/jev-php)](LICENSE)

Small, strongly typed **PHP 8.5** SDK for [TypeSafe JEV 1.13](https://openrouter.ai/typesafe/jev-1.13) through OpenRouter.

Give JEV context and a bounded question. Get a choice, a score, or a yes/no probability that your code can use directly. Define questions with ordinary PHP objects, batch them in one request, or describe your entire result using a readonly class and attributes.

```sh
composer require shanginn/jev-php
```

## A decision in a few lines

```php
use Shanginn\Jev\Jev;
use Shanginn\Jev\Question\Noul;

$jev = Jev::create(getenv('OPENROUTER_API_KEY') ?: '');

$answer = $jev->noul(
    'I was charged twice. Please refund the duplicate payment.',
    new Noul('Does the customer request a refund?'),
);

$answer->noul;       // Probability from 0 to 1.
$answer->isYes(0.8); // Your threshold; no automatic business action.
```

Missing credentials fail locally. The library does not read environment files, print requests, or configure global application state.

## Choose from your own enum

```php
use Shanginn\Jev\Question\Choice;

enum Department: string
{
    case Billing = 'billing';
    case Technical = 'technical';
    case Sales = 'sales';
}

$answer = $jev->choice(
    'Please refund the duplicate payment.',
    Choice::fromEnum('Which team should handle this ticket?', Department::class, [
        'billing' => 'Invoices, payments and refunds',
        'technical' => 'Software bugs and integration failures',
        'sales' => 'Plans, pricing and new purchases',
    ]),
);

$department = $answer->enum(Department::class); // Inferred as Department.
$answer->choice;                              // Raw string label.
$answer->confidence;                          // ?float
$answer->probability(Department::Billing);      // ?float
```

String- and integer-backed enums work. A choice can also be created directly with `new Choice('Question?', ['a' => 'Description A', 'b' => 'Description B'])`.

## Rate against ordered levels

```php
use Shanginn\Jev\Question\Score;

$answer = $jev->score(
    'Export crashes in Safari, but works in Chrome.',
    new Score('How severe is the bug?', [
        'Cosmetic; no functionality affected',
        'Broken feature with a workaround',
        'Blocking issue with no workaround',
    ]),
);

$answer->score;         // float from 0 to 2, including values between levels.
$answer->legend;        // ?array<int, string>
$answer->probabilities; // ?array<int, float>
$answer->confidence;    // ?float
```

Scores are probability-weighted positions on your scale, not integer classifications. Supply 2–10 ordered descriptions.

## Batch questions over shared context

```php
use Shanginn\Jev\Question\{Choice, Noul, Score};

$response = $jev->decide(
    state: ['message' => 'Please refund the duplicate payment.', 'customer_since' => 2021],
    questions: [
        'department' => new Choice('Which department?', [
            'billing' => 'Payments and refunds',
            'technical' => 'Software bugs',
        ]),
        'refund' => new Noul('Is a refund explicitly requested?'),
        'urgency' => new Score('How urgent?', ['Routine', 'Time-sensitive', 'Critical outage']),
    ],
);

$response->choice('department')->choice;
$response->noul('refund')->noul;
$response->score('urgency')->score;
$response->usage->inputTokens;
$response->usage->outputTokens;
$response->usage->cost; // Optional provider-reported USD cost.
$response->id;
$response->provider;
```

State accepts a string, JSON-compatible PHP array, or `stdClass`. All questions share that state and run in one API request. Answer ids must exactly match the request, and typed getters reject mismatched answer types.

## Your result as a PHP object

```php
use Shanginn\Jev\Answer\{ChoiceAnswer, NoulAnswer, ScoreAnswer};
use Shanginn\Jev\Question\{Choice, Noul, Score};

final readonly class TicketDecision
{
    public function __construct(
        #[Choice('Which department?', ['billing' => 'Payments and refunds', 'technical' => 'Software bugs'])]
        public ChoiceAnswer $department,
        #[Noul('Does the customer request a refund?')]
        public NoulAnswer $refund,
        #[Score('How urgent?', ['Routine', 'Time-sensitive', 'Critical outage'])]
        public ScoreAnswer $urgency,
    ) {}
}

$decision = $jev->evaluate('Please refund my duplicate charge.', TicketDecision::class);
// PHPStan and IDEs infer TicketDecision.
$decision->department->choice;
$decision->refund->isYes(0.8);

// Keep usage, model, request id and cost alongside the DTO:
$result = $jev->evaluateWithResponse('Please refund my duplicate charge.', TicketDecision::class);
$result->value;    // TicketDecision
$result->response; // DecisionResponse
```

Every constructor parameter must have exactly one question attribute and its corresponding non-nullable answer type. Questions use the parameter names as ids. The SDK validates the schema before making a request and calls your constructor normally; it does not bypass constructors or write private properties.

## Concurrent calls and cancellation

The transport uses [Amp](https://amphp.org/http-client) for connection reuse and non-blocking I/O. Calls are straightforward in ordinary PHP and can run concurrently inside fibers:

```php
use Amp\TimeoutCancellation;
use Shanginn\Jev\Question\Noul;
use function Amp\async;
use function Amp\Future\await;

$deadline = new TimeoutCancellation(30);
$question = new Noul('Is a refund requested?');
$answers = await([
    async(fn () => $jev->noul('Refund my order, please.', $question, cancellation: $deadline)),
    async(fn () => $jev->noul('How do I change my password?', $question, cancellation: $deadline)),
], $deadline);
```

A shared cancellation covers network calls and retry delays. Prefer one mixed batch when several questions use the same state. There is no token stream: JEV returns decisions as a complete JSON response.

## Errors and retry policy

```php
use Shanginn\Jev\Exception\{ApiException, InvalidResponseException, TransportException};
use Shanginn\Jev\Http\RetryPolicy;

$jev = Jev::create(
    getenv('OPENROUTER_API_KEY') ?: '',
    timeout: 30,
    appTitle: 'My application',
    httpReferer: 'https://example.com',
    retryPolicy: new RetryPolicy(maxRetries: 2),
);

try {
    $answer = $jev->noul('Refund please.', new Noul('Is a refund requested?'));
} catch (ApiException $error) {
    // Also catches AuthenticationException and RateLimitException.
    $error->status;
    $error->requestId;
    $error->retryAfter;
} catch (InvalidResponseException $error) {
    // Malformed JSON, missing answers, wrong types, or invalid decision values.
} catch (TransportException $error) {
    // Connection failure or transport timeout.
}
```

By default, up to two retries apply to HTTP 408, 429, 500, 502, 503, 504, 524 and 529, with exponential backoff and jitter. `Retry-After` seconds and HTTP dates are honored; a delay exceeding `maxDelay` returns the error to you instead of retrying early. Network failures are not automatically retried because the provider may already have processed the request. Retrying a completed upstream request can incur additional cost. Set `maxRetries: 0` to disable retries.

Cancellation raises Amp's `CancelledException`. Invalid local configuration raises `InvalidArgumentException`; non-JSON state raises `JsonException`. Error messages omit upstream bodies and chained transport exceptions, which can contain private data. Keep application exception argument capture and HTTP logging configured appropriately for your secrets.

## API coverage and limits

- Choice, Score and Noul; optional yes/no descriptions; enum conversion; mixed batches; DTOs.
- Model selection, provider routing, user/session ids, trace metadata and application attribution.
- Typed usage, cost, generation id, provider and request-id metadata.
- Request and response validation, configurable retries, timeouts and cancellation; injectable transport for tests.

The default model is pinned to `typesafe/jev-1.13`. Use `model: Jev::LATEST` (`~typesafe/jev-latest`) to follow the current alias, or pass another Decisions model id.

This wraps OpenRouter's **alpha Decisions API** at `https://openrouter.ai/api/alpha/decisions`. It is not a chat, media, embeddings or free-form generation API. OpenRouter currently documents string instructions and string rubric descriptions; the richer structured instructions in TypeSafe's direct API are outside this wrapper's contract. Optional confidence, probabilities and score legends stay `null` when the provider omits them. A Noul is a yes/no probability and has no separate confidence field.

See [API reference](docs/api.md), [architecture](docs/architecture.md), and [release process](docs/publishing.md).

## Run the examples and tests

```sh
composer install
cp .env.example .env
# Edit .env locally and set OPENROUTER_API_KEY. Never commit it.
php examples/choice.php
php examples/batch.php
php examples/typed-object.php
php examples/concurrent.php
php examples/routing.php
composer check
composer test:live
```

Only examples and the explicit live test load the ignored `.env`. `composer test` is offline and needs no key. Live tests make three small paid API calls using synthetic tickets; they print only result metadata. CI never needs the OpenRouter key.

If your local PHP is older, use the provided PHP 8.5 environment:

```sh
docker build -t jev-php-dev -f tools/Dockerfile .
docker run --rm -v "$PWD":/app -w /app jev-php-dev composer install
docker run --rm -v "$PWD":/app -w /app jev-php-dev composer check
docker run --rm -v "$PWD":/app -w /app jev-php-dev composer test:live
```

The structure and typed-object ergonomics are inspired by [shanginn/openai-sdk-php](https://github.com/shanginn/openai-sdk-php/tree/master). This is an independent SDK, not an official TypeSafe or OpenRouter package. MIT licensed.
