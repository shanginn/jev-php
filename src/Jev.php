<?php

declare(strict_types=1);

namespace Shanginn\Jev;

use Amp\Cancellation;
use Shanginn\Jev\Answer\{ChoiceAnswer, NoulAnswer, ScoreAnswer};
use Shanginn\Jev\Http\{AmpTransport, RetryPolicy};
use Shanginn\Jev\Question\{Choice, Noul, Question, Score};
use Shanginn\Jev\Request\{DecisionRequest, RequestOptions};
use Shanginn\Jev\Response\{DecisionResponse, TypedResponse};
use Shanginn\Jev\Schema\DecisionSchema;

final readonly class Jev
{
    public const string MODEL = 'typesafe/jev-1.13';
    public const string LATEST = '~typesafe/jev-latest';

    public function __construct(private JevClient $client, public string $model = self::MODEL) {}

    public static function create(#[\SensitiveParameter] string $apiKey, string $model = self::MODEL, float $timeout = 60.0, ?string $httpReferer = null, ?string $appTitle = null, ?string $appCategories = null, RetryPolicy $retryPolicy = new RetryPolicy()): self
    {
        return new self(new JevClient(new AmpTransport($apiKey, timeout: $timeout, httpReferer: $httpReferer, appTitle: $appTitle, appCategories: $appCategories), $retryPolicy), $model);
    }

    /**
     * @param string|array<array-key, mixed>|\stdClass $state
     * @param array<string|int, Question> $questions
     */
    public function decide(string|array|\stdClass $state, array $questions, ?RequestOptions $options = null, ?Cancellation $cancellation = null): DecisionResponse
    {
        return $this->client->decide(new DecisionRequest($state, $questions, $this->model, $options ?? new RequestOptions()), $cancellation);
    }

    /** @param string|array<array-key, mixed>|\stdClass $state */
    public function choice(string|array|\stdClass $state, Choice $question, ?RequestOptions $options = null, ?Cancellation $cancellation = null): ChoiceAnswer
    {
        return $this->decide($state, ['answer' => $question], $options, $cancellation)->choice('answer');
    }

    /** @param string|array<array-key, mixed>|\stdClass $state */
    public function noul(string|array|\stdClass $state, Noul $question, ?RequestOptions $options = null, ?Cancellation $cancellation = null): NoulAnswer
    {
        return $this->decide($state, ['answer' => $question], $options, $cancellation)->noul('answer');
    }

    /** @param string|array<array-key, mixed>|\stdClass $state */
    public function score(string|array|\stdClass $state, Score $question, ?RequestOptions $options = null, ?Cancellation $cancellation = null): ScoreAnswer
    {
        return $this->decide($state, ['answer' => $question], $options, $cancellation)->score('answer');
    }

    /**
     * @template T of object
     * @param string|array<array-key, mixed>|\stdClass $state
     * @param class-string<T> $schema
     * @return T
     */
    public function evaluate(string|array|\stdClass $state, string $schema, ?RequestOptions $options = null, ?Cancellation $cancellation = null): object
    {
        return $this->evaluateWithResponse($state, $schema, $options, $cancellation)->value;
    }

    /**
     * @template T of object
     * @param string|array<array-key, mixed>|\stdClass $state
     * @param class-string<T> $schema
     * @return TypedResponse<T>
     */
    public function evaluateWithResponse(string|array|\stdClass $state, string $schema, ?RequestOptions $options = null, ?Cancellation $cancellation = null): TypedResponse
    {
        $definition = new DecisionSchema($schema);
        $response = $this->decide($state, $definition->questions, $options, $cancellation);
        return new TypedResponse($definition->hydrate($response), $response);
    }
}
