<?php

declare(strict_types=1);

use Shanginn\Jev\Answer\{ChoiceAnswer, NoulAnswer, ScoreAnswer};
use Shanginn\Jev\Jev;
use Shanginn\Jev\Question\{Choice, Noul, Score};

/** @var Jev $jev */
$jev = require __DIR__ . '/bootstrap.php';

final readonly class TicketDecision
{
    public function __construct(
        #[Choice('Какой отдел должен обработать обращение?', ['billing' => 'Платежи, счета и возврат денег', 'technical' => 'Ошибки в работе программы'])]
        public ChoiceAnswer $department,
        #[Noul('Клиент просит вернуть деньги?')]
        public NoulAnswer $refund,
        #[Score('Насколько срочно нужно обработать обращение?', ['Обычное обращение', 'Требует быстрого ответа', 'Критический сбой'])]
        public ScoreAnswer $urgency,
    ) {}
}

$result = $jev->evaluateWithResponse('С меня дважды списали деньги за один заказ. Пожалуйста, верните повторный платёж.', TicketDecision::class);
echo 'Код отдела: ' . $result->value->department->choice . PHP_EOL;
echo 'Возврат денег: ' . ($result->value->refund->isYes(0.8) ? 'запрошен' : 'нужна проверка') . PHP_EOL;
echo 'Входных токенов: ' . $result->response->usage->inputTokens . PHP_EOL;

// Если нужен только объект результата, вызовите $jev->evaluate($state, TicketDecision::class).

return $result;
