<?php

declare(strict_types=1);

namespace CrowdfoxTimeSync\Harvest\Domain;

use DateTimeImmutable;
use Psl;

/** @psalm-immutable */
final class SpentDate
{
    private readonly DateTimeImmutable $spentDate;

    /** @param non-empty-string $spentDate */
    public function __construct(string $spentDate)
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $spentDate);

        Psl\invariant($date !== false, 'Could not parse date ' . $spentDate);
        Psl\invariant($date->format('Y-m-d') === $spentDate, 'Input date ' . $spentDate . ' is malformed');

        $this->spentDate = $date;
    }

    /** @return non-empty-string */
    public function toString(): string
    {
        return $this->spentDate->format('Y-m-d');
    }

    public function equals(self $other): bool
    {
        return $this->toString() === $other->toString();
    }
}
