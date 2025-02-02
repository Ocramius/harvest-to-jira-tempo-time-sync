<?php

declare(strict_types=1);

namespace TimeSyncTest\Tempo\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psl\Exception\InvariantViolationException;
use Psl\Type;
use TimeSync\Jira\Domain\GetIssueIdForKey;
use TimeSync\Jira\Domain\IssueId;
use TimeSync\Jira\Domain\IssueKey;
use TimeSync\Tempo\Domain\JiraIssueId;

use function base_convert;
use function sha1;
use function substr;

#[CoversClass(JiraIssueId::class)]
final class JiraIssueIdTest extends TestCase
{
    private GetIssueIdForKey&Stub $getId;

    protected function setUp(): void
    {
        $this->getId = $this->createStub(GetIssueIdForKey::class);

        $this->getId->method('__invoke')
            ->willReturnCallback(static function (IssueKey $key): IssueId {
                return IssueId::make(
                    Type\positive_int()
                        ->coerce(base_convert(substr(sha1($key->key), 0, 5), 16, 10)),
                );
            });
    }

    /**
     * @param non-empty-string $expectedKey
     * @param int<1, max>      $expectedId
     *
     * @dataProvider validSelfUrls
     */
    #[DataProvider('validSelfUrls')]
    public function testFromSelfUrl(string $url, string $expectedKey, int $expectedId): void
    {
        $id = JiraIssueId::fromSelfUrl($this->getId, $url);

        self::assertEquals(IssueKey::make($expectedKey), $id->key);
        self::assertEquals(IssueId::make($expectedId), $id->id);
    }

    /**
     * @param non-empty-string $expectedKey
     * @param int<1, max>      $expectedId
     *
     * @dataProvider validSelfUrlsAndDescriptions
     */
    #[DataProvider('validSelfUrlsAndDescriptions')]
    public function testFromValidSelfUrlOrDescription(
        string $url,
        string $description,
        string $expectedKey,
        int $expectedId,
    ): void {
        $id = JiraIssueId::fromSelfUrlOrDescription($this->getId, $url, $description);

        self::assertNotNull($id);
        self::assertEquals(IssueKey::make($expectedKey), $id->key);
        self::assertEquals(IssueId::make($expectedId), $id->id);
    }

    /** @return non-empty-list<array{non-empty-string, non-empty-string, int<1, max>}> */
    public static function validSelfUrls(): array
    {
        return [
            ['/A-1', 'A-1', 18439],
            ['http://example.com/A-2', 'A-2', 233099],
            ['https://foo.atlassian.net/rest/api/2/issue/AB-123', 'AB-123', 694623],
        ];
    }

    /** @return non-empty-list<array{string, string, non-empty-string, int<1, max>}> */
    public static function validSelfUrlsAndDescriptions(): array
    {
        return [
            ['/A-1', 'A-1', 'A-1', 18439],
            ['/', 'A-1', 'A-1', 18439],
            ['/', 'I worked on A-1 during the night', 'A-1', 18439],
            ['/', 'i worked on AA-11 during the night', 'AA-11', 447406],
        ];
    }

    /**
     * @param non-empty-string $expectedExceptionMessage
     *
     * @dataProvider invalidSelfUrls
     */
    #[DataProvider('invalidSelfUrls')]
    public function testFromInvalidSelfUrl(string $url, string $expectedExceptionMessage): void
    {
        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        JiraIssueId::fromSelfUrl($this->getId, $url);
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

    /** @dataProvider invalidSelfUrlsAndDescriptions */
    #[DataProvider('invalidSelfUrlsAndDescriptions')]
    public function testFromInvalidSelfUrlOrDescription(
        string $url,
        string $description,
    ): void {
        self::assertNull(JiraIssueId::fromSelfUrlOrDescription($this->getId, $url, $description));
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
