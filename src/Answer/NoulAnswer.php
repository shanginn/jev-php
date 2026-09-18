<?php

declare(strict_types=1);

namespace Shanginn\Jev\Answer;

use Shanginn\Jev\Question\QuestionType;

final readonly class NoulAnswer implements Answer
{
    public function __construct(public float $noul) {}

    public function type(): QuestionType
    {
        return QuestionType::Noul;
    }

    public function isYes(float $threshold = 0.5): bool
    {
        if (!is_finite($threshold) || $threshold < 0 || $threshold > 1) {
            throw new \InvalidArgumentException('The threshold must be between zero and one.');
        }
        return $this->noul >= $threshold;
    }
}
