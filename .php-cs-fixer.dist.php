<?php

declare(strict_types=1);

return (new PhpCsFixer\Config())
    ->setRules(['@PER-CS3x0' => true, 'ordered_imports' => true, 'no_unused_imports' => true])
    ->setFinder(PhpCsFixer\Finder::create()->in([__DIR__.'/src', __DIR__.'/tests', __DIR__.'/examples']));
