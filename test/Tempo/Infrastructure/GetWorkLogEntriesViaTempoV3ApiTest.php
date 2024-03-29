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
use TimeSync\Tempo\Infrastructure\GetWorkLogEntriesViaTempoV3Api;

/** @covers \TimeSync\Tempo\Infrastructure\GetWorkLogEntriesViaTempoV3Api */
final class GetWorkLogEntriesViaTempoV3ApiTest extends TestCase
{
    /** @var ClientInterface&MockObject */
    private ClientInterface $httpClient;
    private ResponseFactoryInterface $responseFactory;
    private GetWorkLogEntriesViaTempoV3Api $getEntries;

    protected function setUp(): void
    {
        parent::setUp();

        $this->httpClient      = $this->createMock(ClientInterface::class);
        $this->responseFactory = Psr17FactoryDiscovery::findResponseFactory();
        $this->getEntries      = new GetWorkLogEntriesViaTempoV3Api(
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
                    'https://api.tempo.io/core/3/worklogs?issue=AB1-2&issue=AB1-3&issue=FALLBACK-123&from=2022-08-09&to=2022-08-09&limit=1000',
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

    public function testWillFetchLogEntriesForGivenIssuesEvenIfIssueSelfUrlIsNotContainingTheIssueId(): void
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
        "self": "https://foo.atlassian.net/rest/api/2/issue/12345",
        "id": 12345
      },
      "timeSpentSeconds": 61,
      "billableSeconds": 60,
      "startDate": "2022-08-09",
      "startTime": "00:00:00",
      "description": "Working on issue AB-12 harvest:11111"
    },
    {
      "tempoWorklogId": 456,
      "issue": {
        "self": "https://foo.atlassian.net/rest/api/2/issue/12346",
        "id": 12346
      },
      "timeSpentSeconds": 64,
      "billableSeconds": 63,
      "startDate": "2022-08-09",
      "startTime": "00:00:00",
      "description": "Working on issue AB-13 harvest:11111"
    },
    {
      "tempoWorklogId": 789,
      "issue": {
        "self": "https://foo.atlassian.net/rest/api/2/issue/12347",
        "id": 12347
      },
      "timeSpentSeconds": 64,
      "billableSeconds": 63,
      "startDate": "2022-08-09",
      "startTime": "00:00:00",
      "description": "This log will be ignored, because it doesn't match the input time entry harvest:12345"
    },
    {
      "tempoWorklogId": 101112,
      "issue": {
        "self": "https://foo.atlassian.net/rest/api/2/issue/12348",
        "id": 12348
      },
      "timeSpentSeconds": 64,
      "billableSeconds": 63,
      "startDate": "2022-08-09",
      "startTime": "00:00:00",
      "description": "This log will be ignored, because it doesn't have an associated harvest id"
    }
  ]
}
JSON,
            );

        $this->httpClient->expects(self::once())
            ->method('sendRequest')
            ->willReturn($response);

        self::assertEquals(
            [
                new LogEntry(new JiraIssueId('AB-12'), 'Working on issue AB-12 harvest:11111', 61, new SpentDate('2022-08-09')),
                new LogEntry(new JiraIssueId('AB-13'), 'Working on issue AB-13 harvest:11111', 64, new SpentDate('2022-08-09')),
            ],
            ($this->getEntries)(new TimeEntry('11111', 10.0, 'AB1-2, AB1-3, hello', new SpentDate('2022-08-09'))),
        );
    }

    public function testWillRejectNon200HttpResponses(): void
    {
        $response = $this->responseFactory->createResponse(201);

        $response->getBody()
            ->write('HEHE!');

        $this->httpClient->expects(self::once())
            ->method('sendRequest')
            ->willReturn($response);

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage("Request https://api.tempo.io/core/3/worklogs?issue=AB1-2&issue=AB1-3&issue=FALLBACK-123&from=2022-08-09&to=2022-08-09&limit=1000  not successful: 201\nHEHE!");

        ($this->getEntries)(new TimeEntry('123', 10.0, 'AB1-2, AB1-3, hello', new SpentDate('2022-08-09')));
    }

    /**
     * @group #39
     *
     * Tempo started rejecting `/core/3/worklogs?issue=id&issue=id` queries when
     * the `id` is the same: that kind of query now leads to a 404 error.
     *
     * In order to avoid this problem, we de-duplicate any queried issue IDs before
     * adding them to the outgoing query string.
     */
    public function testWillDeDuplicateJiraIssueIdsBeforeQuerying(): void
    {
        $response = $this->responseFactory->createResponse();

        $response->getBody()
            ->write(
                <<<'JSON'
{
  "results": []
}
JSON,
            );

        $this->httpClient->expects(self::once())
            ->method('sendRequest')
            ->with(self::callback(static function (RequestInterface $request): bool {
                self::assertSame('GET', $request->getMethod());
                self::assertSame(
                    'https://api.tempo.io/core/3/worklogs?issue=AB1-2&issue=AB1-3&issue=FALLBACK-123&from=2022-08-09&to=2022-08-09&limit=1000',
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

        self::assertEmpty(
            ($this->getEntries)(new TimeEntry(
                '11111',
                10.0,
                'AB1-2, AB1-2, AB1-3, AB1-2, AB1-3, hello, hi',
                new SpentDate('2022-08-09'),
            )),
        );
    }
}
