# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `UniqueStringGeneratorInterface` interface with `generate()` and `generateWithAutoLengthIncrement()`.
- `PermutationGenerator`: guaranteed-unique strings via a keyed Feistel permutation and a PDO-backed counter.
- `RandomGenerator`: probabilistic unique strings via CSPRNG with existence-check retries.
- `AlphabetInterface` interface (`size()`, `characterAt()`, `isReserved()`) so both generators accept any character set.
- `AbstractAlphabet` base class: one symbol per UTF-8 character (multibyte characters and emoji included);
  validates at least two distinct characters; override `isReserved()` to exclude outputs.
- `AbstractUrlSafeAlphabet` base class: restricts characters to the RFC 3986 unreserved set and reserves any
  output ending in `.` (including `.` and `..`); extend it for custom URL-safe subsets or extra `isReserved()` rules.
- URL-safe presets `UrlSafe` (default, full 66-char unreserved set), `Alphanumeric` (62), `LowercaseAlphanumeric` (36)
  and `Base64Url` (64), each a fixed-set class extending `AbstractUrlSafeAlphabet`.
- `Generic` alphabet: any UTF-8 character set passed to the constructor, no URL-safety check, reserves nothing.
- `LengthPolicy` value object: configurable default length and optional maximum.
- `Keyspace` with per-length capacity maths and a shared `2^62` ceiling for both strategies.
- `FeistelPermutation`: keyed, tweaked Feistel network (FFX-style, 4 rounds by default).
- `InMemoryCounterStore` for tests, single-process use and CLI scripts.
- `PdoCounterStore` with atomic per-length counters and support for MySQL, PostgreSQL, SQL Server, and SQLite.
- `CounterTableInstaller`: idempotent table creation and removal; exposes raw SQL for custom migration tools.
- Dialect implementations for MySQL, PostgreSQL, SQL Server, and SQLite (also the fallback for unknown drivers).
- `ExistenceCheckerInterface` interface with `CallableExistenceChecker`, `NeverExistsChecker`, `InMemoryExistenceChecker`
  and `PdoColumnExistenceChecker`. `PdoColumnExistenceChecker` fails closed, throwing `ExistenceCheckException` on
  database errors instead of reporting the candidate as unused.
- `StringMintExceptionInterface` marker interface and full exception hierarchy.
- `PermutationGenerator::create()` one-liner named constructor for quick PDO-backed setup, with an optional `alphabet` argument.
