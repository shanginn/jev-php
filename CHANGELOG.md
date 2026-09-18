# Changelog

## 1.0.1 — 2026-09-18

- README, промпты, комментарии и вывод примеров переведены на русский.
- Добавлены отдельные примеры Noul и Score.
- Команда `composer test:examples` запускает все семь примеров и проверяет смысл ответов реального API.
- Сценарии `composer test:live` переведены на русский.

## 1.0.0 — 2026-09-18

- Initial PHP 8.5 SDK for OpenRouter's JEV Decisions API.
- Choice, Score and Noul questions, enum conversion, mixed batches and typed answer objects.
- Attribute-based DTO evaluation with generic return types and optional response metadata.
- Complete Decisions request options, provider routing, attribution, trace and usage/cost metadata.
- Amp connection reuse, concurrent calls, cancellation, bounded retries and credential-safe exceptions.
- Strict response validation, PHPUnit tests, PHPStan, executable examples and automated releases.
