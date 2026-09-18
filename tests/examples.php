<?php

declare(strict_types=1);

use Shanginn\Jev\Answer\{ChoiceAnswer, NoulAnswer, ScoreAnswer};
use Shanginn\Jev\Exception\JevException;
use Shanginn\Jev\Response\{DecisionResponse, TypedResponse};

require dirname(__DIR__) . '/vendor/autoload.php';

/** Каждый пример получает свою область видимости; выполняется исходный файл из examples. */
function runExample(string $name): mixed
{
    return require dirname(__DIR__) . '/examples/' . $name . '.php';
}

$name = '';
try {
    foreach (['noul', 'choice', 'score', 'batch', 'typed-object', 'concurrent', 'routing'] as $name) {
        echo PHP_EOL . 'Пример: ' . $name . '.php' . PHP_EOL;
        $result = runExample($name);
        $valid = match ($name) {
            'noul', 'routing' => $result instanceof NoulAnswer && $result->isYes(0.8),
            'choice' => $result instanceof ChoiceAnswer && $result->choice === 'billing',
            'score' => $result instanceof ScoreAnswer && $result->score > 0.5 && $result->score < 1.5,
            'batch' => $result instanceof DecisionResponse
                && $result->choice('department')->choice === 'billing'
                && $result->noul('refund')->isYes(0.8)
                && $result->usage->inputTokens > 0,
            'typed-object' => $result instanceof TypedResponse
                && $result->value instanceof TicketDecision
                && $result->value->department->choice === 'billing'
                && $result->value->refund->isYes(0.8)
                && $result->response->usage->inputTokens > 0,
            'concurrent' => is_array($result)
                && ($result[0] ?? null) instanceof NoulAnswer
                && ($result[1] ?? null) instanceof NoulAnswer
                && $result[0]->noul >= 0.8
                && $result[1]->noul <= 0.2,
        };
        if (!$valid) {
            fwrite(STDERR, 'Ответ примера ' . $name . '.php не прошёл смысловую проверку.' . PHP_EOL);
            exit(1);
        }
        echo 'Проверено: ' . $name . '.php' . PHP_EOL;
    }
    echo PHP_EOL . 'Все семь русскоязычных примеров прошли проверку реального API.' . PHP_EOL;
} catch (Throwable $error) {
    // Не печатаем стек вызовов, заголовки запроса и необработанные ответы провайдера.
    fwrite(STDERR, 'Ошибка примера ' . $name . '.php: ' . ($error instanceof JevException ? $error->getMessage() : $error::class) . PHP_EOL);
    exit(1);
}
