<?php

namespace LaravelQueueMns;

use AliyunMNS\Client;
use AliyunMNS\Exception\MessageNotExistException;
use AliyunMNS\Requests\CreateQueueRequest;
use AliyunMNS\Requests\ListQueueRequest;
use AliyunMNS\Requests\SendMessageRequest;
use Exception;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;
use Illuminate\Support\Arr;

class MnsQueue extends Queue implements QueueContract
{
    protected $mns;

    protected $config;

    protected $default;

    public function __construct(Client $mns, $config)
    {
        $this->mns = $mns;
        $this->config = $config;
        $this->default = Arr::get($this->config, 'queue');
    }

    public function size($queue = null)
    {
        throw new Exception('The size method is not support for aliyun mns.');
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
        } catch (MessageNotExistException $exception) {
            return null;
        }

        $message = json_decode($response->getMessageBody(), true);

        if (is_array($message) && Arr::exists($message, 'uuid')) {
            return new MnsJob($this->container, $this->mns, $queue, $response, $this->connectionName);
        }

        $customMessageHandle = Arr::get($this->config, 'custom_message_handle', null);
        if ($customMessageHandle !== null && method_exists($customMessageHandle, 'handle')) {
            (new $customMessageHandle($response))->handle();
            $this->mns->getQueueRef($queue)->deleteMessage($response->getReceiptHandle());
        }
        return null;
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
