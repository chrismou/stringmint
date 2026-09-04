# StringMint

Collision-free, non-sequential, URL-safe short strings for PHP. Unique by construction, with no
existence checks or retries required, and zero dependencies.

## What it does

Out of the box, StringMint generates short, URL-safe strings that are guaranteed unique without ever needing
to query your DB table to check that a string is not already in use. Instead of picking random characters and
checking whether they are taken, it walks a keyed permutation of the entire keyspace driven by a single counter,
so every string is distinct, looks random, reveals nothing about how many came before it, and never needs a
retry. When a length is exhausted it moves up to the next one.

It works with any PDO driver, has no runtime dependencies, and also ships a random-with-existence-check
generator for cases where a counter table is not wanted.

## But why?

I needed a random string generator for a project and wanted to utilize the absolute maximum number of string combinations 
possible, but was concerned that simply generating random strings would mean more and more collisions as the strings were 
used. By the time you got to 7/8 of the way through your possible combinations, you're looking at an average 87.5% chance of
generating an existing strings, meaning that simply generating a string will take longer and longer and eventually become untenable
(credit to Gemini for doing the maths for me).

So I wanted a package that could do this as efficiently as possible, but also increment string length when the pool ran dry. This 
would allow me to start generating something as short as 2 character strings (4356 combinations) and know it would automatically 
bump to 3 characters (287496 combinations) and beyond when required and not require me to be sat waiting to change a config value. 

## Generators included

| | `PermutationGenerator` (recommended, default) | `RandomGenerator` |
|---|---|---|
| Uniqueness | Guaranteed by construction via a keyed Feistel permutation and a per-length counter | Probabilistic: CSPRNG + existence check + retry |
| State | Needs a persisted counter per `(name, length)` - PDO table or in-memory | None |
| Retry behaviour | Zero retries | Retries grow as the pool fills |
| Exhaustion detection | Exact: counter reaches capacity | Inferred: `maximumAttemptsPerLength` consecutive collisions |
| Predictability | Unpredictable without the secret | Unpredictable (CSPRNG) |
| Keyspace limit | `2^62` (10 chars with the default 66-char alphabet) | Same ceiling for consistency |

Both strategies implement `UniqueStringGeneratorInterface`, so consumers depend on the interface and swap
strategies in the DI container.

## Requirements

- PHP 8.2+ (64-bit only - the `2^62` ceiling uses native 64-bit integers)
- `ext-pdo` for `PdoCounterStore`, `PdoColumnExistenceChecker` and `CounterTableInstaller`

## Installation

```bash
composer require chrismou/stringmint
```

## Quick start: permutation strategy with DB counter

### Step 1 - install the counter table (once)

```php
use Chrismou\StringMint\Counter\CounterTableInstaller;

(new CounterTableInstaller($pdo))->install();

// Or output the SQL to paste into your own migration tool:
// $sql = (new CounterTableInstaller($pdo))->installSql();
```

### Step 2 - generate strings

**One-liner (common case):**

```php
use Chrismou\StringMint\Generator\PermutationGenerator;

$generator = PermutationGenerator::create(
    $pdo,
    $_ENV['STRINGMINT_SECRET'],
    length: 6,
);
$slug = $generator->generate();

// With length escalation and a named counter:
$generator = PermutationGenerator::create(
    $pdo,
    $_ENV['STRINGMINT_SECRET'],
    length: 4,
    maximumLength: 8,
    counterName: 'links',
);
$slug = $generator->generateWithAutoLengthIncrement();
```

`create()` uses the default 66-character RFC 3986 unreserved alphabet to generate URL safe strings. The counter table must be installed separately (see Step 1 above).

**Explicit constructor (DI containers and custom configuration):**

```php
use Chrismou\StringMint\Counter\PdoCounterStore;
use Chrismou\StringMint\Existence\PdoColumnExistenceChecker;
use Chrismou\StringMint\Generator\PermutationGenerator;
use Chrismou\StringMint\LengthPolicy;
use Chrismou\StringMint\Permutation\FeistelPermutation;

$generator = new PermutationGenerator(
    new LengthPolicy(defaultLength: 6),
    new PdoCounterStore($pdo, counterName: 'links'),
    new FeistelPermutation($_ENV['STRINGMINT_SECRET']),
    // Optional: custom alphabet, existence checker, maximumAttempts
);
```

