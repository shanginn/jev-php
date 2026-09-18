<?php

declare(strict_types=1);

use Shanginn\Jev\Jev;
use Shanginn\Jev\Question\Noul;
use Shanginn\Jev\Request\{MaxPrice, ProviderPreferences, RequestOptions, Trace};

/** @var Jev $jev */
$jev = require __DIR__ . '/bootstrap.php';

$answer = $jev->noul('Please refund the duplicate charge.', new Noul('Is a refund requested?', yes: 'An explicit request for money back', no: 'No refund requested'), new RequestOptions(
    provider: new ProviderPreferences(order: ['typesafe'], maxPrice: new MaxPrice(prompt: '1', completion: '1')),
    sessionId: 'example-refund-triage',
    trace: new Trace(traceName: 'refund-triage', metadata: ['example' => true]),
));
echo 'Probability: ' . $answer->noul . PHP_EOL;
