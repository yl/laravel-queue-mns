<?php

namespace LaravelQueueMns;

use AliyunMNS\Client;
use AliyunMNS\Responses\ReceiveMessageResponse;
use Illuminate\Container\Container;
use Illuminate\Queue\Jobs\Job;
use Illuminate\Contracts\Queue\Job as JobContract;

class MnsJob extends Job implements JobContract
{
    protected $mns;

    protected $job;

    public function __construct(Container $container, Client $mns, $queue, ReceiveMessageResponse $job, $connectionName)
    {
        $this->container = $container;
        $this->mns = $mns;
        $this->queue = $queue;
        $this->job = $job;
        $this->connectionName = $connectionName;
    }

    public function release($delay = 1)
    {
        parent::release($delay);

        if ($delay < 1) {
            $delay = 1;
        }

        $this->mns->getQueueRef($this->queue)->changeMessageVisibility($this->job->getReceiptHandle(), $delay);
    }

    public function delete()
    {
        parent::delete();

        $this->mns->getQueueRef($this->queue)->deleteMessage($this->job->getReceiptHandle());
    }

    public function getJobId()
    {
        return $this->job->getMessageId();
    }

    public function getRawBody()
    {
        return $this->job->getMessageBody();
    }

    public function attempts()
    {
        return (int)$this->job->getDequeueCount();
    }
}
