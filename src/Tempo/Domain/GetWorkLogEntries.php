<?php

declare(strict_types=1);

namespace TimeSync\Tempo\Domain;

use TimeSync\Harvest\Domain\TimeEntry;

interface GetWorkLogEntries
{
    /** @return list<LogEntry> log entries related to the given {@see TimeEntry} */
    public function __invoke(TimeEntry $timeEntry): array;
}
