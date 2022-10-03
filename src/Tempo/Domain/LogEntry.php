<?php

declare(strict_types=1);

namespace TimeSync\Tempo\Domain;

use Psl;
use TimeSync\Harvest\Domain\SpentDate;
use TimeSync\Harvest\Domain\TimeEntry;

use function array_keys;
use function array_map;
use function assert;
use function count;
use function explode;
use function preg_quote;
use function trim;

/** @psalm-immutable */
final class LogEntry
{
    public function __construct(
        public readonly JiraIssueId $issue,
        public readonly string $description,
        public readonly int $seconds,
        public readonly SpentDate $date,
    ) {
    }

    /** @return non-empty-list<self> */
    public static function splitTimeEntry(TimeEntry $entry, JiraIssueId $fallbackIssue): array
    {
        /** @var array<string, JiraIssueId> $issues */
        $issues = [];

        foreach (explode(',', $entry->notes) as $description) {
            $matches = Psl\Regex\every_match(
                $description,
                '/([A-Z][A-Z0-9]*-[0-9]+)/',
                Psl\Type\shape([
                    1 => Psl\Type\non_empty_string(),
                ]),
            );

            if ($matches === null) {
                $issues[trim($description . ' ' . $fallbackIssue->id)] = $fallbackIssue;

                continue;
            }

            foreach ($matches as $match) {
                $issues[trim($description)] = new JiraIssueId($match[1]);
            }
        }

        $timeSplit = (int) ($entry->hours * 3600 / count($issues));

        $entries = array_map(
            static fn (JiraIssueId $issue, string $description): self => new LogEntry(
                $issue,
                $description . ' harvest:' . $entry->id,
                $timeSplit,
                $entry->spent_date,
            ),
            $issues,
            array_keys($issues),
        );

        assert($entries !== []);

        return $entries;
    }

    public function matchesTimeEntry(TimeEntry $entry): bool
    {
        return $entry->spent_date->equals($this->date)
            && Psl\Regex\matches($this->description, '#harvest:' . preg_quote($entry->id, '#') . '$#');
    }

    public function appliesToSameIssueAndDay(self $other): bool
    {
        return $this->date->equals($other->date)
            && $this->issue->id === $other->issue->id;
    }
}
