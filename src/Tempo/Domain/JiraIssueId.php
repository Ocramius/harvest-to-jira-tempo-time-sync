<?php

declare(strict_types=1);

namespace TimeSync\Tempo\Domain;

use Psl;
use Psl\Exception\InvariantViolationException;

/** @psalm-immutable */
final class JiraIssueId
{
    /** @throws InvariantViolationException on negative input hours. */
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

    /** @throws InvariantViolationException */
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

    /**
     * Guesses the Jira issue ID by looking at a URL.
     * If the URL doesn't match, we look at the description, searching for
     * anything that may resemble a Jira issue identifier, picking that as
     * our best guess.
     */
    public static function fromSelfUrlOrDescription(string $url, string $description): self|null
    {
        try {
            return self::fromSelfUrl($url);
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

        return new self($match[1]);
    }
}
