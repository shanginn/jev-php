<?php

declare(strict_types=1);

namespace Shanginn\Jev\Request;

use Shanginn\Jev\Jev;
use Shanginn\Jev\Question\Question;

final readonly class DecisionRequest implements \JsonSerializable
{
    /**
     * @param string|array<array-key, mixed>|\stdClass $state
     * @param array<string|int, Question> $questions
     */
    public function __construct(public string|array|\stdClass $state, public array $questions, public string $model = Jev::MODEL, public RequestOptions $options = new RequestOptions())
    {
        if (trim($model) === '' || $questions === []) {
            throw new \InvalidArgumentException('A model and at least one question are required.');
        }
        foreach ($questions as $id => $question) {
            if ((string) $id === '' || !$question instanceof Question) {
                throw new \InvalidArgumentException('Questions must map non-empty ids to Question objects.');
            }
        }
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return ['model' => $this->model, 'state' => $this->state, 'questions' => (object) $this->questions] + $this->options->jsonSerialize();
    }
}
