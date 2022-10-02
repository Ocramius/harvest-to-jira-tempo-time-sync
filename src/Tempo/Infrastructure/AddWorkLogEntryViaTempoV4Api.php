<?php

declare(strict_types=1);

namespace CrowdfoxTimeSync\Tempo\Infrastructure;

use CrowdfoxTimeSync\Tempo\Domain\LogEntry;
use CrowdfoxTimeSync\Tempo\Domain\SetWorkLogEntry;
use Psl;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

final class AddWorkLogEntryViaTempoV4Api implements SetWorkLogEntry
{
    /**
     * @param non-empty-string $tempoBearerToken
     * @param non-empty-string $authorJiraAccountId in Jira, part of the URL in the "Personal Settings"
     */
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $makeRequest,
        private readonly string $tempoBearerToken,
        private readonly string $authorJiraAccountId,
    ) {
    }

    public function __invoke(LogEntry $logEntry): void
    {
        $request = $this->makeRequest
            ->createRequest('POST', 'https://api.tempo.io/4/worklogs')
            ->withHeader('Authorization', 'Bearer ' . $this->tempoBearerToken)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json');

        $request
            ->getBody()
            ->write(Psl\Json\encode([
                'authorAccountId'  => $this->authorJiraAccountId,
                'description'      => $logEntry->description,
                'issueKey'         => $logEntry->issue->id,
                'startDate'        => $logEntry->date->toString(),
                'timeSpentSeconds' => $logEntry->seconds,
            ]));

        $response = $this->httpClient->sendRequest($request);

        Psl\invariant(
            $response->getStatusCode() === 200,
            'Request '
            . $request->getMethod() . ' ' . $request->getUri()->__toString()
            . '  not successful: ' . $response->getStatusCode(),
        );
    }
}
