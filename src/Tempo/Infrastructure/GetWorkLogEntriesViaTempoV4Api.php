<?php

declare(strict_types=1);

namespace TimeSync\Tempo\Infrastructure;

use Psl;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use TimeSync\Harvest\Domain\SpentDate;
use TimeSync\Harvest\Domain\TimeEntry;
use TimeSync\Jira\Domain\GetIssueIdForKey;
use TimeSync\Tempo\Domain\GetWorkLogEntries;
use TimeSync\Tempo\Domain\JiraIssueId;
use TimeSync\Tempo\Domain\LogEntry;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function implode;

/** @link https://apidocs.tempo.io/v4/#section/API-conventions */
final readonly class GetWorkLogEntriesViaTempoV4Api implements GetWorkLogEntries
{
    /** @param non-empty-string $tempoBearerToken */
    public function __construct(
        private GetIssueIdForKey $getId,
        private ClientInterface $httpClient,
        private RequestFactoryInterface $makeRequest,
        private string $tempoBearerToken,
        private JiraIssueId $fallbackJiraIssue,
    ) {
    }

    /** {@inheritDoc} */
    public function __invoke(TimeEntry $timeEntry): array
    {
        /**
         * Tempo uses the same repeated parameter to indicate an array of parameters,
         * so we cannot use {@see \http_build_query()}
         */
        $query = implode(
            '&',
            array_unique(array_map(
                static fn (LogEntry $entry): string => 'issueId=' . $entry->issue->id->id,
                LogEntry::splitTimeEntry($this->getId, $timeEntry, $this->fallbackJiraIssue),
            )),
        )
            . '&from=' . $timeEntry->spent_date->toString()
            . '&to=' . $timeEntry->spent_date->toString()
            . '&limit=1000';

        $request = $this->makeRequest
            ->createRequest('GET', 'https://api.tempo.io/4/worklogs');

        $request = $request
            ->withUri(
                $request
                    ->getUri()
                    ->withQuery($query),
            )
            ->withHeader('Authorization', 'Bearer ' . $this->tempoBearerToken);

        $response = $this->httpClient->sendRequest($request);

        Psl\invariant(
            $response->getStatusCode() === 200,
            'Request ' . $request->getUri()->__toString()
            . '  not successful: ' . $response->getStatusCode()
            . "\n" . $response->getBody()->__toString(),
        );

        $logEntries = array_filter(array_map(
            function (array $row): LogEntry|null {
                $issueId = JiraIssueId::fromSelfUrlOrDescription($this->getId, $row['issue']['self'], $row['description']);

                if ($issueId === null) {
                    return null;
                }

                return new LogEntry(
                    $issueId,
                    $row['description'],
                    $row['timeSpentSeconds'],
                    new SpentDate($row['startDate']),
                );
            },
            Psl\Json\typed(
                $response
                    ->getBody()
                    ->__toString(),
                Psl\Type\shape([
                    'results' => Psl\Type\vec(Psl\Type\shape([
                        'issue'            => Psl\Type\shape([
                            'self' => Psl\Type\non_empty_string(),
                        ]),
                        'timeSpentSeconds' => Psl\Type\positive_int(),
                        'description'      => Psl\Type\string(),
                        'startDate'        => Psl\Type\non_empty_string(),
                    ])),
                ]),
            )['results'],
        ));

        return array_values(array_filter(
            $logEntries,
            static fn (LogEntry $logEntry): bool => $logEntry->matchesTimeEntry($timeEntry),
        ));
    }
}
