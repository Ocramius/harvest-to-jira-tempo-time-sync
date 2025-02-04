<?php

declare(strict_types=1);

namespace TimeSyncTest\Harvest\Infrastructure;

use CuyZ\Valinor\MapperBuilder;
use Http\Discovery\Psr17FactoryDiscovery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psl\Exception\InvariantViolationException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use TimeSync\Harvest\Domain\SpentDate;
use TimeSync\Harvest\Domain\TimeEntry;
use TimeSync\Harvest\Infrastructure\GetTimeEntriesFromV2Api;

#[CoversClass(GetTimeEntriesFromV2Api::class)]
final class GetTimeEntriesFromV2ApiTest extends TestCase
{
    /** @var MockObject&ClientInterface */
    private ClientInterface $httpClient;
    private ResponseFactoryInterface $responseFactory;
    private GetTimeEntriesFromV2Api $getTimeEntries;

    protected function setUp(): void
    {
        parent::setUp();

        $this->httpClient      = $this->createMock(ClientInterface::class);
        $this->responseFactory = Psr17FactoryDiscovery::findResponseFactory();
        $this->getTimeEntries  = new GetTimeEntriesFromV2Api(
            $this->httpClient,
            Psr17FactoryDiscovery::findUriFactory(),
            Psr17FactoryDiscovery::findRequestFactory(),
            (new MapperBuilder())
                ->enableFlexibleCasting()
                ->allowSuperfluousKeys()
                ->mapper(),
            'abc123',
            'super$ecret',
        );
    }

    public function testWillRetrieveAnEmptyResultSet(): void
    {
        $response = $this->responseFactory->createResponse();

        $response->getBody()
            ->write(
                <<<'JSON'
{
  "time_entries":[
    {
      "id": 111,
      "hours": 12.34,
      "notes": "foo bar",
      "spent_date": "2022-08-03"
    }
  ],
  "links":{
    "next": null
  }
}
JSON,
            );
        $this->httpClient->expects(self::once())
            ->method('sendRequest')
            ->with(self::callback(static function (RequestInterface $request): bool {
                self::assertSame(
                    'https://api.harvestapp.com/v2/time_entries?project_id=def456&is_billed=false',
                    $request
                        ->getUri()
                        ->__toString(),
                );
                self::assertEquals(
                    [
                        'Host'                => ['api.harvestapp.com'],
                        'Harvest-Account-Id'  => ['abc123'],
                        'Authorization'       => ['Bearer super$ecret'],
                        'User-Agent'          => [GetTimeEntriesFromV2Api::class],
                    ],
                    $request->getHeaders(),
                );

                return true;
            }))
            ->willReturn($response);

        self::assertEquals(
            [new TimeEntry('111', 12.34, 'foo bar', new SpentDate('2022-08-03'))],
            ($this->getTimeEntries)('def456'),
        );
    }

    public function testWillRejectNon200HttpResponses(): void
    {
        $response = $this->responseFactory->createResponse(201);

        $this->httpClient->expects(self::once())
            ->method('sendRequest')
            ->willReturn($response);

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Request https://api.harvestapp.com/v2/time_entries?project_id=def456&is_billed=false  not successful: 201');

        ($this->getTimeEntries)('def456');
    }

    public function testWillRetrieveMultiplePages(): void
    {
        $response1 = $this->responseFactory->createResponse();
        $response2 = $this->responseFactory->createResponse();
        $response3 = $this->responseFactory->createResponse();

        $response1->getBody()
            ->write(
                <<<'JSON'
{
  "time_entries":[
    {
      "id": 111,
      "hours": 12.34,
      "notes": "foo bar",
      "spent_date": "2022-08-03"
    }
  ],
  "links":{
    "next": "http://example.com/page2"
  }
}
JSON,
            );
        $response2->getBody()
            ->write(
                <<<'JSON'
{
  "time_entries":[
    {
      "id": 222,
      "hours": 56.78,
      "notes": "baz tab",
      "spent_date": "2022-08-04"
    }
  ],
  "links":{
    "next": "http://example.com/page3"
  }
}
JSON,
            );
        $response3->getBody()
            ->write(
                <<<'JSON'
{
  "time_entries":[
    {
      "id": 333,
      "hours": 91.01,
      "notes": "taz tar",
      "spent_date": "2022-08-05"
    }
  ],
  "links":{
    "next": null
  }
}
JSON,
            );

        $this->httpClient->expects(self::exactly(3))
            ->method('sendRequest')
            ->willReturnCallback(
                static function (RequestInterface $request) use (
                    $response1,
                    $response2,
                    $response3,
                ): ResponseInterface {
                    if ($request->getUri()->__toString() === 'http://example.com/page2') {
                        return $response2;
                    }

                    if ($request->getUri()->__toString() === 'http://example.com/page3') {
                        return $response3;
                    }

                    return $response1;
                },
            );

        self::assertEquals(
            [
                new TimeEntry('111', 12.34, 'foo bar', new SpentDate('2022-08-03')),
                new TimeEntry('222', 56.78, 'baz tab', new SpentDate('2022-08-04')),
                new TimeEntry('333', 91.01, 'taz tar', new SpentDate('2022-08-05')),
            ],
            ($this->getTimeEntries)('def456'),
        );
    }
}
