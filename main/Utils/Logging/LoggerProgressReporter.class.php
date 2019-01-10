<?php

class LoggerProgressReporter extends ProgressReporter
{
    protected $logger;
    protected $logLevel;
    
    public function __construct(\Psr\Log\LoggerInterface $logger, $logLevel = LogLevel::INFO)
    {
        $this->logger = $logger;    
        $this->logLevel = $logLevel;
    }

    public function report()
    {
        $this->logger->log($this->logLevel, $this->toString());
    }

}