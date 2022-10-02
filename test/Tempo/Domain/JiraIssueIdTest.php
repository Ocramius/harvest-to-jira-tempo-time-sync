<?php

declare(strict_types=1);

namespace CrowdfoxTimeSyncTest\Tempo\Domain;

use CrowdfoxTimeSync\Tempo\Domain\JiraIssueId;
use PHPUnit\Framework\TestCase;
use Psl\Exception\InvariantViolationException;

/** @covers \CrowdfoxTimeSync\Tempo\Domain\JiraIssueId */
final class JiraIssueIdTest extends TestCase
{
    /**
     * @dataProvider validIds
     *
     * @param non-empty-string $id
     */
    public function testValidJiraIssueId(string $id): void
    {
        self::assertSame($id, (new JiraIssueId($id))->id);
    }

    /** @return non-empty-list<array{non-empty-string}> */
    public function validIds(): array
    {
        return [
            ['A-1'],
            ['D22-123'],
            ['D22-1'],
            ['D22F-1'],
        ];
    }

    /**
     * @dataProvider invalidIds
     *
     * @param non-empty-string $id
     * @param non-empty-string $expectedExceptionMessage
     */
    public function testInvalidJiraIssueId(string $id, string $expectedExceptionMessage): void
    {
        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        self::assertSame($id, (new JiraIssueId($id))->id);
    }

    /** @return non-empty-list<array{non-empty-string, non-empty-string}> */
    public function invalidIds(): array
    {
        return [
            ['A', 'Invalid Jira issue ID: "A"'],
            ['B-', 'Invalid Jira issue ID: "B-"'],
            ['a-1', 'Invalid Jira issue ID: "a-1"'],
            ['1-1', 'Invalid Jira issue ID: "1-1"'],
            ['A- 1', 'Invalid Jira issue ID: "A- 1"'],
            ['A -1', 'Invalid Jira issue ID: "A -1"'],
            ['A-A', 'Invalid Jira issue ID: "A-A"'],
            [' A-1', 'Invalid Jira issue ID: " A-1"'],
            ['A-1 ', 'Invalid Jira issue ID: "A-1 "'],
        ];
    }
}
