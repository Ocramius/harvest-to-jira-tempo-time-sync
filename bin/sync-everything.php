#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace TimeSync\Bin;

use CuyZ\Valinor\MapperBuilder;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psl;
use Psl\Json;
use Psl\Type;
use TimeSync\Harvest\Infrastructure\GetTimeEntriesFromV2Api;
use TimeSync\Jira\Domain\IssueId;
use TimeSync\Jira\Domain\IssueKey;
use TimeSync\Jira\Infrastructure\GetIssueIdFromJiraRestApiV3;
use TimeSync\SyncHarvestToTempo\Domain\SendHarvestEntryToTempo;
use TimeSync\Tempo\Domain\JiraIssueId;
use TimeSync\Tempo\Infrastructure\AddWorkLogEntryViaTempoV4Api;
use TimeSync\Tempo\Infrastructure\GetWorkLogEntriesViaTempoV4Api;

(static function (): void {
    require_once __DIR__ . '/../vendor/autoload.php';

    $customAttributeTypes = Type\dict(
        Type\non_empty_string(),
        Type\string(),
    );

    $secrets = Psl\Type\shape([
        'FALLBACK_JIRA_ISSUE_INTERNAL_ID' => Psl\Type\positive_int(),
        'FALLBACK_JIRA_ISSUE_ID'          => Psl\Type\non_empty_string(),
        'TEMPO_ACCESS_TOKEN'              => Psl\Type\non_empty_string(),
        'TEMPO_CUSTOM_WORKLOG_ATTRIBUTES' => Psl\Type\optional(Psl\Type\converted(
            Type\non_empty_string(),
            $customAttributeTypes,
            static function (string $attributes) use ($customAttributeTypes): array {
                return Json\typed($attributes, $customAttributeTypes);
            },
        )),
        'JIRA_BASE_URL'                   => Psl\Type\non_empty_string(),
        'JIRA_ACCOUNT_EMAIL'              => Psl\Type\non_empty_string(),
        'JIRA_ACCOUNT_ID'                 => Psl\Type\non_empty_string(),
        'JIRA_API_TOKEN'                  => Psl\Type\non_empty_string(),
        'HARVEST_ACCOUNT_ID'              => Psl\Type\non_empty_string(),
        'HARVEST_ACCESS_TOKEN'            => Psl\Type\non_empty_string(),
        'HARVEST_PROJECT_ID'              => Psl\Type\non_empty_string(),
    ])->coerce(Psl\Env\get_vars());

    $httpClient        = Psr18ClientDiscovery::find();
    $fallbackJiraIssue = new JiraIssueId(
        IssueId::make($secrets['FALLBACK_JIRA_ISSUE_INTERNAL_ID']),
        // Note: naming still wonky due to BC - may need a rename in a breaking change
        IssueKey::make($secrets['FALLBACK_JIRA_ISSUE_ID']),
    );
    $requestFactory = Psr17FactoryDiscovery::findRequestFactory();

    $getId = new GetIssueIdFromJiraRestApiV3(
        $httpClient,
        $requestFactory,
        $secrets['JIRA_BASE_URL'],
        $secrets['JIRA_ACCOUNT_EMAIL'],
        $secrets['JIRA_API_TOKEN'],
    );

    $syncEntry = new SendHarvestEntryToTempo(
        $getId,
        $fallbackJiraIssue,
        new GetWorkLogEntriesViaTempoV4Api(
            $getId,
            $httpClient,
            $requestFactory,
            $secrets['TEMPO_ACCESS_TOKEN'],
            $fallbackJiraIssue,
        ),
        new AddWorkLogEntryViaTempoV4Api(
            $httpClient,
            $requestFactory,
            $secrets['TEMPO_ACCESS_TOKEN'],
            $secrets['JIRA_ACCOUNT_ID'], // @TODO perhaps extract this from other credentials?
            $secrets['TEMPO_CUSTOM_WORKLOG_ATTRIBUTES'] ?? [],
        ),
    );

    $getHarvestEntries = new GetTimeEntriesFromV2Api(
        $httpClient,
        Psr17FactoryDiscovery::findUriFactory(),
        $requestFactory,
        (new MapperBuilder())
            ->enableFlexibleCasting()
            ->allowSuperfluousKeys()
            ->mapper(),
        $secrets['HARVEST_ACCOUNT_ID'],
        $secrets['HARVEST_ACCESS_TOKEN'],
    );

    foreach ($getHarvestEntries($secrets['HARVEST_PROJECT_ID']) as $entry) {
        echo $entry->spent_date->toString() . ': ' . $entry->notes . "\n";

        $syncEntry($entry);
    }
})();
