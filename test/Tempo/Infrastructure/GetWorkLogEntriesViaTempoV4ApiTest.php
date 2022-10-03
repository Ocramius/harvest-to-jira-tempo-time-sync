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
use TimeSync\Harvest\Domain\TimeEntry;
use TimeSync\Tempo\Domain\JiraIssueId;
use TimeSync\Tempo\Domain\LogEntry;
use TimeSync\Tempo\Infrastructure\GetWorkLogEntriesViaTempoV4Api;

/** @covers \TimeSync\Tempo\Infrastructure\GetWorkLogEntriesViaTempoV4Api */
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
            Psr17FactoryDiscovery::findRequestFactory(),
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
      "startDate": "2022-08-09",
      "startTime": "00:00:00",
      "description": "Working on issue foo harvest:11111"
    },
    {
      "tempoWorklogId": 456,
      "issue": {
        "self": "https://foo.atlassian.net/rest/api/2/issue/AB-13",
        "id": 456
      },
      "timeSpentSeconds": 64,
      "billableSeconds": 63,
      "startDate": "2022-08-09",
      "startTime": "00:00:00",
      "description": "Working on issue bar harvest:11111"
    },
    {
      "tempoWorklogId": 456,
      "issue": {
        "self": "https://foo.atlassian.net/rest/api/2/issue/AB-13",
        "id": 456
      },
      "timeSpentSeconds": 64,
      "billableSeconds": 63,
      "startDate": "2022-08-09",
      "startTime": "00:00:00",
      "description": "This log will be ignored, because it doesn't match the input time entry harvest:12345"
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
                new LogEntry(new JiraIssueId('AB-12'), 'Working on issue foo harvest:11111', 61, new SpentDate('2022-08-09')),
                new LogEntry(new JiraIssueId('AB-13'), 'Working on issue bar harvest:11111', 64, new SpentDate('2022-08-09')),
            ],
            ($this->getEntries)(new TimeEntry('11111', 10.0, 'AB1-2, AB1-3, hello', new SpentDate('2022-08-09'))),
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
