<?php

declare(strict_types=1);

namespace TimeSyncTest\Jira\Infrastructure\Exception;

use Http\Discovery\Psr17FactoryDiscovery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TimeSync\Jira\Domain\IssueKey;
use TimeSync\Jira\Infrastructure\Exception\IssueNotFound;

#[CoversClass(IssueNotFound::class)]
final class IssueNotFoundTest extends TestCase
{
    public function testExceptionMessage(): void
    {
        $response = Psr17FactoryDiscovery::findResponseFactory()
            ->createResponse(123);

        $response->getBody()->write('haha');

        $key       = IssueKey::make('ABC-123');
        $exception = new IssueNotFound($key, $response);

        self::assertEquals($key, $exception->key());
        self::assertEquals(<<<'MESSAGE'
Could not retrieve issue ID for key "ABC-123"

Server responded with code 123 and message:

haha
MESSAGE
            , $exception->getMessage());
    }
}
