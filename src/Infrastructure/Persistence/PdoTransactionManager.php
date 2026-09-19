<?php

declare(strict_types=1);

namespace MediCareMini\Infrastructure\Persistence;

use MediCareMini\Domain\Repository\TransactionManagerInterface;

/**
 * Thin adapter over Database::transaction() implementing the Domain port -
 * see TransactionManagerInterface's docblock.
 */
final readonly class PdoTransactionManager implements TransactionManagerInterface
{
    public function __construct(private Database $db)
    {
    }

    public function transactional(callable $callback): mixed
    {
        return $this->db->transaction(static fn (): mixed => $callback());
    }
}
