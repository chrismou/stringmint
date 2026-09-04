<?php

declare(strict_types=1);

namespace Chrismou\StringMint\Counter\Pdo;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Shared prepare-and-execute step for dialects whose increment statement returns the new value.
 *
 * Converts a false return from prepare() or execute() (ERRMODE_SILENT) into a PDOException so
 * every dialect surfaces failures the same way and PdoCounterStore can wrap them uniformly.
 */
trait ExecutesIncrementStatement
{
    /**
     * Prepares and executes the increment statement, throwing PDOException on any failure.
     *
     * @param list<string|int> $params
     *
     * @throws PDOException when prepare() or execute() fails
     */
    private function executeIncrement(PDO $pdo, string $sql, array $params): PDOStatement
    {
        $stmt = $pdo->prepare($sql);

        if ($stmt === false) {
            $error = $pdo->errorInfo();
            throw new PDOException($error[2] ?? 'Prepare failed', (int) ($error[1] ?? 0));
        }

        $result = $stmt->execute($params);

        if ($result === false) {
            $error = $stmt->errorInfo();
            throw new PDOException($error[2] ?? 'Execute failed', (int) ($error[1] ?? 0));
        }

        return $stmt;
    }
}
