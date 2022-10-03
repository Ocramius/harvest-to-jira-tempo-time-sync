<?php

declare(strict_types=1);

namespace TimeSync\Harvest\Domain;

use Psl;
use Psl\Exception\InvariantViolationException;

/** @psalm-immutable */
final class TimeEntry
{
    /**
     * @param non-empty-string $id
     * @param non-empty-string $notes
     *
     * @throws InvariantViolationException on negative input hours.
     */
    public function __construct(
        public readonly string $id,
        public readonly float $hours,
        public readonly string $notes,
        public readonly SpentDate $spent_date,
    ) {
        Psl\invariant($this->hours > 0, 'Hours must be greater than zero');
    }
}
