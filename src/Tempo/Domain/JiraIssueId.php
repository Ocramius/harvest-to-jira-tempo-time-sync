<?php

declare(strict_types=1);

namespace CrowdfoxTimeSync\Tempo\Domain;

use Psl;
use Psl\Exception\InvariantViolationException;

/** @psalm-immutable */
final class JiraIssueId
{
    /**
     * @param non-empty-string $notes
     *
     * @throws InvariantViolationException on negative input hours.
     */
    public function __construct(
        public readonly string $id
    ) {
        Psl\invariant(Psl\Regex\matches($id, '/[A-Z][A-Z0-9]*-[0-9]+/'), 'Invalid Jira issue ID: ' . $id);
    }
}
