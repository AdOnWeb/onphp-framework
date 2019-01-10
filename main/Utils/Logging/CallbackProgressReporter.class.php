<?php

class CallbackProgressReporter extends ProgressReporter
{
    protected $callback;

    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    public function report()
    {
        $this->callback($this);
    }

}