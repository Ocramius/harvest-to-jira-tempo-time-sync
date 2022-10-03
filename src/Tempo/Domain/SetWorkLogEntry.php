<?php

declare(strict_types=1);

namespace TimeSync\Tempo\Domain;

interface SetWorkLogEntry
{
    public function __invoke(LogEntry $logEntry): void;
}
