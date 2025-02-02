<?php

declare(strict_types=1);

namespace TimeSync\Harvest\Domain;

use DateTimeImmutable;
use Psl;

/** @psalm-immutable */
final readonly class SpentDate
{
    /** @var non-empty-string */
    private string $spentDate;

    /** @param non-empty-string $spentDate */
    public function __construct(string $spentDate)
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $spentDate);

        Psl\invariant($date !== false, 'Could not parse date ' . $spentDate);

        $formatted = $date->format('Y-m-d');

        Psl\invariant($formatted === $spentDate, 'Input date ' . $spentDate . ' is malformed');

        $this->spentDate = $formatted;
    }

    /** @return non-empty-string */
    public function toString(): string
    {
        return $this->spentDate;
    }

    public function equals(self $other): bool
    {
        return $this->spentDate === $other->spentDate;
    }
}
