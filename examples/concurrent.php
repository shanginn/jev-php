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
$question = new Noul('В сообщении просят вернуть деньги?');
$futures = [];
foreach (['Пожалуйста, верните деньги за заказ.', 'Как изменить пароль?'] as $index => $state) {
    $futures[$index] = async(fn() => $jev->noul($state, $question, cancellation: $deadline));
}
$answers = await($futures, $deadline);
foreach ($answers as $index => $answer) {
    echo 'Сообщение ' . ($index + 1) . ': вероятность запроса на возврат — ' . $answer->noul . PHP_EOL;
}

return $answers;
