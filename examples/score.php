<?php

declare(strict_types=1);

use Shanginn\Jev\Jev;
use Shanginn\Jev\Question\Score;

/** @var Jev $jev */
$jev = require __DIR__ . '/bootstrap.php';

$answer = $jev->score(
    'Экспорт не работает в Safari, но работает в Chrome.',
    new Score('Насколько серьёзна ошибка?', [
        'Косметический дефект: функции работают',
        'Функция не работает, но есть обходной путь',
        'Блокирующая ошибка без обходного пути',
    ]),
);

echo 'Оценка серьёзности по шкале от 0 до 2: ' . $answer->score . PHP_EOL;
echo 'Уверенность модели: ' . ($answer->confidence ?? 'не передана') . PHP_EOL;

return $answer;
