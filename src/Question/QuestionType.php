<?php

declare(strict_types=1);

namespace Shanginn\Jev\Question;

enum QuestionType: string
{
    case Choice = 'choice';
    case Score = 'score';
    case Noul = 'noul';
}
