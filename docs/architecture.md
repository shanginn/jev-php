# Architecture

The reference was the `master` branch of `shanginn/openai-sdk-php`: one simple facade over a client, small PSR-4 request/response classes, enums, custom exceptions, and attributes for user-defined result objects. JEV has a smaller contract, so it does not need a general serializer or JSON Schema generator.

- `Jev` handles convenient calls and typed DTO evaluation.
- `JevClient` serializes requests, applies retry policy and decodes responses.
- `Question` contains readonly question objects, reusable as constructor attributes.
- `Request` contains typed request, routing and tracing options.
- `Response` validates incoming JSON and holds response/usage metadata.
- `Answer` contains immutable choice, score and probability results.
- `Schema` validates a DTO constructor and invokes it with typed answers.
- `Http` provides the narrow transport seam and the default Amp implementation.
- `Exception` provides inspectable SDK errors without raw response bodies.

The runtime dependency is Amp's HTTP client. Amp is fiber-aware: a normal call waits for its own result, while `Amp\async` permits multiple independent calls to share the event loop and connection pool. Batching questions over the same state is a separate API feature and sends one request.

PHPStan generics preserve DTO and enum return types. Runtime validation protects callers from untyped PHP inputs and malformed remote values. Tests cover both sides of the HTTP boundary using an injectable transport and a fake Amp delegate; explicit live tests cover the real endpoint.

The package has no global singleton, environment mutation, framework dependency, automatic tool execution, or automatic dotenv loading. The example bootstrap is deliberately outside `src`.
