<?php

declare(strict_types=1);

namespace TimeSyncTest\Tempo\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psl\Type;
use TimeSync\Harvest\Domain\SpentDate;
use TimeSync\Harvest\Domain\TimeEntry;
use TimeSync\Jira\Domain\GetIssueIdForKey;
use TimeSync\Jira\Domain\IssueId;
use TimeSync\Jira\Domain\IssueKey;
use TimeSync\Tempo\Domain\JiraIssueId;
use TimeSync\Tempo\Domain\LogEntry;

use function base_convert;
use function sha1;
use function substr;

#[CoversClass(LogEntry::class)]
final class LogEntryTest extends TestCase
{
    private GetIssueIdForKey&Stub $getId;

    protected function setUp(): void
    {
        $this->getId = $this->createStub(GetIssueIdForKey::class);

        $this->getId->method('__invoke')
            ->willReturnCallback(static function (IssueKey $key): IssueId {
                return self::id($key->key)->id;
            });
    }

    /**
     * @param non-empty-string $description
     * @param non-empty-string $harvestId
     *
     * @dataProvider logDescriptionsMatchingHarvestIdentifiers
     */
    #[DataProvider('logDescriptionsMatchingHarvestIdentifiers')]
    public function testMatchesGivenTimeEntry(string $description, string $harvestId): void
    {
        self::assertTrue(
            (new LogEntry(self::id('AB12-123'), $description, 1, new SpentDate('2022-08-07')))
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
    #[DataProvider('logDescriptionsNotMatchingHarvestIdentifiers')]
    public function testDoesNotMatchGivenTimeEntry(string $description, string $harvestId): void
    {
        self::assertFalse(
            (new LogEntry(self::id('AB12-123'), $description, 1, new SpentDate('2022-08-07')))
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
    #[DataProvider('logDescriptionsMatchingHarvestIdentifiers')]
    public function testDoesNotMatchGivenTimeEntryIfDateDoesNotMatch(string $description, string $harvestId): void
    {
        self::assertFalse(
            (new LogEntry(self::id('AB12-123'), $description, 1, new SpentDate('2022-08-07')))
                ->matchesTimeEntry(new TimeEntry($harvestId, 0.1, 'hello', new SpentDate('2022-08-08'))),
        );
    }

    /**
     * @param non-empty-list<LogEntry> $logEntries
     *
     * @dataProvider examplesOfSplitTimeEntry
     */
    #[DataProvider('examplesOfSplitTimeEntry')]
    public function testWillSplitATimeEntryIntoMultipleLogEntries(TimeEntry $timeEntry, array $logEntries): void
    {
        self::assertEquals(
            $logEntries,
            LogEntry::splitTimeEntry($this->getId, $timeEntry, self::id('A1-1')),
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
                    new LogEntry(self::id('AB12-10'), 'AB12-10 harvest:123', 9000, new SpentDate('2022-09-05')),
                    new LogEntry(self::id('AB12-20'), 'AB12-20 harvest:123', 9000, new SpentDate('2022-09-05')),
                    new LogEntry(self::id('AB12-30'), 'AB12-30 harvest:123', 9000, new SpentDate('2022-09-05')),
                    new LogEntry(self::id('AB12-40'), 'AB12-40 harvest:123', 9000, new SpentDate('2022-09-05')),
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
                    new LogEntry(self::id('A1-1'), 'A1-1 harvest:124', 36000, new SpentDate('2022-09-05')),
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
                    new LogEntry(self::id('A1-1'), 'hello world A1-1 harvest:124', 36000, new SpentDate('2022-09-05')),
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
                    new LogEntry(self::id('A1-2'), 'A1-1 A1-2 harvest:125', 9000, new SpentDate('2022-09-05')),
                    new LogEntry(self::id('A2-4'), 'A2-3 A2-4 harvest:125', 9000, new SpentDate('2022-09-05')),
                    new LogEntry(self::id('A3-5'), 'A3-5 harvest:125', 9000, new SpentDate('2022-09-05')),
                    new LogEntry(self::id('A4-6'), 'A4-6 harvest:125', 9000, new SpentDate('2022-09-05')),
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
                    new LogEntry(self::id('A1-1'), 'A1-1 done some work harvest:125', 18000, new SpentDate('2022-09-05')),
                    new LogEntry(self::id('A2-2'), 'more A2-2 work harvest:125', 18000, new SpentDate('2022-09-05')),
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
                    new LogEntry(self::id('A2-2'), 'A2-2 done some work harvest:125', 18000, new SpentDate('2022-09-05')),
                    new LogEntry(self::id('A1-1'), 'more work A1-1 harvest:125', 18000, new SpentDate('2022-09-05')),
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
    #[DataProvider('examplesOfSameAndDifferentDayAndIssue')]
    public function testAppliesToSameIssueAndDay(
        string $issueId1,
        string $issueId2,
        string $date1,
        string $date2,
        bool $expected,
    ): void {
        self::assertSame(
            $expected,
            (new LogEntry(self::id($issueId1), 'a description', 1, new SpentDate($date1)))
                ->appliesToSameIssueAndDay(new LogEntry(self::id($issueId2), 'another description', 2, new SpentDate($date2))),
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
