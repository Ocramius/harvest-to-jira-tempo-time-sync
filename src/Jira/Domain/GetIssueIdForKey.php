<?php

declare(strict_types=1);

namespace TimeSync\Jira\Domain;

use TimeSync\Jira\Domain\Exception\IssueIdCouldNotBeRetrieved;

interface GetIssueIdForKey
{
    /** @throws IssueIdCouldNotBeRetrieved */
    public function __invoke(IssueKey $key): IssueId;
}
