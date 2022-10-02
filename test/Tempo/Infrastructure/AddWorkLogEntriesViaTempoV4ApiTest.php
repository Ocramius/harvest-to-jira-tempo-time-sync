<?php

declare(strict_types=1);

namespace CrowdfoxTimeSyncTest\Tempo\Infrastructure;

use CrowdfoxTimeSync\Harvest\Domain\SpentDate;
use CrowdfoxTimeSync\Tempo\Domain\JiraIssueId;
use CrowdfoxTimeSync\Tempo\Domain\LogEntry;
use CrowdfoxTimeSync\Tempo\Infrastructure\AddWorkLogEntryViaTempoV4Api;
use Http\Discovery\Psr17FactoryDiscovery;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psl\Exception\InvariantViolationException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;

/** @covers \CrowdfoxTimeSync\Tempo\Infrastructure\AddWorkLogEntryViaTempoV4Api */
final class AddWorkLogEntriesViaTempoV4ApiTest extends TestCase
{
    /** @var ClientInterface&MockObject */
    private ClientInterface $httpClient;
    private ResponseFactoryInterface $responseFactory;
    private AddWorkLogEntryViaTempoV4Api $addEntry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->httpClient      = $this->createMock(ClientInterface::class);
        $this->responseFactory = Psr17FactoryDiscovery::findResponseFactory();
        $this->addEntry        = new AddWorkLogEntryViaTempoV4Api(
            $this->httpClient,
            Psr17FactoryDiscovery::findRequestFactory()
                ->createRequest('GET', 'http://example.com')
                ->withAddedHeader('X-Ocramius-Was-Here', 'a custom added header'),
            'abc123',
            'jiraid123',
        );
    }

    public function testWillAddGivenWorkEntry(): void
    {
        $response = $this->responseFactory->createResponse();

        $this->httpClient->expects(self::once())
            ->method('sendRequest')
            ->with(self::callback(static function (RequestInterface $request): bool {
                self::assertSame('POST', $request->getMethod());
                self::assertSame(
                    'https://api.tempo.io/4/worklogs',
                    $request->getUri()->__toString(),
                );
                self::assertSame(
                    [
                        'X-Ocramius-Was-Here' => ['a custom added header'],
                        'Host'                => ['api.tempo.io'],
                        'Authorization'       => ['Bearer abc123'],
                    ],
                    $request->getHeaders(),
                );
                self::assertJsonStringEqualsJsonString(
                    <<<'JSON'
{
  "authorAccountId": "jiraid123",
  "description": "Working on issue foo",
  "issueKey": "AB-12",
  "startDate": "2022-08-09",
  "timeSpentSeconds": 61
}
JSON
                    ,
                    $request
                        ->getBody()
                        ->__toString(),
                );

                return true;
            }))
            ->willReturn($response);

        ($this->addEntry)(new LogEntry(
            new JiraIssueId('AB-12'),
            'Working on issue foo',
            61,
            new SpentDate('2022-08-09'),
        ));
    }

    public function testWillFailIfAddingGivenLogEntryFailed(): void
    {
        $response = $this->responseFactory->createResponse(201);

        $this->httpClient->expects(self::once())
            ->method('sendRequest')
            ->willReturn($response);

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Request POST https://api.tempo.io/4/worklogs  not successful: 201');

        ($this->addEntry)(new LogEntry(
            new JiraIssueId('AB-12'),
            'Working on issue foo',
            61,
            new SpentDate('2022-08-09'),
        ));
    }
}
