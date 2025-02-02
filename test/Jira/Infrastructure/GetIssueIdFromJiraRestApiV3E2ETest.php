<?php

declare(strict_types=1);

namespace Jira\Infrastructure;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psl\Env;
use Psl\Type;
use Throwable;
use TimeSync\Jira\Domain\IssueId;
use TimeSync\Jira\Domain\IssueKey;
use TimeSync\Jira\Infrastructure\GetIssueIdFromJiraRestApiV3;

#[CoversClass(GetIssueIdFromJiraRestApiV3::class)]
#[Group('e2e')]
final class GetIssueIdFromJiraRestApiV3E2ETest extends TestCase
{
    private IssueKey $fallbackIssue;
    private IssueId $expectedId;
    private GetIssueIdFromJiraRestApiV3 $getIssueId;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $secrets = Type\shape([
                'JIRA_BASE_URL'        => Type\non_empty_string(),
                'JIRA_ACCOUNT_ID'        => Type\non_empty_string(),
                'JIRA_API_TOKEN'         => Type\non_empty_string(),
                'FALLBACK_JIRA_ISSUE_ID' => Type\non_empty_string(),
                'EXPECTED_JIRA_ISSUE_ID' => Type\positive_int(),
            ], true)->coerce(Env\get_vars());

            $this->fallbackIssue = IssueKey::make($secrets['FALLBACK_JIRA_ISSUE_ID']);
            $this->expectedId    = IssueId::make($secrets['EXPECTED_JIRA_ISSUE_ID']);
        } catch (Throwable) {
            self::markTestSkipped('E2E test cannot be run: missing environment variables');
        }

        $this->getIssueId = new GetIssueIdFromJiraRestApiV3(
            Psr18ClientDiscovery::find(),
            Psr17FactoryDiscovery::findRequestFactory(),
            $secrets['JIRA_BASE_URL'],
            $secrets['JIRA_ACCOUNT_ID'],
            $secrets['JIRA_API_TOKEN'],
        );
    }

    public function testRetrievesRealIssueId(): void
    {
        self::assertEquals($this->expectedId, $this->getIssueId->__invoke($this->fallbackIssue));
    }
}
