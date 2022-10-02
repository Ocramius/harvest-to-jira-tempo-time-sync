<?php

declare(strict_types=1);

namespace CrowdfoxTimeSync\Tempo\Domain;

interface SetWorkLogEntry
{
    public function __invoke(LogEntry $logEntry): void;
}
