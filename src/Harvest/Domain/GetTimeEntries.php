<?php

declare(strict_types=1);

namespace CrowdfoxTimeSync\Harvest\Domain;

interface GetTimeEntries
{
    /**
     * @param non-empty-string $projectId
     *
     * @return iterable<TimeEntry>
     */
    public function __invoke(string $projectId): iterable;
}
