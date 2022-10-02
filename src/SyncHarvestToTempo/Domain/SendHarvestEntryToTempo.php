<?php

declare(strict_types=1);

namespace CrowdfoxTimeSync\SyncHarvestToTempo\Domain;

use CrowdfoxTimeSync\Harvest\Domain\TimeEntry;
use CrowdfoxTimeSync\Tempo\Domain\GetWorkLogEntries;
use CrowdfoxTimeSync\Tempo\Domain\JiraIssueId;
use CrowdfoxTimeSync\Tempo\Domain\LogEntry;
use CrowdfoxTimeSync\Tempo\Domain\SetWorkLogEntry;
use Psl;

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
                    $entry->appliesToSameIssueAndDay(...),
                )
            ),
            $this->setWorkLogEntry->__invoke(...),
        );
    }
}
