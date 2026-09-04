<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Counter\Pdo;

use PDO;

/**
 * Resolves a PdoDialectInterface from a PDO connection's driver name.
 *
 * Unknown drivers (oci, odbc, firebird, made-up, etc.) fall back to SqliteDialect, which uses
 * double-quoted identifiers and portable SELECT / UPDATE ... WHERE SQL. A consumer on an exotic
 * driver that does not suit the fallback injects their own PdoDialectInterface via the $dialect constructor
 * parameter on PdoCounterStore, CounterTableInstaller or PdoColumnExistenceChecker.
 */
final class DialectResolver
{
    /**
     * Returns the appropriate PdoDialectInterface for the given PDO connection.
     *
     * Unknown driver names map to SqliteDialect (the portable fallback).
     */
    public static function resolve(PDO $pdo): PdoDialectInterface
    {
        $driverName = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        return match ($driverName) {
            'mysql' => new MySqlDialect(),
            'pgsql' => new PostgreSqlDialect(),
            'sqlsrv', 'dblib' => new SqlServerDialect(),
            default => new SqliteDialect(),
        };
    }
}