## Random strategy, no database

Short running processes, ie, that only need uniqueness for the current session could use something like the below, generating random strings and utilizing the included
InMemoryExistenceChecker

```php
use Chrismou\StringMint\Existence\InMemoryExistenceChecker;
use Chrismou\StringMint\Generator\RandomGenerator;
use Chrismou\StringMint\LengthPolicy;

$checker = new InMemoryExistenceChecker(); // or PdoColumnExistenceChecker

$generator = new RandomGenerator(
    new LengthPolicy(6),
    $checker,
);

$slug = $generator->generate();
$checker->remember($slug);
```

### Counter name

The counter name option sets a namespace for the counter. So two counters
named `links` and `invites` set with the same secret and length will produce completely unrelated string
sequences.

## Choosing a length

**Central configuration:**

```php
new LengthPolicy(defaultLength: 6)              // fixed default, no ceiling
new LengthPolicy(defaultLength: 4, maximumLength: 8)  // default 4, ceiling 8
```

**Per-call override:**

```php
$generator->generate(2);                        // always length 2, exception if no unique strings left
$generator->generateWithAutoLengthIncrement(4); // start at 4, increase length as required
```

### `generate()` vs `generateWithAutoLengthIncrement()`

- `generate()` - the common case. Always returns the exact requested length. Throws
  `KeyspaceExhaustedException` when that length is full. Use this when you set a long enough
  length that exhaustion is not a realistic concern.
- `generateWithAutoLengthIncrement()` - opt-in escalation. Moves to `length + 1` when a length
  is exhausted, up to `maximumLength` (or no limit if `null`). Use this for apps that start at a short length and grow
  over time.

**Important:** `maximumLength` also acts as a guard against per-call overrides. A `generate(9)`
against `LengthPolicy(4, 8)` throws `InvalidLengthException` immediately, preventing
out-of-bounds strings for a `VARCHAR(8)` column. Lengths count characters, so with a multibyte
custom alphabet size the column in characters (or bytes times the longest character).

When `maximumLength` is not configured, both methods cap at `Keyspace::largestSupportedLength()`
(10 chars for the default 66-char alphabet).

### Capacity table (included 66-char url-safe alphabet)

| Length | Capacity | Largest supported? |
|---|---|---|
| 2 | 4,356 | - |
| 3 | 287,496 | - |
| 4 | 18,974,736 | - |
| 5 | 1,252,332,576 | - |
| 6 | 82,653,950,016 | - |
| 7 | 5.46 trillion | - |
| 8 | 360 trillion | - |
| 9 | 23.8 quadrillion | - |
| 10 | 1.57 quintillion | yes (shared `2^62` ceiling) |

Lengths 11+ exceed `2^62` with this alphabet and are rejected by `InvalidLengthException`.
The ceiling applies to both strategies.

The counter table stores one row per `(counter name, length)`. Any length works immediately
without further schema changes.

**Start at 2 characters?** `generate()` with the default alphabet exhausts at 4,356 strings.
Prefer `generateWithAutoLengthIncrement()` with a configured `maximumLength` for short starting
lengths so the app never fails unexpectedly when a length runs out.

## Dependency injection examples

**Laravel service provider:**

```php
use Chrismou\StringMint\Alphabet\UrlSafe;
use Chrismou\StringMint\Counter\PdoCounterStore;
use Chrismou\StringMint\Existence\PdoColumnExistenceChecker;
use Chrismou\StringMint\Generator\PermutationGenerator;
use Chrismou\StringMint\LengthPolicy;
use Chrismou\StringMint\Permutation\FeistelPermutation;
use Chrismou\StringMint\UniqueStringGeneratorInterface;

$this->app->singleton(UniqueStringGeneratorInterface::class, function ($app) {
    return new PermutationGenerator(
        new LengthPolicy(6),
        new PdoCounterStore($app['db']->getPdo(), 'links'),
        new FeistelPermutation(config('app.stringmint_secret')),
        new UrlSafe(),
        new PdoColumnExistenceChecker($app['db']->getPdo(), 'links', 'slug'),
    );
});
```

