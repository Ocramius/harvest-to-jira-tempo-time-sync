#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace TimeSync\Bin;

use CuyZ\Valinor\MapperBuilder;
use Http\Discovery\HttpClientDiscovery;
use Http\Discovery\Psr17FactoryDiscovery;
use Psl;
use TimeSync\Harvest\Infrastructure\GetTimeEntriesFromV2Api;
use TimeSync\SyncHarvestToTempo\Domain\SendHarvestEntryToTempo;
use TimeSync\Tempo\Domain\JiraIssueId;
use TimeSync\Tempo\Infrastructure\AddWorkLogEntryViaTempoV3Api;
use TimeSync\Tempo\Infrastructure\GetWorkLogEntriesViaTempoV3Api;

(static function (): void {
    require_once __DIR__ . '/../vendor/autoload.php';

    $secrets = Psl\Type\shape([
        'FALLBACK_JIRA_ISSUE_ID' => Psl\Type\non_empty_string(),
        'TEMPO_ACCESS_TOKEN'     => Psl\Type\non_empty_string(),
        'JIRA_ACCOUNT_ID'        => Psl\Type\non_empty_string(),
        'HARVEST_ACCOUNT_ID'     => Psl\Type\non_empty_string(),
        'HARVEST_ACCESS_TOKEN'   => Psl\Type\non_empty_string(),
        'HARVEST_PROJECT_ID'     => Psl\Type\non_empty_string(),
    ])->coerce(Psl\Env\get_vars());

    $fallbackJiraIssue = new JiraIssueId($secrets['FALLBACK_JIRA_ISSUE_ID']);
    $httpClient        = HttpClientDiscovery::find();
    $requestFactory    = Psr17FactoryDiscovery::findRequestFactory();

    $syncEntry = new SendHarvestEntryToTempo(
        $fallbackJiraIssue,
        new GetWorkLogEntriesViaTempoV3Api(
            $httpClient,
            $requestFactory,
            $secrets['TEMPO_ACCESS_TOKEN'],
            $fallbackJiraIssue,
        ),
        new AddWorkLogEntryViaTempoV3Api(
            $httpClient,
            $requestFactory,
            $secrets['TEMPO_ACCESS_TOKEN'],
            $secrets['JIRA_ACCOUNT_ID'],
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
