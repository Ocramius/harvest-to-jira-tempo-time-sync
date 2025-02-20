<?php

declare(strict_types=1);

namespace TimeSync\Tempo\Infrastructure;

use Psl;
use Psl\Dict;
use Psl\Json;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use TimeSync\Tempo\Domain\LogEntry;
use TimeSync\Tempo\Domain\SetWorkLogEntry;

use function array_values;

/** @link https://apidocs.tempo.io/#worklogs */
final readonly class AddWorkLogEntryViaTempoV4Api implements SetWorkLogEntry
{
    /**
     * @param non-empty-string                $tempoBearerToken
     * @param non-empty-string                $authorJiraAccountId     in Jira, part of the URL in the "Personal Settings"
     * @param array<non-empty-string, string> $customAttributesToBeSet
     */
    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $makeRequest,
        private string $tempoBearerToken,
        private string $authorJiraAccountId,
        private array $customAttributesToBeSet,
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
            ->write(Json\encode([
                'authorAccountId'  => $this->authorJiraAccountId,
                'description'      => $logEntry->description,
                'issueId'          => $logEntry->issue->id->id,
                'startDate'        => $logEntry->date->toString(),
                'timeSpentSeconds' => $logEntry->seconds,
                'attributes'       => array_values(Dict\map_with_key(
                    $this->customAttributesToBeSet,
                    static function (string $key, string $value): array {
                        return [
                            'key' => $key,
                            'value' => $value,
                        ];
                    },
                )),
            ]));

        $response = $this->httpClient->sendRequest($request);

        Psl\invariant(
            $response->getStatusCode() === 200,
            'Request '
            . $request->getMethod() . ' ' . $request->getUri()->__toString()
            . '  not successful: ' . $response->getStatusCode()
            . "\n" . $response->getBody()->__toString(),
        );
    }
}
