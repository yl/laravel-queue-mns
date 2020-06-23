<?php

namespace LaravelQueueMns;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function boot()
    {
        app('queue')->addConnector('mns', function () {
            return new MnsConnector();
        });
    }
}
