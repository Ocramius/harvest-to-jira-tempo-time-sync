<?php

declare(strict_types=1);

namespace TimeSyncTest\SyncHarvestToTempo\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psl\Type;
use TimeSync\Harvest\Domain\SpentDate;
use TimeSync\Harvest\Domain\TimeEntry;
use TimeSync\Jira\Domain\GetIssueIdForKey;
use TimeSync\Jira\Domain\IssueId;
use TimeSync\Jira\Domain\IssueKey;
use TimeSync\SyncHarvestToTempo\Domain\SendHarvestEntryToTempo;
use TimeSync\Tempo\Domain\GetWorkLogEntries;
use TimeSync\Tempo\Domain\JiraIssueId;
use TimeSync\Tempo\Domain\LogEntry;
use TimeSync\Tempo\Domain\SetWorkLogEntry;

use function base_convert;
use function sha1;
use function substr;

#[CoversClass(SendHarvestEntryToTempo::class)]
final class SendHarvestEntryToTempoTest extends TestCase
{
    private GetIssueIdForKey&Stub $getId;
    private GetWorkLogEntries&Stub $getWorkLogEntries;
    private SetWorkLogEntry&MockObject $setWorkLogEntry;
    private SendHarvestEntryToTempo $sendHarvestEntryToTempo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->getId                   = $this->createStub(GetIssueIdForKey::class);
        $this->getWorkLogEntries       = $this->createStub(GetWorkLogEntries::class);
        $this->setWorkLogEntry         = $this->createMock(SetWorkLogEntry::class);
        $this->sendHarvestEntryToTempo = new SendHarvestEntryToTempo(
            $this->getId,
            new JiraIssueId(IssueId::make(12345), IssueKey::make('FALLBACK-1')),
            $this->getWorkLogEntries,
            $this->setWorkLogEntry,
        );

        $this->getId->method('__invoke')
            ->willReturnCallback(static function (IssueKey $key): IssueId {
                return self::id($key->key)->id;
            });
    }

    public function testWillSendOnlyNonExistingRecordsToTempo(): void
    {
        $this->setWorkLogEntry->expects(self::exactly(2))
            ->method('__invoke')
            ->with(self::logicalOr(
                self::equalTo(new LogEntry(
                    new JiraIssueId(IssueId::make(130956), IssueKey::make('NEW-1')),
                    'NEW-1 harvest:123',
                    3600,
                    new SpentDate('2022-08-09'),
                )),
                self::equalTo(new LogEntry(
                    new JiraIssueId(IssueId::make(12345), IssueKey::make('FALLBACK-1')),
                    'something else FALLBACK-1 harvest:123',
                    3600,
                    new SpentDate('2022-08-09'),
                )),
            ));

        $this->getWorkLogEntries->method('__invoke')
            ->willReturn([
                new LogEntry(
                    new JiraIssueId(IssueId::make(852373), IssueKey::make('EXISTING-1')),
                    'description harvest:123',
                    1,
                    new SpentDate('2022-08-09'),
                ),
                new LogEntry(
                    new JiraIssueId(IssueId::make(127786), IssueKey::make('EXISTING-2')),
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

    /** @param non-empty-string $key */
    private static function id(string $key): JiraIssueId
    {
        return new JiraIssueId(
            IssueId::make(
                Type\positive_int()
                    ->coerce(base_convert(substr(sha1($key), 0, 5), 16, 10)),
            ),
            IssueKey::make($key),
        );
    }
}
