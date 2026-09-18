<?php

declare(strict_types=1);

namespace Shanginn\Jev\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shanginn\Jev\Http\{AmpTransport, RetryPolicy};
use Shanginn\Jev\Question\{Choice, Noul, Score};
use Shanginn\Jev\Request\{DecisionRequest, MaxPrice, Percentiles, ProviderPreferences, RequestOptions, Trace};

final class ValidationTest extends TestCase
{
    /** @return iterable<string, array{\Closure(): mixed}> */
    public static function invalidInputs(): iterable
    {
        yield 'empty instructions' => [static fn() => new Noul(' ')];
        yield 'partial yes/no rubric' => [static fn() => new Noul('Yes?', yes: 'Yes')];
        yield 'single choice' => [static fn() => new Choice('Pick', ['a' => 'A'])];
        yield 'empty label' => [static fn() => new Choice('Pick', ['' => 'A', 'b' => 'B'])];
        yield 'one score level' => [static fn() => new Score('Rate', ['Only'])];
        yield 'eleven score levels' => [static fn() => new Score('Rate', array_fill(0, 11, 'Level'))];
        yield 'empty questions' => [static fn() => new DecisionRequest('hello', [])];
        yield 'empty model' => [static fn() => new DecisionRequest('hello', ['a' => new Noul('Yes?')], '')];
        yield 'long session' => [static fn() => new RequestOptions(sessionId: str_repeat('a', 257))];
        yield 'negative price' => [static fn() => new MaxPrice(prompt: '-1')];
        yield 'infinite routing value' => [static fn() => new Percentiles(p50: INF)];
        yield 'invalid data policy' => [static fn() => new ProviderPreferences(dataCollection: 'maybe')];
        yield 'trace field override' => [static fn() => new Trace(metadata: ['trace_id' => 'override'])];
        yield 'negative retries' => [static fn() => new RetryPolicy(-1)];
        yield 'infinite timeout' => [static fn() => new AmpTransport('fixture', timeout: INF)];
        yield 'empty token' => [static fn() => new AmpTransport(' ')];
        yield 'header injection' => [static fn() => new AmpTransport('fixture', appTitle: "test\r\nX-Evil: yes")];
        yield 'insecure endpoint' => [static fn() => new AmpTransport('fixture', endpoint: 'http://example.com')];
        yield 'embedded credentials' => [static fn() => new AmpTransport('fixture', endpoint: 'https://user@example.com')];
    }

    /** @param \Closure(): mixed $create */
    #[DataProvider('invalidInputs')]
    public function testInvalidInputsFailLocally(\Closure $create): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $create();
    }
}
