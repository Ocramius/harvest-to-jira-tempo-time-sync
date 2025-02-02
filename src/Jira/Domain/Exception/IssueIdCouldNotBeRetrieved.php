<?php

declare(strict_types=1);

namespace TimeSync\Jira\Domain\Exception;

use Throwable;

interface IssueIdCouldNotBeRetrieved extends Throwable
{
    /** @pure */
    public function key(): IssueKey;
}
