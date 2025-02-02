<?php

declare(strict_types=1);

namespace TimeSync\Jira\Domain;

interface GetIssueIdForKey
{
    /** @throws IssueIdCouldNotBeRetrieved */
    public function __invoke(IssueKey $key): IssueId;
}
