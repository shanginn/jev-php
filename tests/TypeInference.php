<?php

declare(strict_types=1);

namespace Shanginn\Jev\Tests;

use function PHPStan\Testing\assertType;

use Shanginn\Jev\Jev;
use Shanginn\Jev\Question\Choice;
use Shanginn\Jev\Tests\Unit\Department;
use Shanginn\Jev\Tests\Unit\Triage;

/** Analysed by PHPStan; deliberately never executed as an API test. */
function verifyPublicReturnTypes(Jev $jev): void
{
    $decision = $jev->evaluate('Example', Triage::class);
    assertType(Triage::class, $decision);
    assertType('Shanginn\\Jev\\Answer\\NoulAnswer', $decision->refund);
    $response = $jev->evaluateWithResponse('Example', Triage::class);
    assertType('Shanginn\\Jev\\Response\\TypedResponse<Shanginn\\Jev\\Tests\\Unit\\Triage>', $response);
    $choice = $jev->choice('Example', Choice::fromEnum('Department?', Department::class));
    assertType(Department::class, $choice->enum(Department::class));
}
