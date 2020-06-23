<?php

namespace LaravelQueueMns;

use AliyunMNS\Client;
use Illuminate\Queue\Connectors\ConnectorInterface;

class MnsConnector implements ConnectorInterface
{
    public function connect(array $config)
    {
        return new MnsQueue(
            new Client($config['endpoint'], $config['key'], $config['secret']), $config
        );
    }
}
