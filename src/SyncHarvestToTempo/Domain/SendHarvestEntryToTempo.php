<?php

declare(strict_types=1);

namespace TimeSync\SyncHarvestToTempo\Domain;

use Psl;
use TimeSync\Harvest\Domain\TimeEntry;
use TimeSync\Tempo\Domain\GetWorkLogEntries;
use TimeSync\Tempo\Domain\JiraIssueId;
use TimeSync\Tempo\Domain\LogEntry;
use TimeSync\Tempo\Domain\SetWorkLogEntry;

use function array_filter;

final class SendHarvestEntryToTempo
{
    public function __construct(
        private readonly JiraIssueId $fallbackJiraIssue,
        private readonly GetWorkLogEntries $getWorkLogEntries,
        private readonly SetWorkLogEntry $setWorkLogEntry,
    ) {
    }

    public function __invoke(TimeEntry $time): void
    {
        $existingEntries = ($this->getWorkLogEntries)($time);

        Psl\Iter\apply(
            array_filter(
                LogEntry::splitTimeEntry($time, $this->fallbackJiraIssue),
                static fn (LogEntry $entry): bool => ! Psl\Iter\any(
                    $existingEntries,
                    // Note: following should be `$entry->appliesToSameIssueAndDay(...)`, but psalm can't follow
                    fn (LogEntry $other): bool => $entry->appliesToSameIssueAndDay($other),
                )
            ),
            $this->setWorkLogEntry->__invoke(...),
        );
    }
}
