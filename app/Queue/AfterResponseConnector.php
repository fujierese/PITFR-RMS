<?php

namespace App\Queue;

use Illuminate\Queue\Connectors\ConnectorInterface;

class AfterResponseConnector implements ConnectorInterface
{
    public function connect(array $config)
    {
        return new AfterResponseQueue($config['fallback'] ?? 'database', $config['after_commit'] ?? null);
    }
}
