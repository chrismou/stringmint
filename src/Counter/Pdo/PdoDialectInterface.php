<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Counter\Pdo;

use PDO;

/**
 * Driver-specific SQL generation for the counter table.
 *
 * Implementations provide identifier quoting, table DDL, and an atomic increment-and-fetch
 * statement appropriate for the target database. Narrow interface by design: consumers on
 * exotic drivers inject their own implementation via PdoCounterStore's $dialect parameter.
 */
interface PdoDialectInterface
{
    /**
     * Sentinel returned by incrementAndFetch() to signal that PdoCounterStore should use its
     * portable compare-and-swap loop instead of a driver-specific atomic update.
     */
    public const USE_COMPARE_AND_SWAP = '__cas__';

    /**
     * Quotes a table or column identifier, handling an optional schema prefix (schema.table).
     */
    public function quoteIdentifier(string $identifier): string;

    /**
     * Returns the CREATE TABLE statement for the counter table (idempotent where possible).
     */
    public function createTableSql(string $quotedTable): string;

    /**
     * Returns the DROP TABLE statement for the counter table (idempotent where possible).
     */
    public function dropTableSql(string $quotedTable): string;

    /**
     * Atomically increments the counter for ($counterName, $length) and returns the new stored value.
     *
     * Returns null when no row for ($counterName, $length) exists (triggers the seed path in PdoCounterStore).
     * Returns PdoDialectInterface::USE_COMPARE_AND_SWAP when the dialect delegates to PdoCounterStore's CAS loop.
     *
     * @return int|null|PdoDialectInterface::USE_COMPARE_AND_SWAP
     */
    public function incrementAndFetch(PDO $pdo, string $quotedTable, string $counterName, int $length): int|null|string;
}
