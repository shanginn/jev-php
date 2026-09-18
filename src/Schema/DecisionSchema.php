<?php

declare(strict_types=1);

namespace Shanginn\Jev\Schema;

use Shanginn\Jev\Answer\{ChoiceAnswer, NoulAnswer, ScoreAnswer};
use Shanginn\Jev\Question\{Choice, Noul, Question, Score};
use Shanginn\Jev\Response\DecisionResponse;

/** @template T of object */
final readonly class DecisionSchema
{
    /** @var array<string, Question> */
    public array $questions;

    /** @param class-string<T> $class */
    public function __construct(private string $class)
    {
        $reflection = new \ReflectionClass($class);
        $constructor = $reflection->getConstructor();
        if (!$reflection->isInstantiable() || $constructor === null || $constructor->getNumberOfParameters() === 0) {
            throw new \InvalidArgumentException('A decision DTO needs a public constructor with annotated parameters.');
        }
        $questions = [];
        foreach ($constructor->getParameters() as $parameter) {
            $attributes = $parameter->getAttributes(Question::class, \ReflectionAttribute::IS_INSTANCEOF);
            if (count($attributes) !== 1 || $parameter->isVariadic() || $parameter->isPassedByReference()) {
                throw new \InvalidArgumentException('Each DTO constructor parameter needs exactly one Choice, Score or Noul attribute.');
            }
            $question = $attributes[0]->newInstance();
            $expected = match (true) {
                $question instanceof Choice => ChoiceAnswer::class,
                $question instanceof Noul => NoulAnswer::class,
                $question instanceof Score => ScoreAnswer::class,
                default => throw new \InvalidArgumentException('Unsupported question attribute.'),
            };
            $type = $parameter->getType();
            if (!$type instanceof \ReflectionNamedType || $type->getName() !== $expected || $type->allowsNull()) {
                throw new \InvalidArgumentException('The DTO parameter type must be ' . $expected . '.');
            }
            $questions[$parameter->getName()] = $question;
        }
        $this->questions = $questions;
    }

    /** @return T */
    public function hydrate(DecisionResponse $response): object
    {
        $arguments = [];
        foreach ($this->questions as $name => $question) {
            $arguments[$name] = $response->answer($name);
        }
        return new ($this->class)(...$arguments);
    }
}
