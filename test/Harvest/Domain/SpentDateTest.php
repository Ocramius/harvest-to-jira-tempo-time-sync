<?php

declare(strict_types=1);

namespace TimeSyncTest\Harvest\Domain;

use PHPUnit\Framework\TestCase;
use Psl\Exception\InvariantViolationException;
use TimeSync\Harvest\Domain\SpentDate;

/** @covers \TimeSync\Harvest\Domain\SpentDate */
final class SpentDateTest extends TestCase
{
    public function testWillStoreDate(): void
    {
        self::assertSame(
            '2022-08-05',
            (new SpentDate('2022-08-05'))->toString(),
        );
    }

    public function testWillRejectInvalidDate(): void
    {
        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Could not parse date foo');

        new SpentDate('foo');
    }

    public function testWillRejectMalformedDate(): void
    {
        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Input date 2022-02-29 is malformed');

        new SpentDate('2022-02-29');
    }

    public function testWillRejectDateWithTimeFraction(): void
    {
        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Could not parse date 2022-02-28 01:02:03');

        new SpentDate('2022-02-28 01:02:03');
    }

    public function testEquality(): void
    {
        self::assertTrue(
            (new SpentDate('2022-02-28'))
                ->equals(new SpentDate('2022-02-28')),
        );
        self::assertFalse(
            (new SpentDate('2022-02-27'))
                ->equals(new SpentDate('2022-02-28')),
        );
    }
}
