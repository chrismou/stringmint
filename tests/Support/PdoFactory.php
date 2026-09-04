<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Tests\Support;

use PDO;
use PHPUnit\Framework\SkippedWithMessageException;

/**
 * Creates PDO connections for tests.
 *
 * sqlite() always returns a fresh in-memory SQLite connection. fromEnv() creates a connection
 * from an environment-variable DSN, returning null when the variable is absent or empty.
 */
final class PdoFactory
{
    /**
     * Returns a fresh in-memory SQLite PDO connection.
     *
     * Skips the calling test with a clear message when pdo_sqlite is not available.
     */
    public static function sqlite(): PDO
    {
        if (!in_array('pdo_sqlite', get_loaded_extensions(), true)) {
            throw new SkippedWithMessageException(
                'pdo_sqlite is not installed; skipping SQLite integration test.',
            );
        }

        return new PDO('sqlite::memory:');
    }

    /**
     * Returns a PDO from the given environment-variable DSN, or null when the variable is unset or empty.
     *
     * Expects:
     *   STRINGMINT_MYSQL_DSN / STRINGMINT_MYSQL_USER / STRINGMINT_MYSQL_PASSWORD for MySQL
     *   STRINGMINT_PGSQL_DSN / STRINGMINT_PGSQL_USER / STRINGMINT_PGSQL_PASSWORD for PostgreSQL
     */
    public static function fromEnv(string $envVariable): ?PDO
    {
        $dsn = (string) getenv($envVariable);
        if ($dsn === '') {
            return null;
        }

        $userVar = str_replace('_DSN', '_USER', $envVariable);
        $passVar = str_replace('_DSN', '_PASSWORD', $envVariable);

        $user = (string) getenv($userVar) ?: null;
        $pass = (string) getenv($passVar) ?: null;

        return new PDO($dsn, $user, $pass);
    }
}
