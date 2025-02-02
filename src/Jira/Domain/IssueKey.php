<?php

declare(strict_types=1);

namespace TimeSync\Jira\Domain;

/** @psalm-immutable */
final readonly class IssueKey
{
    /** @param non-empty-string $key */
    private function __construct(
        public string $key,
    ) {
    }
}
