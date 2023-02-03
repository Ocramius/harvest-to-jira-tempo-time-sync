<?php

declare(strict_types=1);

namespace TimeSyncTest\Tempo\Domain;

use PHPUnit\Framework\TestCase;
use Psl\Exception\InvariantViolationException;
use TimeSync\Tempo\Domain\JiraIssueId;

/** @covers \TimeSync\Tempo\Domain\JiraIssueId */
final class JiraIssueIdTest extends TestCase
{
    /**
     * @param non-empty-string $id
     *
     * @dataProvider validIds
     */
    public function testValidJiraIssueId(string $id): void
    {
        self::assertSame($id, (new JiraIssueId($id))->id);
    }

    /** @return non-empty-list<array{non-empty-string}> */
    public static function validIds(): array
    {
        return [
            ['A-1'],
            ['D22-123'],
            ['D22-1'],
            ['D22F-1'],
        ];
    }

    /**
     * @param non-empty-string $id
     * @param non-empty-string $expectedExceptionMessage
     *
     * @dataProvider invalidIds
     */
    public function testInvalidJiraIssueId(string $id, string $expectedExceptionMessage): void
    {
        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        self::assertSame($id, (new JiraIssueId($id))->id);
    }

    /** @return non-empty-list<array{non-empty-string, non-empty-string}> */
    public static function invalidIds(): array
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

    /**
     * @param non-empty-string $id
     *
     * @dataProvider validSelfUrls
     */
    public function testFromSelfUrl(string $url, string $id): void
    {
        self::assertSame($id, JiraIssueId::fromSelfUrl($url)->id);
    }

    /**
     * @param non-empty-string $expectedId
     *
     * @dataProvider validSelfUrlsAndDescriptions
     */
    public function testFromValidSelfUrlOrDescription(
        string $url,
        string $description,
        string $expectedId,
    ): void {
        $id = JiraIssueId::fromSelfUrlOrDescription($url, $description);

        self::assertNotNull($id);
        self::assertSame($expectedId, $id->id);
    }

    /** @return non-empty-list<array{non-empty-string, non-empty-string}> */
    public static function validSelfUrls(): array
    {
        return [
            ['/A-1', 'A-1'],
            ['http://example.com/A-2', 'A-2'],
            ['https://foo.atlassian.net/rest/api/2/issue/AB-123', 'AB-123'],
        ];
    }

    /** @return non-empty-list<array{string, string, non-empty-string}> */
    public static function validSelfUrlsAndDescriptions(): array
    {
        return [
            ['/A-1', 'A-1', 'A-1'],
            ['/', 'A-1', 'A-1'],
            ['/', 'I worked on A-1 during the night', 'A-1'],
            ['/', 'i worked on AA-11 during the night', 'AA-11'],
        ];
    }

    /**
     * @param non-empty-string $expectedExceptionMessage
     *
     * @dataProvider invalidSelfUrls
     */
    public function testFromInvalidSelfUrl(string $url, string $expectedExceptionMessage): void
    {
        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        JiraIssueId::fromSelfUrl($url);
    }

    /** @return non-empty-list<array{string, non-empty-string}> */
    public static function invalidSelfUrls(): array
    {
        return [
            [
                '',
                'Url "" does not contain a Jira issue ID',
            ],
            [
                '/A-',
                'Url "/A-" does not contain a Jira issue ID',
            ],
            [
                'http://example.com/FOO',
                'Url "http://example.com/FOO" does not contain a Jira issue ID',
            ],
            [
                'http://example.com/',
                'Url "http://example.com/" does not contain a Jira issue ID',
            ],
            [
                'https://foo.atlassian.net/rest/api/2/issue/',
                'Url "https://foo.atlassian.net/rest/api/2/issue/" does not contain a Jira issue ID',
            ],
            [
                'https://foo.atlassian.net/rest/api/2/issue/123',
                'Url "https://foo.atlassian.net/rest/api/2/issue/123" does not contain a Jira issue ID',
            ],
        ];
    }

    /**
     * @param non-empty-string $expectedExceptionMessage
     *
     * @dataProvider invalidSelfUrlsAndDescriptions
     */
    public function testFromInvalidSelfUrlOrDescription(
        string $url,
        string $description,
    ): void {
        self::assertNull(JiraIssueId::fromSelfUrlOrDescription($url, $description));
    }

    /** @return non-empty-list<array{string, string}> */
    public static function invalidSelfUrlsAndDescriptions(): array
    {
        return [
            [
                '',
                '',
            ],
            [
                '/A-',
                'FOO-',
            ],
            [
                'http://example.com/FOO',
                ' FOO - BAR',
            ],
            [
                'http://example.com/FOO',
                ' FOO - 123',
            ],
            [
                'http://example.com/FOO',
                ' FOO- 123',
            ],
            [
                'http://example.com/FOO',
                ' FOO -123',
            ],
        ];
    }
}