**Symfony `services.yaml`:**

```yaml
Chrismou\StringMint\UniqueStringGeneratorInterface:
    class: Chrismou\StringMint\Generator\PermutationGenerator
    arguments:
        - '@Chrismou\StringMint\LengthPolicy'
        - '@Chrismou\StringMint\Counter\PdoCounterStore'
        - '@Chrismou\StringMint\Permutation\FeistelPermutation'
```

## Alphabets

All alphabets live in the `Chrismou\StringMint\Alphabet` namespace.

| Alphabet | Characters | Size | Notes |
|---|---|---|---|
| `new UrlSafe()` | A-Z a-z 0-9 `-._~` | 66 | **Default.** Full RFC 3986 unreserved set |
| `new Alphanumeric()` | A-Z a-z 0-9 | 62 | No special characters |
| `new LowercaseAlphanumeric()` | a-z 0-9 | 36 | Safe for case-insensitive DB collations |
| `new Base64Url()` | A-Z a-z 0-9 `-_` | 64 | Base64url character set |
| `new Generic($chars)` | any UTF-8 characters | varies | No URL-safety check, reserves nothing |
| extend `AbstractUrlSafeAlphabet` | your subset of `-._~` A-Z a-z 0-9 | varies | Keeps the URL-safety check and trailing-dot guard |
| extend `AbstractAlphabet` | any UTF-8 characters | varies | See "Custom alphabets" below |

**Trailing-dot guard:** Every URL-safe alphabet (`UrlSafe`, `Alphanumeric`, `LowercaseAlphanumeric`, `Base64Url`,
and anything extending `AbstractUrlSafeAlphabet`) reserves any output ending in `.`. That covers `.` and `..`,
which every URL parser normalises away so they would silently collide with other paths, and strings like `abc.`,
where chat clients and email linkifiers drop the trailing dot. `Generic` and
custom alphabets built on `AbstractAlphabet` reserve nothing unless they override `isReserved()`.

**Case-insensitive collations:** MySQL `utf8mb4_general_ci` and SQL Server defaults treat `abc`
and `ABC` as equal. A unique index will reject strings the generator considers distinct. Use
`new LowercaseAlphanumeric()` or a binary collation.

### Custom alphabets

For a one-off character set, use `Generic`:

```php
use Chrismou\StringMint\Alphabet\Generic;

$generator = PermutationGenerator::create($pdo, $_ENV['STRINGMINT_SECRET'], length: 8, alphabet: new Generic('0123456789abcdef'));
```

For a named, reusable alphabet, extend `AbstractAlphabet` and hand it your characters:

```php
use Chrismou\StringMint\Alphabet\AbstractAlphabet;

final class Hex extends AbstractAlphabet
{
    public function __construct()
    {
        parent::__construct('0123456789abcdef');
    }
}

$generator = PermutationGenerator::create($pdo, $_ENV['STRINGMINT_SECRET'], length: 8, alphabet: new Hex());
```

Three rules:

1. At least two characters.
2. No duplicates.
3. To exclude specific outputs, override `isReserved()`:

```php
public function isReserved(string $candidate): bool
{
    return str_starts_with($candidate, '-');
}
```

The first two are checked in the constructor (`InvalidAlphabetException`). Reserved candidates are skipped and
burn a counter index or attempt, exactly like an existence collision, so reserve sparingly.

Each UTF-8 character is one symbol, so accented letters, Greek or emoji work as-is:
`parent::__construct('🍎🍌🍒🍇')` is a four-symbol alphabet. `length` counts characters, not bytes: a length-2
string from that alphabet is 8 bytes. Size your column accordingly.

To keep the URL-safe character set and only exclude a few outputs, extend `AbstractUrlSafeAlphabet` instead -
you keep the RFC 3986 validation and the trailing-dot guard:

