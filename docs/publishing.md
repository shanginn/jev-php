# Publishing and maintenance

Package: [`shanginn/jev-php`](https://packagist.org/packages/shanginn/jev-php)

Repository: [`shanginn/jev-php`](https://github.com/shanginn/jev-php), default branch `master`.

## Automatic releases

1. Update `VERSION` to a new stable semantic version and describe the change in `CHANGELOG.md`.
2. Run `composer check` and, for API changes, `composer test:live` locally.
3. Commit and push to `master`.
4. CI tests the locked dependencies and lowest supported versions on PHP 8.5. Only after both jobs pass does the release job create the matching `vX.Y.Z` tag and GitHub release.
5. Packagist's native GitHub webhook imports pushes and version tags automatically.

Do not add a `version` field to `composer.json`: Packagist derives versions from tags. Existing releases are never overwritten. A failed release can be retried in GitHub Actions; an existing tag can be used to complete release creation. Ordinary pushes without a VERSION change do not create another release.

The workflow needs only GitHub's repository-scoped `GITHUB_TOKEN` with `contents: write` in its release job. The Packagist GitHub connection owns the webhook. **No OpenRouter or Packagist token belongs in Git, composer metadata, CI configuration or workflow logs.** The OpenRouter key is not needed for publishing, and live tests are not run automatically.

## One-time Packagist setup

Sign in to Packagist using GitHub and submit `https://github.com/shanginn/jev-php`. Confirm the package shows automatic updates enabled. If a GitHub connection already exists, Packagist can configure the push webhook on submission. For recovery, use Packagist's account synchronization or manual update controls; see the [official hook instructions](https://packagist.org/about#how-to-update-packages).

Verify a release from a fresh directory using `composer require shanginn/jev-php:^1.0`; this exercises public Packagist metadata and the published archive, rather than a path repository.

## Local safety and validation

`.env`, `.env.*`, `auth.json`, `vendor`, caches and `.artifacts` are ignored. `.env.example` contains an empty placeholder. `tools/check-secrets.py` checks tracked files and prints only filenames on failure. Release archives omit tests, CI, development tools and the lockfile using `.gitattributes`; consumers resolve their own dependencies.

`composer test` never contacts OpenRouter. The opt-in `composer test:live` sends synthetic data only, prints no credentials or raw request headers, and makes three small requests. Its paid API use requires a key with available credits.
