<?php

declare(strict_types=1);

namespace CrowdfoxTimeSync\Tempo\Domain;

use CrowdfoxTimeSync\Harvest\Domain\TimeEntry;

interface GetWorkLogEntries
{
    /** @return list<LogEntry> log entries related to the given {@see TimeEntry} */
    public function __invoke(TimeEntry $timeEntry): array;
}
