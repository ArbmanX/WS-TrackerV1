<?php
namespace App\Services\WorkStudio\Helpers;

class ExecutionTimer
{
    private $startTimes = [];
    private $totalStartTime;

    public function start($label) {
        $this->startTimes[$label] = microtime(true);
    }

    public function stop($label) {
        if (!isset($this->startTimes[$label])) {
            throw new \Exception("Timer for '$label' was not started.");
        }
        $executionTime = microtime(true) - $this->startTimes[$label];
        logger()->info("Function '$label' executed in: " . number_format($executionTime, 4) . " seconds\n");
        echo "Function '$label' executed in: " . number_format($executionTime, 4) . " seconds\n";
        return $executionTime;
    }

    public function startTotal() {
        $this->totalStartTime = microtime(true);
    }

    public function getTotalTime() {
        if (!$this->totalStartTime) {
            throw new \Exception("Total timer was not started.");
        }
        return microtime(true) - $this->totalStartTime;
    }

    public function logTotalTime() {
        $total = $this->getTotalTime();
        logger()->info("Transfer time: {$total}");
        return $total;
    }
}

// // Usage Example
// $timer = new ExecutionTimer();
// $timer->startTotal();

// $timer->start('Function A');
// // Simulate work
// sleep(1);
// $timer->stop('Function A');

// $timer->start('Function B');
// sleep(2);
// $timer->stop('Function B');

// $timer->start('Function C');
// sleep(0.5);
// $timer->stop('Function C');

// $timer->logTotalTime();   
