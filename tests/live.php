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
        #[Choice('Which support department handles this request?', ['billing' => 'Payments, invoices and refunds', 'technical' => 'Software bugs and crashes'])]
        public ChoiceAnswer $department,
        #[Noul('Does the customer explicitly request a refund?')]
        public NoulAnswer $refund,
        #[Score('How urgent is this support ticket?', ['Routine', 'Urgent', 'Critical outage'])]
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
        ['ticket' => 'I was charged twice for the same invoice. Please refund the duplicate payment.'],
        LiveTriage::class,
        new RequestOptions(sessionId: 'jev-php-live-smoke', trace: new Trace(traceName: 'sdk-live-test')),
        $deadline,
    );
    ensure($result->value->department->choice === 'billing', 'Unexpected department.');
    ensure($result->value->refund->isYes(0.5), 'Refund request not detected.');
    ensure($result->response->usage->inputTokens > 0, 'Missing usage.');
    echo json_encode(['test' => 'typed_batch', 'model' => $result->response->model, 'provider' => $result->response->provider, 'id' => $result->response->id, 'department' => $result->value->department->choice, 'refund_probability' => $result->value->refund->noul, 'urgency' => $result->value->urgency->score, 'usage' => $result->response->usage], JSON_THROW_ON_ERROR) . PHP_EOL;

    $question = new Noul('Is the message asking for a refund?');
    $answers = await([
        async(fn() => $jev->noul('Please refund my payment.', $question, cancellation: $deadline)),
        async(fn() => $jev->noul('How do I change my password?', $question, cancellation: $deadline)),
    ], $deadline);
    ensure($answers[0]->noul > $answers[1]->noul, 'Concurrent results not separated.');
    echo json_encode(['test' => 'concurrent_noul', 'positive' => $answers[0]->noul, 'negative' => $answers[1]->noul], JSON_THROW_ON_ERROR) . PHP_EOL;
    echo "Live tests passed.\n";
} catch (Throwable $error) {
    // No stack traces or raw provider payloads: credentials never enter the report.
    fwrite(STDERR, 'Live test failed: ' . ($error instanceof JevException ? $error->getMessage() : $error::class) . PHP_EOL);
    exit(1);
}
