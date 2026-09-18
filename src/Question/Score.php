<?php

declare(strict_types=1);

namespace Shanginn\Jev\Question;

#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY)]
final readonly class Score implements Question
{
    /** @param list<string> $criteria Ordered levels, indexed from zero. */
    public function __construct(public string $instructions, public array $criteria)
    {
        if (trim($instructions) === '' || !array_is_list($criteria) || count($criteria) < 2 || count($criteria) > 10) {
            throw new \InvalidArgumentException('A score needs instructions and an ordered list of 2–10 levels.');
        }
        if (!array_all($criteria, self::validLevel(...))) {
            throw new \InvalidArgumentException('Score levels must be non-empty strings.');
        }
    }

    private static function validLevel(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    public function type(): QuestionType
    {
        return QuestionType::Score;
    }

    public function jsonSerialize(): array
    {
        return ['type' => $this->type()->value, 'instructions' => $this->instructions, 'criteria' => $this->criteria];
    }
}
