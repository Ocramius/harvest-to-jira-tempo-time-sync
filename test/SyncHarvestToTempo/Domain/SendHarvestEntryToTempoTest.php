<?php

declare(strict_types=1);

namespace CrowdfoxTimeSyncTest\SyncHarvestToTempo\Domain;

use CrowdfoxTimeSync\Harvest\Domain\SpentDate;
use CrowdfoxTimeSync\Harvest\Domain\TimeEntry;
use CrowdfoxTimeSync\SyncHarvestToTempo\Domain\SendHarvestEntryToTempo;
use CrowdfoxTimeSync\Tempo\Domain\GetWorkLogEntries;
use CrowdfoxTimeSync\Tempo\Domain\JiraIssueId;
use CrowdfoxTimeSync\Tempo\Domain\LogEntry;
use CrowdfoxTimeSync\Tempo\Domain\SetWorkLogEntry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/** @covers \CrowdfoxTimeSync\SyncHarvestToTempo\Domain\SendHarvestEntryToTempo */
final class SendHarvestEntryToTempoTest extends TestCase
{
    /** @var GetWorkLogEntries&MockObject */
    private GetWorkLogEntries $getWorkLogEntries;
    /** @var SetWorkLogEntry&MockObject */
    private SetWorkLogEntry $setWorkLogEntry;
    private SendHarvestEntryToTempo $sendHarvestEntryToTempo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->getWorkLogEntries       = $this->createMock(GetWorkLogEntries::class);
        $this->setWorkLogEntry         = $this->createMock(SetWorkLogEntry::class);
        $this->sendHarvestEntryToTempo = new SendHarvestEntryToTempo(
            new JiraIssueId('FALLBACK-1'),
            $this->getWorkLogEntries,
            $this->setWorkLogEntry,
        );
    }

    public function testWillSendOnlyNonExistingRecordsToTempo(): void
    {
        $this->setWorkLogEntry->expects(self::exactly(2))
            ->method('__invoke')
            ->with(self::logicalOr(
                self::equalTo(new LogEntry(
                    new JiraIssueId('NEW-1'),
                    'NEW-1 harvest:123',
                    3600,
                    new SpentDate('2022-08-09'),
                )),
                self::equalTo(new LogEntry(
                    new JiraIssueId('FALLBACK-1'),
                    'something else FALLBACK-1 harvest:123',
                    3600,
                    new SpentDate('2022-08-09'),
                )),
            ));

        $this->getWorkLogEntries->method('__invoke')
            ->willReturn([
                new LogEntry(
                    new JiraIssueId('EXISTING-1'),
                    'description harvest:123',
                    1,
                    new SpentDate('2022-08-09'),
                ),
                new LogEntry(
                    new JiraIssueId('EXISTING-2'),
                    'description harvest:123',
                    1,
                    new SpentDate('2022-08-09'),
                ),
            ]);

        ($this->sendHarvestEntryToTempo)(new TimeEntry(
            '123',
            4,
            'EXISTING-1, EXISTING-2, NEW-1, something else',
            new SpentDate('2022-08-09'),
        ));
    }
}
