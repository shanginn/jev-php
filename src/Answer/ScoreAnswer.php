<?php

declare(strict_types=1);

namespace Shanginn\Jev\Answer;

use Shanginn\Jev\Question\QuestionType;

final readonly class ScoreAnswer implements Answer
{
    /**
     * @param array<int, float>|null $probabilities
     * @param array<int, string>|null $legend
     */
    public function __construct(public float $score, public ?array $probabilities = null, public ?float $confidence = null, public ?array $legend = null) {}

    public function type(): QuestionType
    {
        return QuestionType::Score;
    }
}
