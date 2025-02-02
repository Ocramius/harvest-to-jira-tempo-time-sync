<?php

declare(strict_types=1);

namespace TimeSync\Tempo\Domain;

use TimeSync\Jira\Domain\IssueId;
use TimeSync\Jira\Domain\IssueKey;

/** @psalm-immutable */
final readonly class JiraIssue
{
    private function __construct(
        public IssueKey $key,
        public IssueId $id,
    ) {
    }
}
