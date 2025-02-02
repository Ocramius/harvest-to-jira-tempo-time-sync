<?php

declare(strict_types=1);

namespace TimeSync\Jira\Infrastructure;

use Psl\Json;
use Psl\Type;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use SensitiveParameter;
use TimeSync\Jira\Domain\GetIssueIdForKey;
use TimeSync\Jira\Domain\IssueId;
use TimeSync\Jira\Domain\IssueKey;
use TimeSync\Jira\Infrastructure\Exception\IssueNotFound;

use function base64_encode;

final class GetIssueIdFromJiraRestApiV3 implements GetIssueIdForKey
{
    /**
     * @param non-empty-string $user
     * @param non-empty-string $token
     */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $makeRequest,
        #[SensitiveParameter]
        private readonly string $jiraBaseUrl,
        #[SensitiveParameter]
        private readonly string $user,
        #[SensitiveParameter]
        private readonly string $token,
    ) {
    }

    public function __invoke(IssueKey $key): IssueId
    {
        $response = $this->client->sendRequest(
            $this->makeRequest
                ->createRequest('GET', $this->jiraBaseUrl . '/rest/api/3/issue/' . $key->key)
                ->withHeader('Authorization', 'Basic ' . base64_encode($this->user . ':' . $this->token))
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Accept', 'application/json'),
        );

        if ($response->getStatusCode() !== 200) {
            throw new IssueNotFound($key, $response);
        }

        return IssueId::make(
            Json\typed(
                $response->getBody()->__toString(),
                Type\shape([
                    'id' => Type\converted(
                        Type\non_empty_string(),
                        Type\positive_int(),
                        static fn (string $id): int => Type\positive_int()->coerce($id),
                    ),
                ], true),
            )['id'],
        );
    }
}
