<?php

declare(strict_types=1);

use function Amp\async;
use function Amp\Future\await;

use Amp\TimeoutCancellation;
use Shanginn\Jev\Answer\{ChoiceAnswer, NoulAnswer, ScoreAnswer};
use Shanginn\Jev\Exception\JevException;
use Shanginn\Jev\Jev;
use Shanginn\Jev\Question\{Choice, Noul, Score};
use Shanginn\Jev\Request\{RequestOptions, Trace};

final readonly class LiveTriage
{
    public function __construct(
        #[Choice('Какой отдел поддержки должен обработать обращение?', ['billing' => 'Платежи, счета и возврат денег', 'technical' => 'Ошибки в работе программы and crashes'])]
        public ChoiceAnswer $department,
        #[Noul('Клиент прямо просит вернуть деньги?')]
        public NoulAnswer $refund,
        #[Score('Насколько срочно нужно обработать обращение?', ['Обычное обращение', 'Срочное обращение', 'Критический сбой'])]
        public ScoreAnswer $urgency,
    ) {}
}

function ensure(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    /** @var Jev $jev */
    $jev = require dirname(__DIR__) . '/examples/bootstrap.php';
    $deadline = new TimeoutCancellation(60);
    $result = $jev->evaluateWithResponse(
        ['ticket' => 'С меня дважды списали деньги по одному счёту. Пожалуйста, верните повторный платёж.'],
        LiveTriage::class,
        new RequestOptions(sessionId: 'jev-php-live-smoke', trace: new Trace(traceName: 'sdk-live-test')),
        $deadline,
    );
    ensure($result->value->department->choice === 'billing', 'Выбран неожиданный отдел.');
    ensure($result->value->refund->isYes(0.5), 'Запрос на возврат денег не распознан.');
    ensure($result->response->usage->inputTokens > 0, 'Не получены сведения об использовании токенов.');
    echo json_encode(['test' => 'typed_batch', 'model' => $result->response->model, 'provider' => $result->response->provider, 'id' => $result->response->id, 'department' => $result->value->department->choice, 'refund_probability' => $result->value->refund->noul, 'urgency' => $result->value->urgency->score, 'usage' => $result->response->usage], JSON_THROW_ON_ERROR) . PHP_EOL;

    $question = new Noul('В сообщении просят вернуть деньги?');
    $answers = await([
        async(fn() => $jev->noul('Пожалуйста, верните деньги за заказ.', $question, cancellation: $deadline)),
        async(fn() => $jev->noul('Как изменить пароль?', $question, cancellation: $deadline)),
    ], $deadline);
    ensure($answers[0]->noul > $answers[1]->noul, 'Результаты положительного и отрицательного примеров не различаются ожидаемым образом.');
    echo json_encode(['test' => 'concurrent_noul', 'positive' => $answers[0]->noul, 'negative' => $answers[1]->noul], JSON_THROW_ON_ERROR) . PHP_EOL;
    echo "Проверки реального API пройдены.\n";
} catch (Throwable $error) {
    // Не печатаем стек вызовов и необработанные ответы провайдера, чтобы не раскрыть секреты.
    fwrite(STDERR, 'Ошибка проверки реального API: ' . ($error instanceof JevException ? $error->getMessage() : $error::class) . PHP_EOL);
    exit(1);
}
