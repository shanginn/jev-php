<?php

declare(strict_types=1);

namespace Shanginn\Jev\Response;

use Shanginn\Jev\Answer\{Answer, ChoiceAnswer, NoulAnswer, ScoreAnswer};

final readonly class DecisionResponse
{
    /** @param array<string|int, Answer> $answers */
    public function __construct(public string $model, public array $answers, public Usage $usage, public ?string $id = null, public ?string $provider = null, public ?string $requestId = null) {}

    public function answer(string|int $id): Answer
    {
        return $this->answers[$id] ?? throw new \OutOfBoundsException('No answer for question ' . $id . '.');
    }

    public function choice(string|int $id): ChoiceAnswer
    {
        $answer = $this->answer($id);
        return $answer instanceof ChoiceAnswer ? $answer : throw new \LogicException('The answer is not a choice.');
    }

    public function noul(string|int $id): NoulAnswer
    {
        $answer = $this->answer($id);
        return $answer instanceof NoulAnswer ? $answer : throw new \LogicException('The answer is not a noul.');
    }

    public function score(string|int $id): ScoreAnswer
    {
        $answer = $this->answer($id);
        return $answer instanceof ScoreAnswer ? $answer : throw new \LogicException('The answer is not a score.');
    }
}
