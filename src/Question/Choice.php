<?php

declare(strict_types=1);

namespace Shanginn\Jev\Question;

#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY)]
final readonly class Choice implements Question
{
    /** @param array<string|int, string> $criteria Option keys are sent as JSON object keys, including numeric labels. */
    public function __construct(public string $instructions, public array $criteria)
    {
        if (trim($instructions) === '' || count($criteria) < 2) {
            throw new \InvalidArgumentException('A choice needs instructions and at least two options.');
        }
        foreach ($criteria as $label => $description) {
            if ((string) $label === '' || !is_string($description)) {
                throw new \InvalidArgumentException('Choice criteria must map non-empty labels to strings.');
            }
        }
    }

    /**
     * @param class-string<\BackedEnum> $enum
     * @param array<string|int, string> $descriptions
     */
    public static function fromEnum(string $instructions, string $enum, array $descriptions = []): self
    {
        $criteria = [];
        foreach ($enum::cases() as $case) {
            $criteria[(string) $case->value] = $descriptions[$case->value] ?? $case->name;
        }
        if (array_diff_key($descriptions, $criteria) !== []) {
            throw new \InvalidArgumentException('Descriptions contain options missing from the enum.');
        }
        return new self($instructions, $criteria);
    }

    public function type(): QuestionType
    {
        return QuestionType::Choice;
    }

    public function jsonSerialize(): array
    {
        return ['type' => $this->type()->value, 'instructions' => $this->instructions, 'criteria' => (object) $this->criteria];
    }
}
