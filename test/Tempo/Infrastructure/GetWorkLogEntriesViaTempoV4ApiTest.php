<?php

declare(strict_types=1);

namespace CrowdfoxTimeSyncTest\Tempo\Infrastructure;

use CrowdfoxTimeSync\Harvest\Domain\SpentDate;
use CrowdfoxTimeSync\Harvest\Domain\TimeEntry;
use CrowdfoxTimeSync\Tempo\Domain\JiraIssueId;
use CrowdfoxTimeSync\Tempo\Domain\LogEntry;
use CrowdfoxTimeSync\Tempo\Infrastructure\GetWorkLogEntriesViaTempoV4Api;
use Http\Discovery\Psr17FactoryDiscovery;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psl\Exception\InvariantViolationException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;

/** @covers \CrowdfoxTimeSync\Tempo\Infrastructure\GetWorkLogEntriesViaTempoV4Api */
final class GetWorkLogEntriesViaTempoV4ApiTest extends TestCase
{
    /** @var ClientInterface&MockObject */
    private ClientInterface $httpClient;
    private ResponseFactoryInterface $responseFactory;
    private GetWorkLogEntriesViaTempoV4Api $getEntries;

    protected function setUp(): void
    {
        parent::setUp();

        $this->httpClient      = $this->createMock(ClientInterface::class);
        $this->responseFactory = Psr17FactoryDiscovery::findResponseFactory();
        $this->getEntries      = new GetWorkLogEntriesViaTempoV4Api(
            $this->httpClient,
            Psr17FactoryDiscovery::findRequestFactory()
                ->createRequest('GET', 'http://example.com')
                ->withAddedHeader('X-Ocramius-Was-Here', 'a custom added header'),
            'abc123',
            new JiraIssueId('FALLBACK-123'),
        );
    }

    public function testWillFetchLogEntriesForGivenIssues(): void
    {
        $response = $this->responseFactory->createResponse();

        $response->getBody()
            ->write(
                <<<'JSON'
{
  "results": [
    {
      "tempoWorklogId": 123,
      "issue": {
        "self": "https://foo.atlassian.net/rest/api/2/issue/AB-12",
        "id": 123
      },
      "timeSpentSeconds": 61,
      "billableSeconds": 60,
      "startDate": "2022-10-01",
      "startTime": "00:00:00",
      "description": "Working on issue foo"
    },
    {
      "tempoWorklogId": 456,
      "issue": {
        "self": "https://foo.atlassian.net/rest/api/2/issue/AB-13",
        "id": 456
      },
      "timeSpentSeconds": 64,
      "billableSeconds": 63,
      "startDate": "2022-10-02",
      "startTime": "00:00:00",
      "description": "Working on issue bar"
    }
  ]
}
JSON,
            );

        $this->httpClient->expects(self::once())
            ->method('sendRequest')
            ->with(self::callback(static function (RequestInterface $request): bool {
                self::assertSame('GET', $request->getMethod());
                self::assertSame(
                    'https://api.tempo.io/4/worklogs?issue=AB1-2&issue=AB1-3&issue=FALLBACK-123&from=2022-08-09&to=2022-08-09&limit=1000',
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

                return true;
            }))
            ->willReturn($response);

        self::assertEquals(
            [
                new LogEntry(new JiraIssueId('AB-12'), 'Working on issue foo', 61, new SpentDate('2022-10-01')),
                new LogEntry(new JiraIssueId('AB-13'), 'Working on issue bar', 64, new SpentDate('2022-10-02')),
            ],
            ($this->getEntries)(new TimeEntry('123', 10.0, 'AB1-2, AB1-3, hello', new SpentDate('2022-08-09'))),
        );
    }

    public function testWillRejectNon200HttpResponses(): void
    {
        $response = $this->responseFactory->createResponse(201);

        $this->httpClient->expects(self::once())
            ->method('sendRequest')
            ->willReturn($response);

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('Request https://api.tempo.io/4/worklogs?issue=AB1-2&issue=AB1-3&issue=FALLBACK-123&from=2022-08-09&to=2022-08-09&limit=1000  not successful: 201');

        ($this->getEntries)(new TimeEntry('123', 10.0, 'AB1-2, AB1-3, hello', new SpentDate('2022-08-09')));
    }
}
