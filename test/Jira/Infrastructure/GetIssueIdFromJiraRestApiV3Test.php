<?php

declare(strict_types=1);

namespace TimeSyncTest\Jira\Infrastructure;

use Http\Discovery\Psr17FactoryDiscovery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use TimeSync\Jira\Domain\IssueId;
use TimeSync\Jira\Domain\IssueKey;
use TimeSync\Jira\Infrastructure\GetIssueIdFromJiraRestApiV3;
use TimeSyncTest\OpenAPI\WrapResponseCallbackInValidationCallback;

#[CoversClass(GetIssueIdFromJiraRestApiV3::class)]
final class GetIssueIdFromJiraRestApiV3Test extends TestCase
{
    private ClientInterface&MockObject $client;
    private GetIssueIdFromJiraRestApiV3 $getIssueId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client     = $this->createMock(ClientInterface::class);
        $this->getIssueId = new GetIssueIdFromJiraRestApiV3(
            $this->client,
            Psr17FactoryDiscovery::findRequestFactory(),
            'http://jira.example.com',
            'me@example.com',
            'super$ecret',
        );
    }

    public function testWillRetrieveValidId(): void
    {
        $this->client
            ->method('sendRequest')
            ->willReturnCallback(WrapResponseCallbackInValidationCallback::wrap(
                __DIR__ . '/swagger-v3.v3.json',
                static function (RequestInterface $request): ResponseInterface {
                    self::assertEquals(
                        'http://jira.example.com/rest/api/3/issue/HAHA-123',
                        $request->getUri()->__toString(),
                    );
                    self::assertEquals(
                        'Basic bWVAZXhhbXBsZS5jb206c3VwZXIkZWNyZXQ=',
                        $request->getHeaderLine('Authorization'),
                    );

                    return self::newResponse('{"id": "1234"}')
                        ->withStatus(200)
                        ->withAddedHeader('Content-Type', 'application/json');
                },
            ));

        self::assertEquals(
            IssueId::make(1234),
            $this->getIssueId->__invoke(IssueKey::make('HAHA-123')),
        );
    }

    private static function newResponse(string $body): ResponseInterface
    {
        $response = Psr17FactoryDiscovery::findResponseFactory()
            ->createResponse();

        $response->getBody()->write($body);

        return $response;
    }
}
