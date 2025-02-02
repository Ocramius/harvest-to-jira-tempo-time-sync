<?php

declare(strict_types=1);

namespace TimeSync\Jira\Domain;

/** @psalm-immutable */
final readonly class IssueId
{
    /** @param int<1, max> $id */
    private function __construct(
        public int $id,
    ) {
    }
}
