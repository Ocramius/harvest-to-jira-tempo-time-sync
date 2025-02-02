<?php

declare(strict_types=1);

namespace TimeSync\Jira\Domain;

use Psl;
use Psl\Exception\InvariantViolationException;
use Psl\Regex;
use function sprintf;

/** @psalm-immutable */
final readonly class IssueKey
{
    /** @param non-empty-string $key */
    private function __construct(
        public string $key,
    ) {
    }

    /**
     * @pure 
     * @param non-empty-string $key 
     * @throws InvariantViolationException
     */
    public static function make(string $key): self
    {
        Psl\invariant(
            Regex\matches($key, '/^[A-Z0-9]+-[1-9]+\d*$/'),
            sprintf('Issue "%s" does not look like a jira ID', $key),
        );
        
        return new self($key);
    }
}
