<?php

declare(strict_types=1);

namespace TimeSync\Jira\Infrastructure\Exception;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use TimeSync\Jira\Domain\Exception\IssueIdCouldNotBeRetrieved;
use TimeSync\Jira\Domain\IssueKey;

use function sprintf;

final class IssueNotFound extends RuntimeException implements IssueIdCouldNotBeRetrieved
{
    public function __construct(private readonly IssueKey $key, ResponseInterface $response)
    {
        parent::__construct(sprintf(
            "Could not retrieve issue ID for key \"%s\"\n\nServer responded with code %d and message:\n\n%s",
            $key->key,
            $response->getStatusCode(),
            $response->getBody()->__toString(),
        ));
    }

    public function key(): IssueKey
    {
        return $this->key;
    }
}
