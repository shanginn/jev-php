<?php

declare(strict_types=1);

use Shanginn\Jev\Jev;
use Shanginn\Jev\Question\Noul;
use Shanginn\Jev\Request\{MaxPrice, ProviderPreferences, RequestOptions, Trace};

/** @var Jev $jev */
$jev = require __DIR__ . '/bootstrap.php';

$answer = $jev->noul('Пожалуйста, верните повторно списанные деньги.', new Noul('Клиент просит вернуть деньги?', yes: 'Прямая просьба вернуть деньги', no: 'Просьбы вернуть деньги нет'), new RequestOptions(
    provider: new ProviderPreferences(order: ['typesafe'], maxPrice: new MaxPrice(prompt: '1', completion: '1')),
    sessionId: 'example-refund-triage',
    trace: new Trace(traceName: 'refund-triage', metadata: ['example' => true]),
));
echo 'Вероятность: ' . $answer->noul . PHP_EOL;

return $answer;
