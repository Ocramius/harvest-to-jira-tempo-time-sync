<?php

declare(strict_types=1);

namespace TimeSync\Harvest\Infrastructure;

use CuyZ\Valinor\Mapper\TreeMapper;
use Psl;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use TimeSync\Harvest\Domain\GetTimeEntries;
use TimeSync\Harvest\Domain\TimeEntry;

use function array_map;
use function array_merge;
use function http_build_query;

/**
 * @link https://help.getharvest.com/api-v2/timesheets-api/timesheets/time-entries/
 *
 * @psalm-type PageResponse = array{
 *     time_entries: list<non-empty-array<non-empty-string, mixed>>,
 *     links: array{
 *         next: non-empty-string|null
 *     }
 * }
 */
final class GetTimeEntriesFromV2Api implements GetTimeEntries
{
    /**
     * @param non-empty-string $harvestAccountId
     * @param non-empty-string $harvestBearerToken
     */
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly UriFactoryInterface $uriFactory,
        private readonly RequestFactoryInterface $makeRequest,
        private readonly TreeMapper $mapper,
        private readonly string $harvestAccountId,
        private readonly string $harvestBearerToken,
    ) {
    }

    /** {@inheritDoc} */
    public function __invoke(string $projectId): iterable
    {
        $nextPage = null;

        $responses = [];

        do {
            $pageResponse = $this->fetchPage($nextPage, $projectId);

            $responses[] = $pageResponse;

            $nextPage = $this->nextPage($pageResponse);
        } while ($nextPage);

        return array_merge(
            [],
            ...array_map(
                $this->pageResults(...),
                $responses,
            ),
        );
    }

    /**
     * @param PageResponse $response
     *
     * @return list<TimeEntry>
     */
    private function pageResults(array $response): array
    {
        return array_map(
            fn (array $entry): TimeEntry => $this->mapper->map(TimeEntry::class, $entry),
            $response['time_entries'],
        );
    }

    /**
     * @param non-empty-string $projectId
     *
     * @return PageResponse
     */
    private function fetchPage(UriInterface|null $uri, string $projectId): array
    {
        $request = $this->makeRequest
            ->createRequest(
                'GET',
                $uri
                ?? $this->uriFactory->createUri('https://api.harvestapp.com/v2/time_entries')
                ->withQuery(http_build_query(['project_id' => $projectId])),
            )
            ->withAddedHeader('Harvest-Account-Id', $this->harvestAccountId)
            ->withAddedHeader('Authorization', 'Bearer ' . $this->harvestBearerToken)
            ->withAddedHeader('User-Agent', self::class);

        $response = $this->httpClient->sendRequest($request);

        Psl\invariant(
            $response->getStatusCode() === 200,
            'Request ' . $request->getUri()->__toString() . '  not successful: ' . $response->getStatusCode(),
        );

        return Psl\Json\typed(
            $response
                ->getBody()
                ->__toString(),
            Psl\Type\shape([
                'time_entries' => Psl\Type\vec(Psl\Type\non_empty_dict(
                    Psl\Type\non_empty_string(),
                    Psl\Type\mixed(),
                )),
                'links'        => Psl\Type\shape([
                    'next' => Psl\Type\union(
                        Psl\Type\null(),
                        Psl\Type\non_empty_string(),
                    ),
                ]),
            ]),
        );
    }

    /** @param PageResponse $pageResponse */
    private function nextPage(array $pageResponse): UriInterface|null
    {
        $nextPage = $pageResponse['links']['next'];

        if ($nextPage === null) {
            return null;
        }

        return $this->uriFactory->createUri($nextPage);
    }
}
