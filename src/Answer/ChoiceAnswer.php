<?php

declare(strict_types=1);

namespace Shanginn\Jev\Answer;

use Shanginn\Jev\Question\QuestionType;

final readonly class ChoiceAnswer implements Answer
{
    /** @param array<string|int, float>|null $probabilities Null means the provider omitted this field. */
    public function __construct(public string $choice, public ?array $probabilities = null, public ?float $confidence = null) {}

    public function type(): QuestionType
    {
        return QuestionType::Choice;
    }

    /**
     * @template T of \BackedEnum
     * @param class-string<T> $enum
     * @return T
     */
    public function enum(string $enum): \BackedEnum
    {
        foreach ($enum::cases() as $case) {
            if ((string) $case->value === $this->choice) {
                return $case;
            }
        }
        throw new \UnexpectedValueException('The chosen label is not a case of ' . $enum . '.');
    }

    public function probability(string|int|\BackedEnum $option): ?float
    {
        $key = $option instanceof \BackedEnum ? $option->value : $option;
        return $this->probabilities[$key] ?? null;
    }
}
