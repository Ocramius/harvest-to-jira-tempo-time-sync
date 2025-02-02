<?php

declare(strict_types=1);

namespace TimeSyncTest\Jira\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psl\Exception\InvariantViolationException;
use TimeSync\Jira\Domain\IssueKey;

#[CoversClass(IssueKey::class)]
final class IssueKeyTest extends TestCase
{
    /**
     * @param non-empty-string $key
     *
     * @dataProvider validKeys
     */
    #[DataProvider('validKeys')]
    public function testValidKey(string $key)
    {
        self::assertSame($key, IssueKey::make($key)->key);
    }

    /** @return non-empty-list<list{non-empty-string}> */
    public static function validKeys(): array
    {
        return [
            ['A-1'],
            ['A-2'],
            ['A-999'],
            ['ABC-1'],
            ['ABC-2'],
            ['ABC-999'],
            ['AB-12'],
            ['ABCDEFG-1234567'],
        ];
    }

    /**
     * @param non-empty-string $key
     *
     * @dataProvider invalidKeys
     */
    #[DataProvider('invalidKeys')]
    public function testInvalidKey(string $key)
    {
        $this->expectException(InvariantViolationException::class);

        IssueKey::make($key);
    }

    /** @return non-empty-list<list{non-empty-string}> */
    public static function invalidKeys(): array
    {
        return [
            [' '],
            ['0'],
            ['A'],
            ['ABC-'],
            ['-2'],
            ['ABC - 999'],
            [' A-1'],
            ['A-1 '],
            [' A-1 '],
            ['A-0'],
        ];
    }
}
