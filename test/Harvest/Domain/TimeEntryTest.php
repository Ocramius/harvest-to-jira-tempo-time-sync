<?php

declare(strict_types=1);

namespace TimeSyncTest\Harvest\Domain;

use CuyZ\Valinor\Mapper\Source\JsonSource;
use CuyZ\Valinor\MapperBuilder;
use PHPUnit\Framework\TestCase;
use Psl\Exception\InvariantViolationException;
use TimeSync\Harvest\Domain\SpentDate;
use TimeSync\Harvest\Domain\TimeEntry;

/** @covers \TimeSync\Harvest\Domain\TimeEntry */
final class TimeEntryTest extends TestCase
{
    public function testCanHydrateFromHarvestRecord(): void
    {
        $mapper = (new MapperBuilder())
            ->flexible()
            ->mapper();

        $record = $mapper->map(
            TimeEntry::class,
            new JsonSource(
                <<<'JSON'
{
  "id": 1827336432,
  "spent_date": "2022-07-06",
  "hours": 7.1,
  "hours_without_timer": 7.1,
  "rounded_hours": 7.25,
  "notes": "CR-56, debugging CR-55 with QA/OPS, BE Refinement meeting, MA Refinement meeting, meeting with Sven Kroll about CR-56 approach",
  "is_locked": true,
  "locked_reason": "Item Invoiced and Locked for this Time Period",
  "is_closed": false,
  "is_billed": true,
  "timer_started_at": null,
  "started_time": null,
  "ended_time": null,
  "is_running": false,
  "billable": true,
  "budgeted": true,
  "billable_rate": 110.0,
  "cost_rate": null,
  "created_at": "2022-07-06T17:05:48Z",
  "updated_at": "2022-07-30T16:29:24Z",
  "user": {
    "id": 500649,
    "name": "Marco Pivetta"
  },
  "client": {
    "id": 12206987,
    "name": " GmbH",
    "currency": "EUR"
  },
  "project": {
    "id": 33026870,
    "name": "",
    "code": "CFX"
  },
  "task": {
    "id": 2368428,
    "name": "Software development"
  },
  "user_assignment": {
    "id": 349192559,
    "is_project_manager": true,
    "is_active": true,
    "use_default_rates": true,
    "budget": null,
    "created_at": "2022-05-30T09:45:17Z",
    "updated_at": "2022-05-30T09:45:17Z",
    "hourly_rate": 90.0
  },
  "task_assignment": {
    "id": 354617418,
    "billable": true,
    "is_active": true,
    "created_at": "2022-05-30T09:45:17Z",
    "updated_at": "2022-05-30T09:45:17Z",
    "hourly_rate": 110.0,
    "budget": null
  },
  "invoice": {
    "id": 33314503,
    "number": "13000351"
  },
  "external_reference": null
}
JSON,
            ),
        );

        self::assertSame(7.1, $record->hours);
        self::assertSame('CR-56, debugging CR-55 with QA/OPS, BE Refinement meeting, MA Refinement meeting, meeting with Sven Kroll about CR-56 approach', $record->notes);
    }

    public function testWillRejectNegativeTime(): void
    {
        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Hours must be greater than zero');

        new TimeEntry('1', -1.0, 'irrelevant', new SpentDate('2022-08-03'));
    }

    public function testWillRejectZeroTime(): void
    {
        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Hours must be greater than zero');

        new TimeEntry('1', 0.0, 'irrelevant', new SpentDate('2022-08-03'));
    }
}
