<?php

namespace Yl\LaravelQueueMns;

use AliyunMNS\Client;
use AliyunMNS\Exception\MessageNotExistException;
use AliyunMNS\Requests\CreateQueueRequest;
use AliyunMNS\Requests\ListQueueRequest;
use AliyunMNS\Requests\SendMessageRequest;
use Exception;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;

class MnsQueue extends Queue implements QueueContract
{
    protected $mns;

    protected $default;

    public function __construct(Client $mns, $default)
    {
        $this->mns = $mns;
        $this->default = $default;
    }

    public function size($queue = null)
    {
        throw new Exception('The size method is not support for aliyun-mns');
    }

    public function push($job, $data = '', $queue = null)
    {
        $queue = $this->getQueue($queue);
        if (!$this->queueExists($queue)) {
            $this->createQueue($queue);
        }
        return $this->pushRaw($this->createPayload($job, $queue, $data), $queue);
    }

    public function pushRaw($payload, $queue = null, array $options = [])
    {
        return $this->mns->getQueueRef($this->getQueue($queue))->sendMessage(
            new SendMessageRequest($payload)
        )->getMessageId();
    }

    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->mns->getQueueRef($this->getQueue($queue))->sendMessage(
            new SendMessageRequest($this->createPayload($job, $data), $this->secondsUntil($delay))
        )->getMessageId();
    }

    public function pop($queue = null)
    {
        $queue = $this->getQueue($queue);
        if (!$this->queueExists($queue)) {
            $this->createQueue($queue);
        }
        try {
            $response = $this->mns->getQueueRef($queue)->receiveMessage();
            return new MnsJob($this->container, $this->mns, $queue, $response, $this->connectionName);
        } catch (MessageNotExistException $exception) {
            return null;
        }
    }

    public function getQueue($queue)
    {
        return $queue ?: $this->default;
    }

    public function queueExists($queue)
    {
        $queues = $this->mns->listQueue(new ListQueueRequest())->getQueueNames();
        return in_array($queue, $queues);
    }

    public function createQueue($queue)
    {
        $this->mns->createQueue(new CreateQueueRequest($queue));
    }
}
