<?php

declare(strict_types=1);

namespace TimeSync\Tempo\Domain;

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
        public readonly string $id,
    ) {
        Psl\invariant(
            Psl\Regex\matches(
                $id,
                '/^[A-Z][A-Z0-9]*-[0-9]+$/',
            ),
            'Invalid Jira issue ID: "' . $id . '"',
        );
    }

    public static function fromSelfUrl(string $url): self
    {
        $match = Psl\Regex\first_match(
            $url,
            '/\/([A-Z][A-Z0-9]*-[0-9]+)$/',
            Psl\Type\shape([
                1 => Psl\Type\non_empty_string(),
            ]),
        );

        Psl\invariant($match !== null, 'Url "' . $url . '" does not contain a Jira issue ID');

        return new self($match[1]);
    }
}
