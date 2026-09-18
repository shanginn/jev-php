<?php

declare(strict_types=1);

use Shanginn\Jev\Jev;
use Shanginn\Jev\Question\{Choice, Noul, Score};

/** @var Jev $jev */
$jev = require __DIR__ . '/bootstrap.php';

$response = $jev->decide(
    state: ['message' => 'С меня дважды списали деньги за один заказ. Пожалуйста, верните повторный платёж.', 'customer_since' => 2021],
    questions: [
        'department' => new Choice('Какой отдел должен обработать обращение?', ['billing' => 'Платежи, счета и возврат денег', 'technical' => 'Ошибки в работе программы']),
        'refund' => new Noul('Клиент просит вернуть деньги?'),
        'urgency' => new Score('Насколько срочно нужно обработать обращение?', ['Обычное обращение', 'Требует быстрого ответа', 'Критический сбой']),
    ],
);
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
echo 'Вероятность запроса на возврат: ' . $response->noul('refund')->noul . PHP_EOL;

return $response;
