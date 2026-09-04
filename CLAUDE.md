# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`chrismou/stringmint` is a zero-dependency PHP 8.2+ library that mints short, URL-safe, unique strings.
Its headline strategy walks a keyed Feistel permutation of the whole keyspace driven by a persisted
counter, so strings are unique by construction with no existence check or retry. A second,
probabilistic strategy (random + existence check) is included for setups without a counter table.

The package has no tagged release yet. Until 1.0.0 is tagged, backwards compatibility is not a concern:
delete old APIs rather than deprecating them.

## Commands

```bash
composer install

composer test              # phpunit (Unit + Integration suites)
composer lint              # pint --test (PSR-12 + ordered/imported symbols)
composer lint:fix          # pint
composer analyse           # phpstan level 8 over src/ and tests/, no baseline, no ignores
composer test:coverage     # phpunit --coverage-text

# Single test file / method / suite
vendor/bin/phpunit tests/Unit/Generator/PermutationGeneratorTest.php
vendor/bin/phpunit --filter testIsReservedReturnsTrueForAnyTrailingDot
vendor/bin/phpunit --testsuite Unit
```

CI (`.github/workflows/ci.yml`) runs lint, analyse and test on PHP 8.2 to 8.5. All three must be
clean before a change is considered done.

Integration tests run against in-memory SQLite by default. Setting `STRINGMINT_MYSQL_DSN` /
`STRINGMINT_PGSQL_DSN` (plus `_USER` / `_PASSWORD`) adds MySQL / PostgreSQL entries to the
`PdoTestCase::pdoDataset()` data provider; see `phpunit.xml.dist` for the variable names. Server-backed
connections drop the `stringmint_counters` and `links` tables before each test. CI runs both servers on
one PHP version. Any SQL written in tests must go through `PdoTestCase::quoteIdentifier()` (or the
`createLinksTable()` / `insertLink()` helpers): MySQL treats double-quoted names as string literals.

## Architecture

Everything hangs off two generators that implement `UniqueStringGeneratorInterface`
(`generate()` and `generateWithAutoLengthIncrement()`):

- **`PermutationGenerator`** (default, recommended). Per call: `CounterStoreInterface::next($length)`
  atomically reserves an index, `KeyspacePermutationInterface::permute($index, $capacity, $tweak)`
  maps it to a random-looking index in the same range, and the result is base-encoded into
  `$length` symbols of the alphabet. The **tweak is the counter's `name()`**, so two counters
  sharing a secret produce unrelated sequences. Retries only happen when `isReserved()` or an
  optional `ExistenceCheckerInterface` rejects a candidate; each retry burns a counter index.
- **`RandomGenerator`**. CSPRNG candidates checked against an `ExistenceCheckerInterface`; exhaustion
  is inferred after `maximumAttemptsPerLength` consecutive collisions.

Supporting pieces, each in its own namespace under `src/`:

- **`Keyspace`** owns the capacity maths (`|alphabet|^length`) and the shared `2^62` ceiling that
  both strategies respect. It refuses to run on 32-bit PHP.
- **`LengthPolicy`** is the default length plus optional maximum; it resolves per-call overrides and
  the escalation ceiling for auto-increment.
- **`Alphabet/`**: `AlphabetInterface` (`size()`, `characterAt()`, `isReserved()`) with
  `AbstractAlphabet` doing UTF-8 splitting and validation. `AbstractUrlSafeAlphabet` adds the
  RFC 3986 unreserved-set check and reserves any output ending in `.`. The four presets
  (`UrlSafe`, `Alphanumeric`, `LowercaseAlphanumeric`, `Base64Url`) are final classes with a
  `CHARACTERS` constant; `Generic` takes any string and reserves nothing. Custom reservation rules
  extend `AbstractUrlSafeAlphabet`, not a preset.
- **`Permutation/`**: `FeistelPermutation` is an HMAC-based, tweakable Feistel network with
  cycle-walking for non-power-of-two sizes.
- **`Counter/`**: `PdoCounterStore` and `InMemoryCounterStore`. PDO support is split by
  `Counter/Pdo/*Dialect` classes chosen by `DialectResolver` from the driver name; unknown drivers
  fall back to `SqliteDialect`'s compare-and-swap SQL. `CounterTableInstaller` creates the counter
  table and is a separate step from generation.
- **`Existence/`**: `ExistenceCheckerInterface` is read-only. Nothing in the library writes back to
  a checker; the application's own insert is what makes a string "exist". `InMemoryExistenceChecker`
  has `remember()` but the generators never call it. `PdoColumnExistenceChecker` fails closed: a
  database error throws `ExistenceCheckException`, never "does not exist".
- **`Exception/`**: all exceptions implement the `StringMintExceptionInterface` marker.

Interfaces carry an `Interface` suffix. Table and column names are validated against an identifier
regex and inlined into SQL, since they cannot be bound as parameters. Every identifier, including the
fixed counter column names, is quoted through the dialect's `quoteIdentifier()`; never hard-code
double quotes in SQL.

### Do-not-change invariants

Secret, alphabet (character set or order) and counter name all feed the bijection. Changing any of
them after strings have been issued causes collisions with existing data. Changing `isReserved()`
or `LengthPolicy` is safe. Keep this in mind when refactoring anything that touches encoding or the
permutation tweak.

## Tests

- `tests/Unit` and `tests/Integration` mirror `src/`. Shared helpers live in `tests/Support`
  (`PdoTestCase`, `PdoFactory`, `ScriptedRandomEngine` for deterministic `RandomGenerator` tests,
  `IdentityPermutation`, `ReadsAlphabetSymbols`).
- Generator tests that need a small alphabet use `new Generic('ab')` rather than a preset.
- PHPStan runs over `tests/` too at level 8, so test code needs full type hints and array shapes.

## Conventions

- `declare(strict_types=1)`, `final` (often `final readonly`) classes, constructor promotion,
  docblocks on every method, array shape PHPDoc for arrays.
- Pint config: PSR-12, alphabetical imports, global classes imported (`use PDO;`), no unused imports.
- Use a plain hyphen `-` in all text; never Unicode dashes.
- `plans/` holds dated design docs written by the planning pipeline and is kept for audit. New plans
  follow `YYYYMMDD-slug.md`.
- README.md and CHANGELOG.md (Keep a Changelog, single `[Unreleased]` section) document the public
  API and must be updated alongside API changes.
