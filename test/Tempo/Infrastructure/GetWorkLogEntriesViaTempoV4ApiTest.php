<?php

declare(strict_types=1);

namespace TimeSyncTest\Tempo\Infrastructure;

use Http\Discovery\Psr17FactoryDiscovery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psl\Exception\InvariantViolationException;
use Psl\Type;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use TimeSync\Harvest\Domain\SpentDate;
use TimeSync\Harvest\Domain\TimeEntry;
use TimeSync\Jira\Domain\GetIssueIdForKey;
use TimeSync\Jira\Domain\IssueId;
use TimeSync\Jira\Domain\IssueKey;
use TimeSync\Tempo\Domain\JiraIssueId;
use TimeSync\Tempo\Domain\LogEntry;
use TimeSync\Tempo\Infrastructure\GetWorkLogEntriesViaTempoV4Api;
use TimeSyncTest\OpenAPI\WrapResponseCallbackInValidationCallback;

use function base_convert;
use function sha1;
use function substr;

#[CoversClass(GetWorkLogEntriesViaTempoV4Api::class)]
final class GetWorkLogEntriesViaTempoV4ApiTest extends TestCase
{
    /** @var ClientInterface&MockObject */
    private ClientInterface $httpClient;
    private ResponseFactoryInterface $responseFactory;
    private GetWorkLogEntriesViaTempoV4Api $getEntries;
    private GetIssueIdForKey&Stub $getId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->getId           = $this->createStub(GetIssueIdForKey::class);
        $this->httpClient      = $this->createMock(ClientInterface::class);
        $this->responseFactory = Psr17FactoryDiscovery::findResponseFactory();
        $this->getEntries      = new GetWorkLogEntriesViaTempoV4Api(
            $this->getId,
            $this->httpClient,
            Psr17FactoryDiscovery::findRequestFactory(),
            'abc123',
            self::id('FALLBACK-123'),
        );

