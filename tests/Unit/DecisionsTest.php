<?php

declare(strict_types=1);

namespace Shanginn\Jev\Tests\Unit;

use Amp\{CancelledException, DeferredCancellation};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shanginn\Jev\Answer\{ChoiceAnswer, NoulAnswer, ScoreAnswer};
use Shanginn\Jev\Exception\{ApiException, AuthenticationException, InvalidResponseException, RateLimitException, TransportException};
use Shanginn\Jev\Http\{HttpResponse, RetryPolicy};
use Shanginn\Jev\{Jev, JevClient};
use Shanginn\Jev\Question\{Choice, Noul, Score};
use Shanginn\Jev\Request\{DecisionRequest, MaxPrice, Percentiles, ProviderPreferences, ProviderSort, RequestOptions, Trace};
use Shanginn\Jev\Tests\FixtureTransport;

enum Department: string
{
    case Billing = 'billing';
    case Technical = 'technical';
}
enum Priority: int
{
    case Low = 0;
    case High = 1;
}

final readonly class Triage
{
    public function __construct(
        #[Choice('Which department?', ['billing' => 'Payments', 'technical' => 'Bugs'])]
        public ChoiceAnswer $department,
        #[Noul('Is this a refund request?')]
        public NoulAnswer $refund,
        #[Score('How urgent?', ['Low', 'High'])]
        public ScoreAnswer $urgency,
    ) {}
}

final class InvalidDto
{
    public function __construct(#[Noul('Yes?')] public string $answer) {}
}

final class DecisionsTest extends TestCase
{
    public function testTypedDtoPreservesAnswersAndMetadata(): void
    {
        $transport = new FixtureTransport([FixtureTransport::response([
            'department' => ['type' => 'choice', 'choice' => 'billing', 'confidence' => 0.9, 'probabilities' => (object) ['technical' => 0.1, 'billing' => 0.9]],
            'refund' => ['type' => 'noul', 'noul' => 0.95],
            'urgency' => ['type' => 'score', 'score' => 0.75, 'confidence' => 0.5, 'probabilities' => (object) [0.25, 0.75], 'legend' => (object) ['Low', 'High']],
        ])]);
        $jev = new Jev(new JevClient($transport));
        $result = $jev->evaluateWithResponse(['ticket' => 'Refund please'], Triage::class);
        self::assertSame(Department::Billing, $result->value->department->enum(Department::class));
        self::assertSame(0.9, $result->value->department->probability(Department::Billing));
        self::assertTrue($result->value->refund->isYes(0.8));
        self::assertSame(0.75, $result->value->urgency->score);
        self::assertSame('High', $result->value->urgency->legend[1] ?? null);
        self::assertSame(120, $result->response->usage->totalTokens());
        self::assertSame(0.00000504, $result->response->usage->cost);
        self::assertSame('req-fixture', $result->response->requestId);
        self::assertSame('gen-fixture', $result->response->id);
        self::assertSame('TypeSafe', $result->response->provider);
    }

