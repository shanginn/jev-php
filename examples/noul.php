<?php

declare(strict_types=1);

use Shanginn\Jev\Jev;
use Shanginn\Jev\Question\Noul;

/** @var Jev $jev */
$jev = require __DIR__ . '/bootstrap.php';

$answer = $jev->noul(
    'С меня дважды списали деньги за один заказ. Пожалуйста, верните повторный платёж.',
    new Noul('Клиент просит вернуть деньги?'),
);

echo 'Вероятность запроса на возврат: ' . $answer->noul . PHP_EOL;
echo 'Решение при пороге 0,8: ' . ($answer->isYes(0.8) ? 'возврат запрошен' : 'нужна проверка') . PHP_EOL;

return $answer;
