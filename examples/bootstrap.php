<?php

declare(strict_types=1);

use Shanginn\Jev\Jev;

require dirname(__DIR__) . '/vendor/autoload.php';

// Только примеры читают .env. В приложении передавайте настройки самостоятельно.
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
    throw new RuntimeException('Укажите OPENROUTER_API_KEY в окружении или локальном файле .env, исключённом из Git.');
}

return Jev::create($key);
