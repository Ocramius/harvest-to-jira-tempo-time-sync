<?php

declare(strict_types=1);

namespace TimeSyncTest\Tempo\Infrastructure;

use Http\Discovery\Psr17FactoryDiscovery;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psl\Exception\InvariantViolationException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use TimeSync\Harvest\Domain\SpentDate;
use TimeSync\Tempo\Domain\JiraIssueId;
use TimeSync\Tempo\Domain\LogEntry;
use TimeSync\Tempo\Infrastructure\AddWorkLogEntryViaTempoV3Api;

/** @covers \TimeSync\Tempo\Infrastructure\AddWorkLogEntryViaTempoV3Api */
final class AddWorkLogEntriesViaTempoV3ApiTest extends TestCase
{
    /** @var ClientInterface&MockObject */
    private ClientInterface $httpClient;
    private ResponseFactoryInterface $responseFactory;
    private AddWorkLogEntryViaTempoV3Api $addEntry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->httpClient      = $this->createMock(ClientInterface::class);
        $this->responseFactory = Psr17FactoryDiscovery::findResponseFactory();
        $this->addEntry        = new AddWorkLogEntryViaTempoV3Api(
            $this->httpClient,
            Psr17FactoryDiscovery::findRequestFactory(),
            'abc123',
            'jiraid123',
            [
                'custom_attribute_1' => 'custom_value_1',
                'custom_attribute_2' => 'custom_value_2',
            ]
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
                    'https://api.tempo.io/core/3/worklogs',
                    $request->getUri()->__toString(),
                );
                self::assertSame(
                    [
                        'Host'                => ['api.tempo.io'],
                        'Authorization'       => ['Bearer abc123'],
                        'Content-Type'        => ['application/json'],
                        'Accept'              => ['application/json'],
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
  "timeSpentSeconds": 61,
  "attributes": [
    {
      "key":  "custom_attribute_1",
      "value": "custom_value_1"
    },
    {
      "key":  "custom_attribute_2",
      "value": "custom_value_2"
   }
  ]
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

        $response->getBody()
            ->write('HAHA!');

        $this->httpClient->expects(self::once())
            ->method('sendRequest')
            ->willReturn($response);

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage("Request POST https://api.tempo.io/core/3/worklogs  not successful: 201\nHAHA!");

        ($this->addEntry)(new LogEntry(
            new JiraIssueId('AB-12'),
            'Working on issue foo',
            61,
            new SpentDate('2022-08-09'),
        ));
    }
}
