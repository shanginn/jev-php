<?php

declare(strict_types=1);

use Shanginn\Jev\Answer\{ChoiceAnswer, NoulAnswer, ScoreAnswer};
use Shanginn\Jev\Jev;
use Shanginn\Jev\Question\{Choice, Noul, Score};

/** @var Jev $jev */
$jev = require __DIR__ . '/bootstrap.php';

final readonly class TicketDecision
{
    public function __construct(
        #[Choice('Which department?', ['billing' => 'Payments and refunds', 'technical' => 'Software bugs'])]
        public ChoiceAnswer $department,
        #[Noul('Does the customer request a refund?')]
        public NoulAnswer $refund,
        #[Score('How urgent is the ticket?', ['Routine', 'Time-sensitive', 'Critical outage'])]
        public ScoreAnswer $urgency,
    ) {}
}

$result = $jev->evaluateWithResponse('I was charged twice. Please refund the duplicate.', TicketDecision::class);
echo $result->value->department->choice . PHP_EOL;
echo 'Refund: ' . ($result->value->refund->isYes(0.8) ? 'requested' : 'review') . PHP_EOL;
echo 'Input tokens: ' . $result->response->usage->inputTokens . PHP_EOL;

// Use $jev->evaluate($state, TicketDecision::class) when you only need the DTO.
