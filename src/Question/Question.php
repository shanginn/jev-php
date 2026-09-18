<?php

declare(strict_types=1);

namespace Shanginn\Jev\Question;

interface Question extends \JsonSerializable
{
    public function type(): QuestionType;

    /** @return array<string, mixed> */
    public function jsonSerialize(): array;
}
