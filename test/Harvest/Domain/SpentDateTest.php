<?php

declare(strict_types=1);

namespace CrowdfoxTimeSyncTest\Harvest\Domain;

use CrowdfoxTimeSync\Harvest\Domain\SpentDate;
use PHPUnit\Framework\TestCase;
use Psl\Exception\InvariantViolationException;

/** @covers \CrowdfoxTimeSync\Harvest\Domain\SpentDate */
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
}
