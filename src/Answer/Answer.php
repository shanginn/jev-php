<?php

declare(strict_types=1);

namespace Shanginn\Jev\Answer;

use Shanginn\Jev\Question\QuestionType;

interface Answer
{
    public function type(): QuestionType;
}
