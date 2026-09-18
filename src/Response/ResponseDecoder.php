<?php

declare(strict_types=1);

namespace Shanginn\Jev\Response;

use Shanginn\Jev\Answer\{Answer, ChoiceAnswer, NoulAnswer, ScoreAnswer};
use Shanginn\Jev\Exception\InvalidResponseException;
use Shanginn\Jev\Question\{Choice, Noul, Question, Score};
use Shanginn\Jev\Request\DecisionRequest;

/** @internal Validates provider output before it can become a typed business decision. */
final class ResponseDecoder
{
    public function decode(string $json, DecisionRequest $request, ?string $requestId = null): DecisionResponse
    {
        try {
            $data = self::map(json_decode($json, flags: JSON_THROW_ON_ERROR), 'response');
        } catch (\JsonException) {
            throw new InvalidResponseException('The provider returned invalid JSON.');
        }
        $rawAnswers = self::map($data['answers'] ?? null, 'answers');
        if (array_diff_key($request->questions, $rawAnswers) !== [] || array_diff_key($rawAnswers, $request->questions) !== []) {
            throw new InvalidResponseException('Response answer ids do not match the requested question ids.');
        }
        $answers = [];
        foreach ($request->questions as $id => $question) {
            $answers[$id] = $this->answer(self::map($rawAnswers[$id], 'answer'), $question);
        }
        $usage = self::map($data['usage'] ?? null, 'usage');
        return new DecisionResponse(
            model: self::string($data['model'] ?? null, 'model'),
            answers: $answers,
            usage: new Usage(self::integer($usage['input_tokens'] ?? null), self::integer($usage['output_tokens'] ?? null), isset($usage['cost']) ? self::number($usage['cost'], 'cost', 0) : null),
            id: isset($data['id']) ? self::string($data['id'], 'id') : null,
            provider: isset($data['provider']) ? self::string($data['provider'], 'provider') : null,
            requestId: $requestId,
        );
    }

    /** @param array<array-key, mixed> $data */
    private function answer(array $data, Question $question): Answer
    {
        if (($data['type'] ?? null) !== $question->type()->value) {
            throw new InvalidResponseException('Answer type does not match its question.');
        }
        if ($question instanceof Noul) {
            return new NoulAnswer(self::number($data['noul'] ?? null, 'noul', 0, 1));
        }
        $confidence = isset($data['confidence']) ? self::number($data['confidence'], 'confidence', 0, 1) : null;
        if ($question instanceof Choice) {
            $choice = self::string($data['choice'] ?? null, 'choice');
            if (!array_key_exists($choice, $question->criteria)) {
                throw new InvalidResponseException('The chosen label is outside the requested answer space.');
            }
            return new ChoiceAnswer($choice, $this->probabilities($data['probabilities'] ?? null, array_keys($question->criteria)), $confidence);
        }
        if ($question instanceof Score) {
            $legend = null;
            if (isset($data['legend'])) {
                $legend = self::map($data['legend'], 'legend');
                if ($legend !== $question->criteria) {
                    // JSON object member order is not significant.
                    ksort($legend);
                    if ($legend !== $question->criteria) {
                        throw new InvalidResponseException('Score legend does not match the requested levels.');
                    }
                }
            }
            /** @var array<int, float>|null $probabilities */
            $probabilities = $this->probabilities($data['probabilities'] ?? null, array_keys($question->criteria));
            return new ScoreAnswer(self::number($data['score'] ?? null, 'score', 0, count($question->criteria) - 1), $probabilities, $confidence, $legend);
        }
        throw new InvalidResponseException('Unsupported question implementation.');
    }

    /**
     * @param list<string|int> $keys
     * @return array<string|int, float>|null
     */
    private function probabilities(mixed $value, array $keys): ?array
    {
        if ($value === null) {
            return null;
        }
        $values = self::map($value, 'probabilities');
        if (count($values) !== count($keys) || array_diff($keys, array_keys($values)) !== []) {
            throw new InvalidResponseException('Probability keys do not match the requested answer space.');
        }
        $result = [];
        foreach ($values as $key => $probability) {
            $result[$key] = self::number($probability, 'probability', 0, 1);
        }
        // Allow minor provider rounding, but reject a broken distribution.
        if (abs(array_sum($result) - 1.0) > 0.01) {
            throw new InvalidResponseException('Probabilities do not sum to one.');
        }
        return $result;
    }

    /** @return array<array-key, mixed> */
    private static function map(mixed $value, string $field): array
    {
        if (!$value instanceof \stdClass) {
            throw new InvalidResponseException('Expected a JSON object for ' . $field . '.');
        }
        return (array) $value;
    }

    private static function string(mixed $value, string $field): string
    {
        if (!is_string($value) || $value === '') {
            throw new InvalidResponseException('Expected a non-empty string for ' . $field . '.');
        }
        return $value;
    }

    private static function integer(mixed $value): int
    {
        if (!is_int($value) || $value < 0) {
            throw new InvalidResponseException('Token counts must be non-negative integers.');
        }
        return $value;
    }

    private static function number(mixed $value, string $field, float $min, float $max = PHP_FLOAT_MAX): float
    {
        if ((!is_float($value) && !is_int($value)) || !is_finite((float) $value) || $value < $min || $value > $max) {
            throw new InvalidResponseException('Invalid numeric value for ' . $field . '.');
        }
        return (float) $value;
    }
}