    public function testAllOptionsAndNumericKeysSerializeWithCorrectJsonShapes(): void
    {
        $request = new DecisionRequest(new \stdClass(), [0 => Choice::fromEnum('Priority?', Priority::class)], options: new RequestOptions(
            provider: new ProviderPreferences(order: ['typesafe'], allowFallbacks: false, requireParameters: true, dataCollection: 'deny', zdr: true, only: ['typesafe'], ignore: [], quantizations: ['fp16'], sort: new ProviderSort('latency', 'none'), maxPrice: new MaxPrice(prompt: '0.1', completion: '0'), preferredMinThroughput: new Percentiles(p50: 100), preferredMaxLatency: 5.0, enforceDistillableText: false),
            sessionId: 'session-1',
            user: 'customer-1',
            trace: new Trace(traceId: 'trace-1', metadata: ['release' => 'test']),
        ));
        $data = json_decode(json_encode($request, JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR);
        self::assertInstanceOf(\stdClass::class, $data->state);
        self::assertInstanceOf(\stdClass::class, $data->questions);
        self::assertInstanceOf(\stdClass::class, $data->questions->{'0'}->criteria);
        self::assertFalse($data->provider->allow_fallbacks);
        self::assertFalse($data->provider->enforce_distillable_text);
        self::assertSame('0', $data->provider->max_price->completion);
        self::assertSame('trace-1', $data->trace->trace_id);
        self::assertSame('test', $data->trace->release);
        self::assertSame('session-1', $data->session_id);
        self::assertSame('{"type":"noul","instructions":"Yes?","criteria":{"true":"Yes","false":"No"}}', json_encode(new Noul('Yes?', 'Yes', 'No')));
    }

    public function testMinimalResponsesDoNotInventConfidence(): void
    {
        $transport = new FixtureTransport([FixtureTransport::response(['answer' => ['type' => 'choice', 'choice' => '0']])]);
        $answer = (new Jev(new JevClient($transport)))->choice('hello', Choice::fromEnum('Priority?', Priority::class));
        self::assertSame(Priority::Low, $answer->enum(Priority::class));
        self::assertNull($answer->confidence);
        self::assertNull($answer->probabilities);
        self::assertNull($answer->probability(0));
    }

    /** @return iterable<string, array{string}> */
    public static function malformedResponses(): iterable
    {
        $valid = ['model' => 'model', 'answers' => ['answer' => ['type' => 'noul', 'noul' => 0.5]], 'usage' => ['input_tokens' => 1, 'output_tokens' => 0]];
        yield 'invalid JSON' => ['{'];
        yield 'array root' => ['[]'];
        foreach (['answers', 'model', 'usage'] as $key) {
            $data = $valid;
            unset($data[$key]);
            yield 'missing ' . $key => [json_encode($data, JSON_THROW_ON_ERROR)];
        }
        foreach ([true, '0.8', -0.01, 1.01, null] as $i => $value) {
            $data = $valid;
            $data['answers']['answer']['noul'] = $value;
            yield 'bad noul ' . $i => [json_encode($data, JSON_THROW_ON_ERROR)];
        }
        $data = $valid;
        $data['answers']['answer']['type'] = 'score';
        yield 'wrong answer type' => [json_encode($data, JSON_THROW_ON_ERROR)];
        $data = $valid;
        $data['answers']['other'] = $data['answers']['answer'];
        yield 'extra answer' => [json_encode($data, JSON_THROW_ON_ERROR)];
        $data = $valid;
        $data['usage']['input_tokens'] = '1';
        yield 'string token count' => [json_encode($data, JSON_THROW_ON_ERROR)];
        $data = $valid;
        $data['usage']['cost'] = -1;
        yield 'negative cost' => [json_encode($data, JSON_THROW_ON_ERROR)];
        yield 'error in success body' => ['{"error":{"code":500,"message":"upstream error"}}'];
    }

    #[DataProvider('malformedResponses')]
    public function testMalformedResponsesAreRejected(string $json): void
    {
        $jev = new Jev(new JevClient(new FixtureTransport([new HttpResponse(200, $json)])));
        $this->expectException(InvalidResponseException::class);
        $jev->noul('hello', new Noul('Yes?'));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidChoices(): iterable
    {
        yield 'unknown label' => [['type' => 'choice', 'choice' => 'other']];
        yield 'bad confidence' => [['type' => 'choice', 'choice' => 'billing', 'confidence' => 2]];
        yield 'missing option probability' => [['type' => 'choice', 'choice' => 'billing', 'probabilities' => (object) ['billing' => 1]]];
        yield 'invalid distribution' => [['type' => 'choice', 'choice' => 'billing', 'probabilities' => (object) ['billing' => 0.8, 'technical' => 0.8]]];
        yield 'numeric string probability' => [['type' => 'choice', 'choice' => 'billing', 'probabilities' => (object) ['billing' => '1', 'technical' => 0]]];
    }

    /** @param array<string, mixed> $answer */
    #[DataProvider('invalidChoices')]
    public function testInvalidChoicesAreRejected(array $answer): void
    {
        $jev = new Jev(new JevClient(new FixtureTransport([FixtureTransport::response(['answer' => $answer])])));
        $this->expectException(InvalidResponseException::class);
        $jev->choice('hello', Choice::fromEnum('Department?', Department::class));
    }

    public function testScoreCannotEscapeItsScale(): void
    {
        $jev = new Jev(new JevClient(new FixtureTransport([FixtureTransport::response(['answer' => ['type' => 'score', 'score' => 2]])])));
        $this->expectException(InvalidResponseException::class);
        $jev->score('hello', new Score('How much?', ['Low', 'High']));
    }

    public function testSchemaErrorsFailBeforeNetwork(): void
    {
        $transport = new FixtureTransport([]);
        try {
            (new Jev(new JevClient($transport)))->evaluate('hello', InvalidDto::class);
            self::fail();
        } catch (\InvalidArgumentException) {
            self::assertSame([], $transport->requests);
        }
    }

    public function testRateLimitRetriesThenSucceeds(): void
    {
        $transport = new FixtureTransport([new HttpResponse(429, 'private data', ['Retry-After' => ['0']]), FixtureTransport::response(['answer' => ['type' => 'noul', 'noul' => 1]])]);
        self::assertTrue((new Jev(new JevClient($transport)))->noul('hello', new Noul('Yes?'))->isYes());
        self::assertCount(2, $transport->requests);
        self::assertSame($transport->requests[0], $transport->requests[1]);
    }

    public function testRetriesAreBoundedAndErrorBodiesAreNotExposed(): void
    {
        $transport = new FixtureTransport(array_fill(0, 3, new HttpResponse(503, 'SECRET_ECHO')));
        try {
            (new Jev(new JevClient($transport, new RetryPolicy(initialDelay: 0))))->noul('hello', new Noul('Yes?'));
            self::fail();
        } catch (ApiException $e) {
            self::assertSame(503, $e->status);
            self::assertStringNotContainsString('SECRET_ECHO', (string) $e);
            self::assertNull($e->getPrevious());
        }
        self::assertCount(3, $transport->requests);
    }

    public function testLongRetryAfterReturnsControlWithoutEarlyRetry(): void
    {
        $transport = new FixtureTransport([new HttpResponse(429, '{}', ['retry-after' => ['120']])]);
        try {
            (new Jev(new JevClient($transport)))->noul('hello', new Noul('Yes?'));
            self::fail();
        } catch (RateLimitException $e) {
            self::assertSame(120.0, $e->retryAfter);
        }
        self::assertCount(1, $transport->requests);
    }

    public function testAuthenticationIsNotRetried(): void
    {
        $transport = new FixtureTransport([new HttpResponse(401, '{}')]);
        try {
            (new Jev(new JevClient($transport)))->noul('hello', new Noul('Yes?'));
            self::fail();
        } catch (AuthenticationException $e) {
            self::assertSame(401, $e->status);
        }
        self::assertCount(1, $transport->requests);
    }

    public function testTransportErrorsAreNotRetried(): void
    {
        $transport = new FixtureTransport([new TransportException('Timeout')]);
        try {
            (new Jev(new JevClient($transport)))->noul('hello', new Noul('Yes?'));
            self::fail();
        } catch (TransportException) {
            self::assertCount(1, $transport->requests);
        }
    }

    public function testCancellationPreventsNetworkRequest(): void
    {
        $cancellation = new DeferredCancellation();
        $cancellation->cancel();
        $transport = new FixtureTransport([]);
        try {
            (new Jev(new JevClient($transport)))->noul('hello', new Noul('Yes?'), cancellation: $cancellation->getCancellation());
            self::fail();
        } catch (CancelledException) {
            self::assertSame([], $transport->requests);
        }
    }

    public function testCancellationInterruptsRetryDelay(): void
    {
        $cancellation = new DeferredCancellation();
        $transport = new FixtureTransport([new HttpResponse(429, '{}', ['Retry-After' => ['5']])]);
        $timer = \Revolt\EventLoop::delay(0.01, static fn() => $cancellation->cancel());
        try {
            (new Jev(new JevClient($transport)))->noul('hello', new Noul('Yes?'), cancellation: $cancellation->getCancellation());
            self::fail();
        } catch (CancelledException) {
            self::assertCount(1, $transport->requests);
        } finally {
            \Revolt\EventLoop::cancel($timer);
        }
    }
}
