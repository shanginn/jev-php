# JEV PHP

[![CI](https://github.com/shanginn/jev-php/actions/workflows/ci.yml/badge.svg)](https://github.com/shanginn/jev-php/actions/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/v/shanginn/jev-php)](https://packagist.org/packages/shanginn/jev-php)
[![PHP](https://img.shields.io/packagist/php-v/shanginn/jev-php)](composer.json)
[![Лицензия](https://img.shields.io/packagist/l/shanginn/jev-php)](LICENSE)

Компактный SDK со строгой типизацией для **PHP 8.5**: работа с [TypeSafe JEV 1.13](https://openrouter.ai/typesafe/jev-1.13) через OpenRouter.

Передайте JEV контекст и вопрос с заданными вариантами ответа. В результате получите выбранный вариант, числовую оценку или вероятность ответа «да», которые можно сразу использовать в коде. Вопросы задаются обычными PHP-объектами. Их можно объединить в один запрос или описать весь результат классом `readonly` с атрибутами.

```sh
composer require shanginn/jev-php
```

## Первый запрос

```php
require 'vendor/autoload.php';

use Shanginn\Jev\Jev;
use Shanginn\Jev\Question\Noul;

$jev = Jev::create(getenv('OPENROUTER_API_KEY') ?: '');

$answer = $jev->noul(
    'С меня дважды списали деньги за один заказ. Пожалуйста, верните повторный платёж.',
    new Noul('Клиент просит вернуть деньги?'),
);

$answer->noul;       // Вероятность от 0 до 1.
$answer->isYes(0.8); // Проверка по вашему порогу. Деньги автоматически не возвращаются.
```

Если ключ не передан, ошибка возникнет до обращения к API. Сама библиотека не читает `.env`, не печатает запросы и не меняет глобальные настройки приложения. Клиент `$jev` можно переиспользовать в следующих примерах.

Готовый файл: [examples/noul.php](examples/noul.php).

## Выбор значения из enum

```php
use Shanginn\Jev\Question\Choice;

enum Department: string
{
    case Billing = 'billing';
    case Technical = 'technical';
    case Sales = 'sales';
}

$answer = $jev->choice(
    'С меня дважды списали деньги за один заказ. Пожалуйста, верните повторный платёж.',
    Choice::fromEnum('Какой отдел должен обработать обращение?', Department::class, [
        'billing' => 'Платежи, счета и возврат денег',
        'technical' => 'Ошибки в работе программы',
        'sales' => 'Тарифы, цены и новые покупки',
    ]),
);

$department = $answer->enum(Department::class); // Тип Department, а не произвольная строка.
$answer->choice;                              // Исходный код выбранного варианта.
$answer->confidence;                          // Уверенность модели: ?float.
$answer->probability(Department::Billing);      // Вероятность конкретного варианта: ?float.
```

Поддерживаются перечисления со строковыми и целочисленными значениями. Можно обойтись без enum: `new Choice('Какой отдел?', ['billing' => 'Бухгалтерия', 'technical' => 'Техническая поддержка'])`.

Коды вариантов и имена классов остаются обычными идентификаторами PHP; текст обращения, вопрос и описания вариантов написаны по-русски.

Готовый файл: [examples/choice.php](examples/choice.php).

## Оценка по заданной шкале

```php
use Shanginn\Jev\Question\Score;

$answer = $jev->score(
    'Экспорт не работает в Safari, но работает в Chrome.',
    new Score('Насколько серьёзна ошибка?', [
        'Косметический дефект: функции работают',
        'Функция не работает, но есть обходной путь',
        'Блокирующая ошибка без обходного пути',
    ]),
);

$answer->score;         // Число от 0 до 2, в том числе дробное.
$answer->legend;        // Описания уровней: ?array<int, string>.
$answer->probabilities; // Вероятности уровней: ?array<int, float>.
$answer->confidence;    // Уверенность модели: ?float.
```

`score` — среднее по уровням с учётом их вероятностей. Это не обязательно целый номер уровня. Задайте от 2 до 10 описаний по порядку: от минимального значения к максимальному.

Готовый файл: [examples/score.php](examples/score.php).

## Несколько вопросов в одном запросе

```php
use Shanginn\Jev\Question\{Choice, Noul, Score};

$response = $jev->decide(
    state: [
        'message' => 'С меня дважды списали деньги за один заказ. Пожалуйста, верните повторный платёж.',
        'customer_since' => 2021,
    ],
    questions: [
        'department' => new Choice('Какой отдел должен обработать обращение?', [
            'billing' => 'Платежи, счета и возврат денег',
            'technical' => 'Ошибки в работе программы',
        ]),
        'refund' => new Noul('Клиент просит вернуть деньги?'),
        'urgency' => new Score('Насколько срочно нужно обработать обращение?', [
            'Обычное обращение', 'Требует быстрого ответа', 'Критический сбой',
        ]),
    ],
);

$response->choice('department')->choice;
$response->noul('refund')->noul;
$response->score('urgency')->score;
$response->usage->inputTokens;
$response->usage->outputTokens;
$response->usage->cost; // Стоимость в долларах США, если её передал провайдер.
$response->id;
$response->provider;
```

`state` принимает строку, совместимый с JSON массив PHP или `stdClass`. Все вопросы используют один контекст и отправляются одним запросом. Идентификаторы ответов должны точно совпадать с идентификаторами вопросов. Методы `choice()`, `noul()` и `score()` проверяют тип запрошенного ответа.

Готовый файл: [examples/batch.php](examples/batch.php).

## Результат в виде вашего PHP-объекта

```php
use Shanginn\Jev\Answer\{ChoiceAnswer, NoulAnswer, ScoreAnswer};
use Shanginn\Jev\Question\{Choice, Noul, Score};

final readonly class TicketDecision
{
    public function __construct(
        #[Choice('Какой отдел должен обработать обращение?', [
            'billing' => 'Платежи, счета и возврат денег',
            'technical' => 'Ошибки в работе программы',
        ])]
        public ChoiceAnswer $department,

        #[Noul('Клиент просит вернуть деньги?')]
        public NoulAnswer $refund,

        #[Score('Насколько срочно нужно обработать обращение?', [
            'Обычное обращение', 'Требует быстрого ответа', 'Критический сбой',
        ])]
        public ScoreAnswer $urgency,
    ) {}
}

$decision = $jev->evaluate('Пожалуйста, верните повторно списанные деньги.', TicketDecision::class);
// PHPStan и IDE определяют тип TicketDecision.
$decision->department->choice;
$decision->refund->isYes(0.8);
$decision->urgency->score;

// Чтобы сохранить модель, расход токенов, идентификатор запроса и стоимость:
$result = $jev->evaluateWithResponse('Пожалуйста, верните повторно списанные деньги.', TicketDecision::class);
$result->value;    // TicketDecision.
$result->response; // DecisionResponse.
```

Каждому параметру конструктора нужен ровно один атрибут вопроса и соответствующий тип ответа без `null`. Имена параметров становятся идентификаторами вопросов. SDK проверяет схему до обращения к API и создаёт объект обычным вызовом вашего конструктора.

Готовый файл: [examples/typed-object.php](examples/typed-object.php).

## Параллельные запросы и отмена

Транспорт использует [Amp](https://amphp.org/http-client): соединения переиспользуются, а ввод-вывод не блокирует выполнение других задач. Независимые запросы можно выполнять параллельно:

```php
use Amp\TimeoutCancellation;
use Shanginn\Jev\Question\Noul;
use function Amp\async;
use function Amp\Future\await;

$deadline = new TimeoutCancellation(30);
$question = new Noul('В сообщении просят вернуть деньги?');
$answers = await([
    async(fn () => $jev->noul('Пожалуйста, верните деньги за заказ.', $question, cancellation: $deadline)),
    async(fn () => $jev->noul('Как изменить пароль?', $question, cancellation: $deadline)),
], $deadline);
```

Общая отмена действует и на сетевые запросы, и на ожидание перед повторными попытками. Если вопросы относятся к одному контексту, обычно удобнее собрать их в один вызов `decide()`. Потоковой выдачи токенов нет: JEV возвращает готовый JSON с решениями.

Готовый файл: [examples/concurrent.php](examples/concurrent.php).

## Настройки провайдера и наблюдаемость

```php
use Shanginn\Jev\Request\{MaxPrice, ProviderPreferences, RequestOptions, Trace};

$options = new RequestOptions(
    provider: new ProviderPreferences(
        order: ['typesafe'],
        maxPrice: new MaxPrice(prompt: '1', completion: '1'),
    ),
    sessionId: 'example-refund-triage',
    trace: new Trace(traceName: 'refund-triage', metadata: ['example' => true]),
);

$answer = $jev->noul(
    'Пожалуйста, верните повторно списанные деньги.',
    new Noul('Клиент просит вернуть деньги?',
        yes: 'Прямая просьба вернуть деньги',
        no: 'Просьбы вернуть деньги нет',
    ),
    $options,
);
```

`MaxPrice` задаёт ограничения цены, а не бюджет всей операции. Значения `prompt` и `completion` указаны в долларах США за миллион токенов. Доступность провайдеров зависит от выбранной модели и ограничений запроса.

Готовый файл: [examples/routing.php](examples/routing.php).

## Ошибки и повторные попытки

```php
use Shanginn\Jev\Exception\{ApiException, InvalidResponseException, TransportException};
use Shanginn\Jev\Http\RetryPolicy;

$jev = Jev::create(
    getenv('OPENROUTER_API_KEY') ?: '',
    timeout: 30,
    appTitle: 'Поддержка клиентов',
    httpReferer: 'https://example.com',
    retryPolicy: new RetryPolicy(maxRetries: 2),
);

try {
    $answer = $jev->noul('Пожалуйста, верните деньги.', new Noul('Клиент просит вернуть деньги?'));
} catch (ApiException $error) {
    // Сюда также попадают AuthenticationException и RateLimitException.
    $error->status;
    $error->requestId;
    $error->retryAfter;
} catch (InvalidResponseException $error) {
    // Некорректный JSON, пропущенные ответы, неверные типы или значения.
} catch (TransportException $error) {
    // Ошибка соединения или истечение времени ожидания.
}
```

По умолчанию выполняется не более двух повторных попыток при HTTP 408, 429, 500, 502, 503, 504, 524 и 529. Задержка растёт экспоненциально с добавлением случайного разброса. Заголовок `Retry-After` учитывается и как число секунд, и как HTTP-дата. Если заданная сервером задержка превышает `maxDelay`, SDK возвращает ошибку вызывающему коду, не повторяя запрос раньше времени.

Сетевые ошибки автоматически не повторяются: провайдер мог уже обработать запрос. Повторная обработка может оплачиваться отдельно. Чтобы отключить повторы, укажите `maxRetries: 0`.

При отмене возникает `Amp\CancelledException`. Некорректные локальные настройки вызывают `InvalidArgumentException`, а данные, которые невозможно преобразовать в JSON, — `JsonException`. SDK не включает необработанные ответы провайдера и исходные транспортные исключения в сообщения об ошибках. В приложении также следите за настройками журналирования HTTP и аргументов исключений, чтобы не записывать секреты.

## Возможности и ограничения

- Вопросы Choice, Score и Noul; описания ответов «да» и «нет»; преобразование в enum; смешанные запросы; типизированные объекты результата.
- Выбор модели и провайдера, идентификаторы пользователя и сессии, данные трассировки и сведения о приложении.
- Типизированные сведения о токенах, стоимости, провайдере и идентификаторах запроса.
- Проверка запросов и ответов, настройка повторных попыток, тайм-ауты, отмена и подмена транспорта в тестах.

По умолчанию используется фиксированная модель `typesafe/jev-1.13`. Чтобы следовать текущему алиасу, передайте `model: Jev::LATEST` (`~typesafe/jev-latest`). Можно указать и другой идентификатор модели Decisions.

SDK работает с **alpha Decisions API** OpenRouter: `https://openrouter.ai/api/alpha/decisions`. Этот API предназначен для принятия решений, а не для чата, генерации свободного текста, изображений или эмбеддингов. Контракт OpenRouter предусматривает строковые инструкции и описания вариантов. Более сложные структурированные инструкции прямого API TypeSafe сюда не входят.

Если провайдер не передал уверенность, распределение вероятностей или описания уровней оценки, соответствующие поля останутся `null`. У Noul есть вероятность ответа «да», но нет отдельного поля уверенности. Корректная структура ответа сама по себе не гарантирует правильность решения модели.

Дополнительная документация на английском: [справочник API](docs/api.md), [архитектура](docs/architecture.md), [публикация версий](docs/publishing.md).

## Запуск русскоязычных примеров и тестов

Из корня клонированного репозитория:

```sh
composer install
# Создайте .env из шаблона, только если файла ещё нет:
test -f .env || cp .env.example .env
# Укажите в .env свой OPENROUTER_API_KEY. Файл исключён из Git.

php examples/noul.php
php examples/choice.php
php examples/score.php
php examples/batch.php
php examples/typed-object.php
php examples/concurrent.php
php examples/routing.php

composer check
composer test:live
composer test:examples
```

| Файл | Что показывает |
| --- | --- |
| [noul.php](examples/noul.php) | Вероятность запроса на возврат и собственный порог решения |
| [choice.php](examples/choice.php) | Выбор отдела и преобразование результата в PHP enum |
| [score.php](examples/score.php) | Дробная оценка серьёзности ошибки по трём уровням |
| [batch.php](examples/batch.php) | Три разных вопроса по одному обращению за один запрос |
| [typed-object.php](examples/typed-object.php) | Атрибуты, собственный объект результата и сведения о токенах |
| [concurrent.php](examples/concurrent.php) | Два независимых запроса с общей отменой |
| [routing.php](examples/routing.php) | Настройки провайдера, ограничения цены и трассировка |

Промпты, описания вариантов, комментарии и пояснения в выводе примеров написаны по-русски. Машинные имена полей и коды вариантов сохраняются в исходном виде.

`composer test:examples` запускает **эти же семь файлов** и проверяет смысл ответов: обращение о повторном платеже должно попасть в бухгалтерию, просьба о возврате — получить высокую вероятность, вопрос о пароле — низкую, а ошибка с обходным путём — промежуточную оценку. Точные вероятности могут меняться. Проверка выполняет восемь небольших платных запросов с вымышленными обращениями.

`composer test:live` отдельно проверяет русскоязычный запрос к типизированному объекту и параллельные вызовы — ещё три запроса. `composer test` работает без сети и ключа. Команда `composer check` запускает обычные тесты, PHPStan, проверку форматирования и проверку `composer.json`. В CI платные запросы не выполняются.

Только примеры и явно запущенные проверки реального API читают локальный `.env`. Ключ не выводится в отчёт и не нужен для публикации пакета.

Если локальная версия PHP ниже 8.5, используйте подготовленное окружение:

```sh
docker build -t jev-php-dev -f tools/Dockerfile .
docker run --rm -v "$PWD":/app -w /app jev-php-dev composer install
docker run --rm -v "$PWD":/app -w /app jev-php-dev composer check
docker run --rm -v "$PWD":/app -w /app jev-php-dev composer test:live
docker run --rm -v "$PWD":/app -w /app jev-php-dev composer test:examples
```

Структура проекта и работа с типизированными объектами вдохновлены [shanginn/openai-sdk-php](https://github.com/shanginn/openai-sdk-php/tree/master). Это независимый SDK, а не официальный пакет TypeSafe или OpenRouter. Лицензия MIT.
