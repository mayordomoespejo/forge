<?php

declare(strict_types=1);

namespace Forge\Queue;

use Forge\ContentAnalyzer;

class Worker
{
    private JobQueue        $queue;
    private ContentAnalyzer $analyzer;
    private bool            $running = true;

    public function __construct()
    {
        $this->queue    = new JobQueue();
        $this->analyzer = new ContentAnalyzer();
    }

    public function run(): void
    {
        echo '[Worker] Started. Waiting for jobs...' . PHP_EOL;

        pcntl_signal(SIGTERM, function (): void { $this->running = false; });
        pcntl_signal(SIGINT,  function (): void { $this->running = false; });

        while ($this->running) {
            pcntl_signal_dispatch();

            $job = $this->queue->dequeue();

            if ($job === null) {
                sleep(2);
                continue;
            }

            $jobId = $job['id'];
            echo '[Worker] Processing job ' . $jobId . ' (type: ' . $job['type'] . ')' . PHP_EOL;

            try {
                $result = $this->analyzer->analyze(
                    $job['type'],
                    '',
                    $job['file_path'],
                    $job['options'] ?? []
                );
                $this->queue->complete($jobId, $result);
                echo '[Worker] Job ' . $jobId . ' completed.' . PHP_EOL;
            } catch (\Throwable $e) {
                $this->queue->fail($jobId, $e->getMessage());
                echo '[Worker] Job ' . $jobId . ' failed: ' . $e->getMessage() . PHP_EOL;
            }
        }

        echo '[Worker] Stopped.' . PHP_EOL;
    }
}
