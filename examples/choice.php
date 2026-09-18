<?php

declare(strict_types=1);

use Shanginn\Jev\Jev;
use Shanginn\Jev\Question\Choice;

/** @var Jev $jev */
$jev = require __DIR__ . '/bootstrap.php';

enum Department: string
{
    case Billing = 'billing';
    case Technical = 'technical';
    case Sales = 'sales';
}

$answer = $jev->choice('I was charged twice. Please refund the duplicate payment.', Choice::fromEnum('Which team should handle the ticket?', Department::class));
$department = $answer->enum(Department::class); // Department, inferred by PHPStan and IDEs.
echo $department->value . PHP_EOL;
echo 'Confidence: ' . ($answer->confidence ?? 'not supplied') . PHP_EOL;
