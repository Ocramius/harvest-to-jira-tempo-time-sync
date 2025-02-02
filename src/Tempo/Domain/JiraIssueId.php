<?php

declare(strict_types=1);

namespace TimeSync\Tempo\Domain;

use Psl;
use Psl\Exception\InvariantViolationException;
use TimeSync\Jira\Domain\GetIssueIdForKey;
use TimeSync\Jira\Domain\IssueId;
use TimeSync\Jira\Domain\IssueKey;

/** @psalm-immutable */
final readonly class JiraIssueId
{
    /** @throws InvariantViolationException on negative input hours. */
    public function __construct(
        public IssueId $id,
        public IssueKey $key,
    ) {
    }

    /** @throws InvariantViolationException */
    public static function fromSelfUrl(GetIssueIdForKey $getId, string $url): self
    {
        $match = Psl\Regex\first_match(
            $url,
            '/\/([A-Z][A-Z0-9]*-[0-9]+)$/',
            Psl\Type\shape([
                1 => Psl\Type\non_empty_string(),
            ]),
        );

        Psl\invariant($match !== null, 'Url "' . $url . '" does not contain a Jira issue ID');

        $key = IssueKey::make($match[1]);

        return new self($getId($key), $key);
    }

    /**
     * Guesses the Jira issue ID by looking at a URL.
     * If the URL doesn't match, we look at the description, searching for
     * anything that may resemble a Jira issue identifier, picking that as
     * our best guess.
     */
    public static function fromSelfUrlOrDescription(GetIssueIdForKey $getId, string $url, string $description): self|null
    {
        try {
            return self::fromSelfUrl($getId, $url);
        } catch (InvariantViolationException) {
        }

        $match = Psl\Regex\first_match(
            $description,
            '/([A-Z][A-Z0-9]*-[0-9]+)/',
            Psl\Type\shape([
                1 => Psl\Type\non_empty_string(),
            ]),
        );

        if ($match === null) {
            return null;
        }

        $key = IssueKey::make($match[1]);

        return new self($getId($key), $key);
    }
}