```php
use Chrismou\StringMint\Alphabet\AbstractUrlSafeAlphabet;

final class Slug extends AbstractUrlSafeAlphabet
{
    public function __construct()
    {
        parent::__construct(self::RFC3986_UNRESERVED);
    }

    public function isReserved(string $candidate): bool
    {
        return parent::isReserved($candidate) || str_starts_with($candidate, '-');
    }
}
```

Pass any subset of `RFC3986_UNRESERVED` to `parent::__construct()` to narrow the character set as well.

`Generic` and custom alphabets built on `AbstractAlphabet` are not checked for URL safety - that is
`AbstractUrlSafeAlphabet`'s job.

You can also implement `Chrismou\StringMint\Alphabet\AlphabetInterface` directly, but that bypasses all validation:
a duplicate character would silently shrink the keyspace and break uniqueness. Extend `AbstractAlphabet` unless
you have a specific reason not to.

## Uniqueness checks

`ExistenceCheckerInterface` implementations:

- `NeverExistsChecker` - null object; default for `PermutationGenerator` when no external check
  is needed (the permutation guarantees uniqueness by construction)
- `InMemoryExistenceChecker` - set-backed; for tests, single-process use, and custom blocklists
- `CallableExistenceChecker(fn(string $c): bool)` - closure-backed; quick wiring and blocklists
- `PdoColumnExistenceChecker($pdo, $table, $column)` - `SELECT 1` query; production use. Fails closed:
  a database error throws `ExistenceCheckException` rather than reporting the candidate as unused

**Blocklist pattern** - reject banned words or reserved slugs:

```php
$checker = new CallableExistenceChecker(
    fn (string $c) => in_array($c, ['admin', 'root', 'api'], true)
        || $db->exists('links', ['slug' => $c]),
);
```

## Exceptions

| Exception | Extends | When |
|---|---|---|
| `StringMintExceptionInterface` | interface | Marker; catch this to handle any package exception |
| `InvalidAlphabetException` | `\InvalidArgumentException` | Invalid character set (invalid UTF-8, fewer than two or duplicate characters; non-unreserved characters for URL-safe alphabets) |
| `InvalidLengthException` | `\InvalidArgumentException` | Bad length: policy construction, override, keyspace ceiling |
| `InvalidTableNameException` | `\InvalidArgumentException` | Table/column name fails identifier validation |
| `UnableToGenerateUniqueStringException` | `\RuntimeException` | Base: generation failed |
| `KeyspaceExhaustedException` | above | Exact or inferred exhaustion; exposes `length()` |
| `CounterStoreException` | `\RuntimeException` | PDO database error in `PdoCounterStore` |
| `ExistenceCheckException` | `\RuntimeException` | PDO database error in `PdoColumnExistenceChecker` |

`KeyspaceExhaustedException::inferredForLength()` is thrown by `RandomGenerator` after
`maximumAttemptsPerLength` consecutive collisions. This may fire on a merely crowded pool rather
than a truly exhausted one. Raise `maximumAttemptsPerLength`, use
`generateWithAutoLengthIncrement()`, or switch to `PermutationGenerator` to avoid false positives.

## Concurrency and transactions

**Counter updates inside a caller's transaction** hold a row lock on the `(name, length)` row
until commit (MySQL/PostgreSQL/SQL Server), serialising concurrent generators for the entire
transaction. Use a dedicated PDO connection for the counter under high concurrency.

**Check-then-insert race:** The `ExistenceCheckerInterface` is not a lock. For `RandomGenerator` and for
`PermutationGenerator` with an existence checker, the check-then-insert window still exists.
Always maintain a unique constraint on the column and be ready to retry on a constraint violation.
`PermutationGenerator` is race-free with respect to its own output - the counter reservation is
atomic.

## Error handling

`PdoCounterStore` and `PdoColumnExistenceChecker` work with any PDO error mode (ERRMODE_SILENT,
ERRMODE_WARNING, or ERRMODE_EXCEPTION) and wrap all database failures in `CounterStoreException` and
`ExistenceCheckException` respectively, leaving the original `PDOException` as the previous exception
if available. Neither ever treats a database error as "not found": a broken existence check would let
the generator issue strings that may already be in use.

