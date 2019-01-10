<?php

abstract class ProgressReporter implements Stringable
{
    private $total = 100;
    private $done = 0;
    private $message = '';

    private $startDone;
    private $startTime;

    abstract function report();

    public function update(int $done, $message = null)
    {
        if ($message) {
            $this->setMessage($message);
        }
        $this->done = $done;

        if (!$this->startTime) {
            $this->startTimer();
        }

        $this->report();

        if ($this->done === $this->total) {
            $this->finish();
        }

        return $this;
    }

    public function increment(int $items = 1)
    {
        return $this->update($this->getDone() + $items);
    }

    public function setTotal(int $total)
    {
        Assert::isGreater($total, 0);

        $this->total = $total;
        return $this;
    }

    public function setMessage(string $message)
    {
        $this->message = $message;
        return $this;
    }

    public function getDone()
    {
        return $this->done;
    }

    public function getTotal()
    {
        return $this->total;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function getPercent()
    {
        return 100 * $this->done / $this->total;
    }

    public function toString()
    {
        $message = sprintf('[%3d%%] %s', $this->getPercent(), $this->getMessage());

        if ($this->total != 100) {
            $message .= " ($this->done / $this->total)";
        }

        $eta = $this->getEta();
        if ($eta) {
            $message .= '  (ETA: ' . $eta->toString() . ')';
        }

        return $message;
    }

    public function getEta()
    {
        if ($this->startDone === null || $this->startTime === null) {
            return null;
        }

        if ($this->startDone >= $this->done) {
            return null;
        }

        if ($this->done >= $this->total) {
            return null;
        }

        $timePerOne = (microtime(true) - $this->startTime) / ($this->done - $this->startDone);
        $timeLeft = $timePerOne * ($this->total - $this->done);
        $timeETA = Timestamp::create(time() + $timeLeft);

        return $timeETA;
    }

    public function startTimer()
    {
        $this->startDone = $this->done;
        $this->startTime = microtime(true);
    }

    public function stopTimer()
    {
        $this->startTime = null;
        $this->startDone = null;
    }

    public function finish()
    {
        $this->stopTimer();
    }
}