<?php

class ConsoleProgressReporter extends ProgressReporter
{
    const SPINNER_SEQUENCE = '-\\|/';
    const FINISHED_SYMBOL = '*';
    protected $crPrinted = false;
    protected $spinnerPosition;

    protected $lastLine = '';

    public function report()
    {
        if (php_sapi_name() !== 'cli') {
            return;
        }

        if ($this->getDone() === $this->getTotal()) {
            $spinnerChar = static::FINISHED_SYMBOL;
        } else {
            if ($this->spinnerPosition === null || $this->spinnerPosition >= strlen(static::SPINNER_SEQUENCE)) {
                $this->spinnerPosition = 0;
            }
            $spinnerChar = static::SPINNER_SEQUENCE[$this->spinnerPosition];
            $this->spinnerPosition++;
        }

        $newLine = '  ' . $spinnerChar . ' ' . $this->toString();
        while (strlen($newLine) < strlen($this->lastLine)) {
            $newLine .= ' ';
        }
        echo $newLine . "\r";
        $this->lastLine = $newLine;

        $this->crPrinted = true;
    }

    public function finish()
    {
        parent::finish();

        if ($this->crPrinted) {
            echo "\n";
            $this->crPrinted = false;
        }
    }


}