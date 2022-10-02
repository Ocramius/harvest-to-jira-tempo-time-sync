<?php

declare(strict_types=1);

namespace CrowdfoxTimeSyncTest\Tempo\Domain;

use CrowdfoxTimeSync\Harvest\Domain\SpentDate;
use CrowdfoxTimeSync\Harvest\Domain\TimeEntry;
use CrowdfoxTimeSync\Tempo\Domain\JiraIssueId;
use CrowdfoxTimeSync\Tempo\Domain\LogEntry;
use PHPUnit\Framework\TestCase;

/** @covers \CrowdfoxTimeSync\Tempo\Domain\LogEntry */
final class LogEntryTest extends TestCase
{
    /**
     * @param non-empty-string $description
     * @param non-empty-string $harvestId
     *
     * @dataProvider logDescriptionsMatchingHarvestIdentifiers
     */
    public function testMatchesGivenTimeEntry(string $description, string $harvestId): void
    {
        self::assertTrue(
            (new LogEntry(new JiraIssueId('AB12-123'), $description, 1))
                ->matchesTimeEntry(new TimeEntry($harvestId, 0.1, 'hello', new SpentDate('2022-08-07'))),
        );
    }

    /** @return non-empty-list<array{non-empty-string, non-empty-string}> */
    public function logDescriptionsMatchingHarvestIdentifiers(): array
    {
        return [
            ['harvest:1', '1'],
            ['harvest:12345', '12345'],
            ['harvest:foo bar baz', 'foo bar baz'],
            ['harvest:i / am / a / complex / id', 'i / am / a / complex / id'],
            ['This is some description upfront harvest:i / am / a / complex / id', 'i / am / a / complex / id'],
        ];
    }

    /**
     * @param non-empty-string $harvestId
     *
     * @dataProvider logDescriptionsNotMatchingHarvestIdentifiers
     */
    public function testDoesNotMatchGivenTimeEntry(string $description, string $harvestId): void
    {
        self::assertFalse(
            (new LogEntry(new JiraIssueId('AB12-123'), $description, 1))
                ->matchesTimeEntry(new TimeEntry($harvestId, 0.1, 'hello', new SpentDate('2022-08-07'))),
        );
    }

    /** @return non-empty-list<array{string, non-empty-string}> */
    public function logDescriptionsNotMatchingHarvestIdentifiers(): array
    {
        return [
            ['', '1'],
            ['harvest:1', '2'],
            ['harvest:2', '1'],
            ['harvest:12346', '12345'],
            ['harvest:12345', '12346'],
            ['harvest:12345', ' 12345'],
            ['harvest:12345', '12345 '],
            ['12345', '12345'],
            ['harvast:12345', '12345'],
            ['harvest:12345 and something after it', '12345'],
        ];
    }

    /**
     * @param non-empty-list<LogEntry> $logEntries
     *
     * @dataProvider examplesOfSplitTimeEntry
     */
    public function testWillSplitATimeEntryIntoMultipleLogEntries(TimeEntry $timeEntry, array $logEntries): void
    {
        self::assertEquals(
            $logEntries,
            LogEntry::splitTimeEntry($timeEntry, new JiraIssueId('A1-1')),
        );
    }

    /** @return non-empty-array<non-empty-string, array{TimeEntry, non-empty-list<LogEntry>}> */
    public function examplesOfSplitTimeEntry(): array
    {
        return [
            'time entry perfectly split in 4 existing issues' => [
                new TimeEntry(
                    '123',
                    10,
                    'AB12-10, AB12-20, AB12-30, AB12-40',
                    new SpentDate('2022-09-05'),
                ),
                [
                    new LogEntry(new JiraIssueId('AB12-10'), 'AB12-10 harvest:123', 9000),
                    new LogEntry(new JiraIssueId('AB12-20'), 'AB12-20 harvest:123', 9000),
                    new LogEntry(new JiraIssueId('AB12-30'), 'AB12-30 harvest:123', 9000),
                    new LogEntry(new JiraIssueId('AB12-40'), 'AB12-40 harvest:123', 9000),
                ],
            ],
            'time entry with no assigned issue'               => [
                new TimeEntry(
                    '124',
                    10,
                    ' ',
                    new SpentDate('2022-09-05'),
                ),
                [
                    new LogEntry(new JiraIssueId('A1-1'), 'A1-1 harvest:124', 36000),
                ],
            ],
            'time entry with no assigned issue, but with a description'               => [
                new TimeEntry(
                    '124',
                    10,
                    ' hello world',
                    new SpentDate('2022-09-05'),
                ),
                [
                    new LogEntry(new JiraIssueId('A1-1'), 'hello world A1-1 harvest:124', 36000),
                ],
            ],
            'time entry with multiple issues in single CSV entry'               => [
                new TimeEntry(
                    '125',
                    10,
                    'A1-1 A1-2, A2-3 A2-4, A3-5, A4-6',
                    new SpentDate('2022-09-05'),
                ),
                [
                    new LogEntry(new JiraIssueId('A1-2'), 'A1-1 A1-2 harvest:125', 9000),
                    new LogEntry(new JiraIssueId('A2-4'), 'A2-3 A2-4 harvest:125', 9000),
                    new LogEntry(new JiraIssueId('A3-5'), 'A3-5 harvest:125', 9000),
                    new LogEntry(new JiraIssueId('A4-6'), 'A4-6 harvest:125', 9000),
                ],
            ],
            'log entries with a short description'               => [
                new TimeEntry(
                    '125',
                    10,
                    'A1-1 done some work, more A2-2 work',
                    new SpentDate('2022-09-05'),
                ),
                [
                    new LogEntry(new JiraIssueId('A1-1'), 'A1-1 done some work harvest:125', 18000),
                    new LogEntry(new JiraIssueId('A2-2'), 'more A2-2 work harvest:125', 18000),
                ],
            ],
            'log entries with issue id, as well as issues without id'               => [
                new TimeEntry(
                    '125',
                    10,
                    'A2-2 done some work, more work',
                    new SpentDate('2022-09-05'),
                ),
                [
                    new LogEntry(new JiraIssueId('A2-2'), 'A2-2 done some work harvest:125', 18000),
                    new LogEntry(new JiraIssueId('A1-1'), 'more work A1-1 harvest:125', 18000),
                ],
            ],
        ];
    }
}
