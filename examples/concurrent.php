<?php

declare(strict_types=1);

use function Amp\async;
use function Amp\Future\await;

use Amp\TimeoutCancellation;
use Shanginn\Jev\Jev;
use Shanginn\Jev\Question\Noul;

/** @var Jev $jev */
$jev = require __DIR__ . '/bootstrap.php';

$deadline = new TimeoutCancellation(30);
$question = new Noul('Is this a refund request?');
$futures = [];
foreach (['Please refund my payment.', 'How do I change my password?'] as $index => $state) {
    $futures[$index] = async(fn() => $jev->noul($state, $question, cancellation: $deadline));
}
foreach (await($futures, $deadline) as $index => $answer) {
    echo $index . ': ' . $answer->noul . PHP_EOL;
}