## Do-not-change list

Do not change these values after strings have been issued:

- **Secret** - changes the entire bijection; new strings collide with old ones at rate `issued/capacity`
- **Alphabet** - changes the bijection and the encoding (character set and order; changing only `isReserved()` is safe)
- **Counter name** - changes both the permutation tweak AND points at a fresh counter row starting at 0

**Safe to change:** `LengthPolicy` (counters are per length), `maximumLength`, any override
passed to `generate()`.

Renaming the counter is doubly unsafe: it changes the permutation AND resets the counter.
Collisions with existing strings are possible either way; the old row is left behind.

## Database support

| Driver | Dialect | Increment method | Identifier quoting |
|---|---|---|---|
| `mysql` | `MySqlDialect` | `LAST_INSERT_ID()` trick | backticks |
| `pgsql` | `PostgreSqlDialect` | `RETURNING` | double quotes |
| `sqlsrv`, `dblib` | `SqlServerDialect` | `OUTPUT INSERTED` | square brackets |
| `sqlite` + anything else | `SqliteDialect` (fallback) | Compare-and-swap loop | double quotes |

Unknown drivers (oci, odbc, firebird, ...) fall back to `SqliteDialect`'s portable
compare-and-swap SQL. Consumers on exotic drivers that do not suit this fallback inject their
own `PdoDialectInterface` implementation through the `$dialect` constructor parameter.

The `CREATE TABLE IF NOT EXISTS` and `DROP TABLE IF EXISTS` in the fallback are not universal
(Oracle rejects both). The `isInstalled()` guard in `CounterTableInstaller::install()` covers
re-installs; a first install on such a driver may need `installSql()` pasted into a custom tool.

## Testing your own code

```php
use Chrismou\StringMint\Alphabet\UrlSafe;
use Chrismou\StringMint\Counter\InMemoryCounterStore;
use Chrismou\StringMint\Existence\InMemoryExistenceChecker;
use Chrismou\StringMint\Generator\RandomGenerator;

// Seed a counter at a specific length to test exhaustion boundaries.
$store = new InMemoryCounterStore([3 => 64]); // length 3 starts at index 64 (capacity = 64 for 4-char alphabet)

// Pre-populate the existence checker for collision tests.
$checker = new InMemoryExistenceChecker(['abc', 'def']);

// Seed a Randomizer for deterministic RandomGenerator tests.
$randomizer = new \Random\Randomizer(new \Random\Engine\Mt19937(42));
$generator = new RandomGenerator($policy, $checker, new UrlSafe(), 10, $randomizer);
```

## Development

```bash
composer install

composer test         # run the PHPUnit test suite
composer analyse      # run PHPStan level 8 static analysis
composer lint         # check PSR-12 formatting (pint --test)
composer lint:fix     # auto-fix formatting

# Optional MySQL/PostgreSQL integration tests (the tests drop and recreate the
# stringmint_counters and links tables in that database, so use a throwaway one):
STRINGMINT_MYSQL_DSN="mysql:host=127.0.0.1;dbname=test" STRINGMINT_MYSQL_USER=root composer test
STRINGMINT_PGSQL_DSN="pgsql:host=127.0.0.1;dbname=test" STRINGMINT_PGSQL_USER=postgres composer test
```

The `phpunit.xml.dist` configuration can be overridden by creating an untracked local `phpunit.xml` file at the project root.

### Debugging

The `FeistelPermutation` secret is marked as a sensitive parameter and is redacted in `var_dump()`
output and framework debug panels. However, `print_r()` and `var_export()` will show the actual
secret - do not use these functions on generator instances in production environments.

## Future work

No plans for a batch `generateMany()` API, Laravel/Symfony bridge packages, or a profanity filter
in the initial release. Custom blocklists are supported via `CallableExistenceChecker`.

## Licence

MIT. See [LICENSE](LICENSE).
