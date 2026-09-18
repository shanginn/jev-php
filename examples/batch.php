<?php

declare(strict_types=1);

use Shanginn\Jev\Jev;
use Shanginn\Jev\Question\{Choice, Noul, Score};

/** @var Jev $jev */
$jev = require __DIR__ . '/bootstrap.php';

$response = $jev->decide(
    state: ['message' => 'I was charged twice. Please refund the duplicate payment.', 'customer_since' => 2021],
    questions: [
        'department' => new Choice('Which department?', ['billing' => 'Payments and refunds', 'technical' => 'Software bugs']),
        'refund' => new Noul('Is the customer asking for a refund?'),
        'urgency' => new Score('How urgent is this?', ['Routine', 'Time-sensitive', 'Critical outage']),
    ],
);
echo json_encode($response, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
echo 'Refund probability: ' . $response->noul('refund')->noul . PHP_EOL;