        $this->getId->method('__invoke')
            ->willReturnCallback(static function (IssueKey $key): IssueId {
                return self::id($key->key)->id;
            });
    }

    public function testWillFetchLogEntriesForGivenIssues(): void
    {
        $response = $this->responseFactory->createResponse()
            ->withAddedHeader('Content-Type', 'application/json');

        $response->getBody()
            ->write(
                <<<'JSON'
{
  "self": "https://example.com/page",
  "metadata": {
    "count": 1,
    "limit": 50,
    "offset": 0,
    "next": "https://example.com/page/next",
    "previous": "https://example.com/page/previous"
  },
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
      "description": "Working on issue foo harvest:11111",
      "createdAt": "2017-02-06T16:41:41Z",
      "startDateTimeUtc": "2017-02-05T16:06:00Z",
      "self": "https://example.com/this",
      "updatedAt": "2017-02-06T16:41:41Z",
      "attributes": {
        "self": "https://example.com/this/attributes"
      },
      "author": {
        "accountId": "123456:01234567-89ab-cdef-0123-456789abcdef"
      }
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
      "description": "Working on issue bar harvest:11111",
      "createdAt": "2017-02-06T16:41:41Z",
      "startDateTimeUtc": "2017-02-05T16:06:00Z",
      "self": "https://example.com/this",
      "updatedAt": "2017-02-06T16:41:41Z",
      "attributes": {
        "self": "https://example.com/this/attributes"
      },
      "author": {
        "accountId": "123456:01234567-89ab-cdef-0123-456789abcdef"
      }
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
      "description": "This log will be ignored, because it doesn't match the input time entry harvest:12345",
      "createdAt": "2017-02-06T16:41:41Z",
      "startDateTimeUtc": "2017-02-05T16:06:00Z",
      "self": "https://example.com/this",
      "updatedAt": "2017-02-06T16:41:41Z",
      "attributes": {
        "self": "https://example.com/this/attributes"
      },
      "author": {
        "accountId": "123456:01234567-89ab-cdef-0123-456789abcdef"
      }
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
                    'https://api.tempo.io/4/worklogs?issueId=285638&issueId=413713&issueId=265303&from=2022-08-09&to=2022-08-09&limit=1000',
                    $request->getUri()->__toString(),
                );
                self::assertSame(
                    [
                        'Host'          => ['api.tempo.io'],
                        'Authorization' => ['Bearer abc123'],
                    ],
                    $request->getHeaders(),
                );

                return true;
            }))
            ->willReturnCallback(WrapResponseCallbackInValidationCallback::wrap(
                __DIR__ . '/tempo-core.yaml',
                static fn (): ResponseInterface => $response,
            ));

        self::assertEquals(
            [
                new LogEntry(self::id('AB-12'), 'Working on issue foo harvest:11111', 61, new SpentDate('2022-08-09')),
                new LogEntry(self::id('AB-13'), 'Working on issue bar harvest:11111', 64, new SpentDate('2022-08-09')),
            ],
            ($this->getEntries)(new TimeEntry('11111', 10.0, 'AB1-2, AB1-3, hello', new SpentDate('2022-08-09'))),
        );
    }

    public function testWillFetchLogEntriesForGivenIssuesEvenIfIssueSelfUrlIsNotContainingTheIssueId(): void
    {
        $response = $this->responseFactory->createResponse()
            ->withAddedHeader('Content-Type', 'application/json');

        $response->getBody()
            ->write(
                <<<'JSON'
{
  "self": "https://example.com/page",
  "metadata": {
    "count": 1,
    "limit": 50,
    "offset": 0,
    "next": "https://example.com/page/next",
    "previous": "https://example.com/page/previous"
  },
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
      "description": "Working on issue AB-12 harvest:11111",
      "createdAt": "2017-02-06T16:41:41Z",
      "startDateTimeUtc": "2017-02-05T16:06:00Z",
      "self": "https://example.com/this",
      "updatedAt": "2017-02-06T16:41:41Z",
      "attributes": {
        "self": "https://example.com/this/attributes"
      },
      "author": {
        "accountId": "123456:01234567-89ab-cdef-0123-456789abcdef"
      }
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
      "description": "Working on issue AB-13 harvest:11111",
      "createdAt": "2017-02-06T16:41:41Z",
      "startDateTimeUtc": "2017-02-05T16:06:00Z",
      "self": "https://example.com/this",
      "updatedAt": "2017-02-06T16:41:41Z",
      "attributes": {
        "self": "https://example.com/this/attributes"
      },
      "author": {
        "accountId": "123456:01234567-89ab-cdef-0123-456789abcdef"
      }
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
      "description": "This log will be ignored, because it doesn't match the input time entry harvest:12345",
      "createdAt": "2017-02-06T16:41:41Z",
      "startDateTimeUtc": "2017-02-05T16:06:00Z",
      "self": "https://example.com/this",
      "updatedAt": "2017-02-06T16:41:41Z",
      "attributes": {
        "self": "https://example.com/this/attributes"
      },
      "author": {
        "accountId": "123456:01234567-89ab-cdef-0123-456789abcdef"
      }
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
      "description": "This log will be ignored, because it doesn't have an associated harvest id",
      "createdAt": "2017-02-06T16:41:41Z",
      "startDateTimeUtc": "2017-02-05T16:06:00Z",
      "self": "https://example.com/this",
      "updatedAt": "2017-02-06T16:41:41Z",
      "attributes": {
        "self": "https://example.com/this/attributes"
      },
      "author": {
        "accountId": "123456:01234567-89ab-cdef-0123-456789abcdef"
      }
    }
  ]
}
JSON,
            );

        $this->httpClient->expects(self::once())
            ->method('sendRequest')
            ->willReturnCallback(WrapResponseCallbackInValidationCallback::wrap(
                __DIR__ . '/tempo-core.yaml',
                static fn (): ResponseInterface => $response,
            ));

        self::assertEquals(
            [
                new LogEntry(self::id('AB-12'), 'Working on issue AB-12 harvest:11111', 61, new SpentDate('2022-08-09')),
                new LogEntry(self::id('AB-13'), 'Working on issue AB-13 harvest:11111', 64, new SpentDate('2022-08-09')),
            ],
            ($this->getEntries)(new TimeEntry('11111', 10.0, 'AB1-2, AB1-3, hello', new SpentDate('2022-08-09'))),
        );
    }

    public function testWillRejectNon200HttpResponses(): void
    {
        $response = $this->responseFactory->createResponse(201)
            ->withAddedHeader('Content-Type', 'application/json');

        $response->getBody()
            ->write('HEHE!');

        $this->httpClient->expects(self::once())
            ->method('sendRequest')
            ->willReturn($response);

        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage("Request https://api.tempo.io/4/worklogs?issueId=285638&issueId=413713&issueId=265303&from=2022-08-09&to=2022-08-09&limit=1000  not successful: 201\nHEHE!");

        ($this->getEntries)(new TimeEntry('123', 10.0, 'AB1-2, AB1-3, hello', new SpentDate('2022-08-09')));
    }

    /**
     * Tempo started rejecting `/core/3/worklogs?issueId=id&issueId=id` queries when
     * the `id` is the same: that kind of query now leads to a 404 error.
     *
     * In order to avoid this problem, we de-duplicate any queried issue IDs before
     * adding them to the outgoing query string.
     */
    #[Group('#39')]
    public function testWillDeDuplicateJiraIssueIdsBeforeQuerying(): void
    {
        $response = $this->responseFactory->createResponse()
            ->withAddedHeader('Content-Type', 'application/json');

        $response->getBody()
            ->write(
                <<<'JSON'
{
  "self": "https://example.com/page",
  "metadata": {
    "count": 1,
    "limit": 50,
    "offset": 0,
    "next": "https://example.com/page/next",
    "previous": "https://example.com/page/previous"
  },
  "results": []
}
JSON,
            );

        $this->httpClient->expects(self::once())
            ->method('sendRequest')
            ->with(self::callback(static function (RequestInterface $request): bool {
                self::assertSame('GET', $request->getMethod());
                self::assertSame(
                    'https://api.tempo.io/4/worklogs?issueId=285638&issueId=413713&issueId=265303&from=2022-08-09&to=2022-08-09&limit=1000',
                    $request->getUri()->__toString(),
                );
                self::assertSame(
                    [
                        'Host'          => ['api.tempo.io'],
                        'Authorization' => ['Bearer abc123'],
                    ],
                    $request->getHeaders(),
                );

                return true;
            }))
            ->willReturnCallback(WrapResponseCallbackInValidationCallback::wrap(
                __DIR__ . '/tempo-core.yaml',
                static fn (): ResponseInterface => $response,
            ));

        self::assertEmpty(
            ($this->getEntries)(new TimeEntry(
                '11111',
                10.0,
                'AB1-2, AB1-2, AB1-3, AB1-2, AB1-3, hello, hi',
                new SpentDate('2022-08-09'),
            )),
        );
    }

    /** @param non-empty-string $key */
    private static function id(string $key): JiraIssueId
    {
        return new JiraIssueId(
            IssueId::make(
                Type\positive_int()
                    ->coerce(base_convert(substr(sha1($key), 0, 5), 16, 10)),
            ),
            IssueKey::make($key),
        );
    }
}
