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

$answer = $jev->choice('С меня дважды списали деньги за один заказ. Пожалуйста, верните повторный платёж.', Choice::fromEnum('Какой отдел должен обработать обращение?', Department::class, [
    'billing' => 'Платежи, счета и возврат денег',
    'technical' => 'Ошибки в работе программы',
    'sales' => 'Тарифы, цены и новые покупки',
]));
$department = $answer->enum(Department::class); // PHPStan и IDE определяют тип Department.
echo 'Отдел: ' . match ($department) {
    Department::Billing => 'бухгалтерия',
    Department::Technical => 'техническая поддержка',
    Department::Sales => 'продажи',
} . PHP_EOL;
echo 'Уверенность модели: ' . ($answer->confidence ?? 'не передана') . PHP_EOL;

return $answer;
