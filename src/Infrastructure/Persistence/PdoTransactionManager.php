<?php

declare(strict_types=1);

namespace Aster\Infrastructure\Persistence;

use Aster\Domain\Repository\TransactionManagerInterface;

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
