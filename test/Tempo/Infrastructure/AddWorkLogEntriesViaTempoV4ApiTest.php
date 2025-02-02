<?php

declare(strict_types=1);

namespace TimeSyncTest\Tempo\Infrastructure;

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
use TimeSync\Jira\Domain\IssueId;
use TimeSync\Jira\Domain\IssueKey;
use TimeSync\Tempo\Domain\JiraIssueId;
use TimeSync\Tempo\Domain\LogEntry;
use TimeSync\Tempo\Infrastructure\AddWorkLogEntryViaTempoV4Api;
use TimeSyncTest\OpenAPI\WrapResponseCallbackInValidationCallback;

#[CoversClass(AddWorkLogEntryViaTempoV4Api::class)]
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
            Psr17FactoryDiscovery::findRequestFactory(),
            'abc123',
            'jiraid123',
            [
                'custom_attribute_1' => 'custom_value_1',
                'custom_attribute_2' => 'custom_value_2',
            ],
        );
    }

    public function testWillAddGivenWorkEntry(): void
    {
        $response = $this->responseFactory->createResponse()
            ->withHeader('Content-Type', 'application/json');

        $response->getBody()->write(<<<'JSON'
{
  "attributes": {
    "self": "https://example.com/this/attributes"
  },
  "author": {
    "accountId": "123456:01234567-89ab-cdef-0123-456789abcdef"
  },
  "billableSeconds": 1,
  "createdAt": "2017-02-06T16:41:41Z",
  "description": "Investigating a problem with our external database system",
  "issue": {
    "id": 112233,
    "self": "https://example.com/this/issue"
  },
  "self": "https://example.com/this",
  "startDate": "2017-02-06",
  "startDateTimeUtc": "2017-02-05T16:06:00Z",
  "startTime": "20:06:00",
  "tempoWorklogId": 126,
  "timeSpentSeconds": 3600,
  "updatedAt": "2017-02-06T16:41:41Z"
}
JSON,);

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
  "issueId": 112233,
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
            ->willReturnCallback(WrapResponseCallbackInValidationCallback::wrap(
                __DIR__ . '/tempo-core.yaml',
                static fn (): ResponseInterface => $response,
            ));

        ($this->addEntry)(new LogEntry(
            new JiraIssueId(IssueId::make(112233), IssueKey::make('AB-12')),
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
        $this->expectExceptionMessage("Request POST https://api.tempo.io/4/worklogs  not successful: 201\nHAHA!");

        ($this->addEntry)(new LogEntry(
            new JiraIssueId(IssueId::make(112233), IssueKey::make('AB-12')),
            'Working on issue foo',
            61,
            new SpentDate('2022-08-09'),
        ));
    }
}
