<?php

declare(strict_types=1);

namespace Shanginn\Jev\Question;

#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY)]
final readonly class Noul implements Question
{
    public function __construct(public string $instructions, public ?string $yes = null, public ?string $no = null)
    {
        if (trim($instructions) === '' || (($yes === null) !== ($no === null))) {
            throw new \InvalidArgumentException('A noul needs instructions; supply both yes/no descriptions or neither.');
        }
    }

    public function type(): QuestionType
    {
        return QuestionType::Noul;
    }

    public function jsonSerialize(): array
    {
        $result = ['type' => $this->type()->value, 'instructions' => $this->instructions];
        if ($this->yes !== null) {
            $result['criteria'] = ['true' => $this->yes, 'false' => $this->no];
        }
        return $result;
    }
}
