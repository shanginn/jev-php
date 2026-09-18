<?php

declare(strict_types=1);

use Shanginn\Jev\Jev;

require dirname(__DIR__) . '/vendor/autoload.php';

// Only the examples read .env. Library consumers supply their own configuration.
$key = getenv('OPENROUTER_API_KEY') ?: '';
$env = dirname(__DIR__) . '/.env';
if ($key === '' && is_file($env)) {
    foreach (file($env, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if (preg_match('/^\s*OPENROUTER_API_KEY\s*=\s*(.*?)\s*$/', $line, $matches)) {
            $key = trim($matches[1], "\"'");
        }
    }
}
if ($key === '') {
    throw new RuntimeException('Set OPENROUTER_API_KEY in the environment or ignored .env file.');
}

return Jev::create($key);
