<?php

declare(strict_types=1);

namespace TimeSyncTest\Tempo\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TimeSync\Harvest\Domain\SpentDate;
use TimeSync\Harvest\Domain\TimeEntry;
use TimeSync\Tempo\Domain\JiraIssueId;
use TimeSync\Tempo\Domain\LogEntry;

#[CoversClass(LogEntry::class)]
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
            (new LogEntry(new JiraIssueId('AB12-123'), $description, 1, new SpentDate('2022-08-07')))
                ->matchesTimeEntry(new TimeEntry($harvestId, 0.1, 'hello', new SpentDate('2022-08-07'))),
        );
    }

    /** @return non-empty-list<array{non-empty-string, non-empty-string}> */
    public static function logDescriptionsMatchingHarvestIdentifiers(): array
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
            (new LogEntry(new JiraIssueId('AB12-123'), $description, 1, new SpentDate('2022-08-07')))
                ->matchesTimeEntry(new TimeEntry($harvestId, 0.1, 'hello', new SpentDate('2022-08-07'))),
        );
    }

    /** @return non-empty-list<array{string, non-empty-string}> */
    public static function logDescriptionsNotMatchingHarvestIdentifiers(): array
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
     * @param non-empty-string $harvestId
     *
     * @dataProvider logDescriptionsMatchingHarvestIdentifiers
     */
    public function testDoesNotMatchGivenTimeEntryIfDateDoesNotMatch(string $description, string $harvestId): void
    {
        self::assertFalse(
            (new LogEntry(new JiraIssueId('AB12-123'), $description, 1, new SpentDate('2022-08-07')))
                ->matchesTimeEntry(new TimeEntry($harvestId, 0.1, 'hello', new SpentDate('2022-08-08'))),
        );
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
    public static function examplesOfSplitTimeEntry(): array
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
                    new LogEntry(new JiraIssueId('AB12-10'), 'AB12-10 harvest:123', 9000, new SpentDate('2022-09-05')),
                    new LogEntry(new JiraIssueId('AB12-20'), 'AB12-20 harvest:123', 9000, new SpentDate('2022-09-05')),
                    new LogEntry(new JiraIssueId('AB12-30'), 'AB12-30 harvest:123', 9000, new SpentDate('2022-09-05')),
                    new LogEntry(new JiraIssueId('AB12-40'), 'AB12-40 harvest:123', 9000, new SpentDate('2022-09-05')),
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
                    new LogEntry(new JiraIssueId('A1-1'), 'A1-1 harvest:124', 36000, new SpentDate('2022-09-05')),
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
                    new LogEntry(new JiraIssueId('A1-1'), 'hello world A1-1 harvest:124', 36000, new SpentDate('2022-09-05')),
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
                    new LogEntry(new JiraIssueId('A1-2'), 'A1-1 A1-2 harvest:125', 9000, new SpentDate('2022-09-05')),
                    new LogEntry(new JiraIssueId('A2-4'), 'A2-3 A2-4 harvest:125', 9000, new SpentDate('2022-09-05')),
                    new LogEntry(new JiraIssueId('A3-5'), 'A3-5 harvest:125', 9000, new SpentDate('2022-09-05')),
                    new LogEntry(new JiraIssueId('A4-6'), 'A4-6 harvest:125', 9000, new SpentDate('2022-09-05')),
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
                    new LogEntry(new JiraIssueId('A1-1'), 'A1-1 done some work harvest:125', 18000, new SpentDate('2022-09-05')),
                    new LogEntry(new JiraIssueId('A2-2'), 'more A2-2 work harvest:125', 18000, new SpentDate('2022-09-05')),
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
                    new LogEntry(new JiraIssueId('A2-2'), 'A2-2 done some work harvest:125', 18000, new SpentDate('2022-09-05')),
                    new LogEntry(new JiraIssueId('A1-1'), 'more work A1-1 harvest:125', 18000, new SpentDate('2022-09-05')),
                ],
            ],
        ];
    }

    /**
     * @param non-empty-string $issueId1
     * @param non-empty-string $issueId2
     * @param non-empty-string $date1
     * @param non-empty-string $date2
     *
     * @dataProvider examplesOfSameAndDifferentDayAndIssue
     */
    public function testAppliesToSameIssueAndDay(
        string $issueId1,
        string $issueId2,
        string $date1,
        string $date2,
        bool $expected,
    ): void {
        self::assertSame(
            $expected,
            (new LogEntry(new JiraIssueId($issueId1), 'a description', 1, new SpentDate($date1)))
                ->appliesToSameIssueAndDay(new LogEntry(new JiraIssueId($issueId2), 'another description', 2, new SpentDate($date2))),
        );
    }

    /** @return non-empty-list<array{non-empty-string, non-empty-string, non-empty-string, non-empty-string, bool}> */
    public static function examplesOfSameAndDifferentDayAndIssue(): array
    {
        return [
            ['AB12-123', 'AB12-123', '2022-08-01', '2022-08-01', true],
            ['AB12-123', 'AB12-123', '2022-08-01', '2022-08-02', false],
            ['AB12-123', 'AB12-123', '2022-08-02', '2022-08-01', false],
            ['AB12-123', 'AB12-124', '2022-08-01', '2022-08-01', false],
            ['AB12-124', 'AB12-123', '2022-08-01', '2022-08-01', false],
        ];
    }
}
