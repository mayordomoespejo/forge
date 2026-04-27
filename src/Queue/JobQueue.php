<?php

declare(strict_types=1);

namespace Forge\Queue;

class JobQueue
{
    private string $baseDir;

    private const DIR_PENDING    = 'pending';
    private const DIR_PROCESSING = 'processing';
    private const DIR_COMPLETED  = 'completed';
    private const DIR_FAILED     = 'failed';

    public function __construct()
    {
        $this->baseDir = __DIR__ . '/../../storage/queue';
        foreach ([self::DIR_PENDING, self::DIR_PROCESSING, self::DIR_COMPLETED, self::DIR_FAILED] as $dir) {
            $path = $this->baseDir . '/' . $dir;
            if (!is_dir($path)) mkdir($path, 0755, true);
        }
    }

    /**
     * Enqueues a new job and returns the generated job ID.
     *
     * @param  string               $type     Job type identifier (e.g. 'audio', 'video', 'document')
     * @param  string               $filePath Absolute path to the file to process
     * @param  array<string, mixed> $options  Additional processing options forwarded to the worker
     * @return string                         Unique job ID (prefixed with 'job_')
     */
    public function enqueue(string $type, string $filePath, array $options = []): string
    {
        $jobId = uniqid('job_', true);
        $job   = [
            'id'         => $jobId,
            'type'       => $type,
            'file_path'  => $filePath,
            'options'    => $options,
            'created_at' => date('c'),
            'status'     => self::DIR_PENDING,
        ];

        file_put_contents(
            $this->baseDir . '/' . self::DIR_PENDING . '/' . $jobId . '.json',
            json_encode($job),
            LOCK_EX
        );

        return $jobId;
    }

    /**
     * Claims the oldest pending job and moves it to the processing state.
     *
     * Returns null when the queue is empty.
     *
     * @return array<string, mixed>|null Job data array, or null if queue is empty
     */
    public function dequeue(): ?array
    {
        $files = glob($this->baseDir . '/' . self::DIR_PENDING . '/*.json') ?: [];
        if (empty($files)) return null;

        sort($files);
        $file = $files[0];
        $job  = json_decode((string) file_get_contents($file), true);
        if (!is_array($job)) return null;

        unlink($file);
        $job['status']     = self::DIR_PROCESSING;
        $job['started_at'] = date('c');

        file_put_contents(
            $this->baseDir . '/' . self::DIR_PROCESSING . '/' . $job['id'] . '.json',
            json_encode($job),
            LOCK_EX
        );

        return $job;
    }

    /**
     * Marks a processing job as completed and stores its result.
     *
     * @param string               $jobId  Job identifier returned by enqueue()
     * @param array<string, mixed> $result Analysis result to persist alongside the job
     */
    public function complete(string $jobId, array $result): void
    {
        $procFile = $this->baseDir . '/' . self::DIR_PROCESSING . '/' . $jobId . '.json';
        $job      = is_file($procFile)
            ? (json_decode((string) file_get_contents($procFile), true) ?? [])
            : [];

        if (is_file($procFile)) unlink($procFile);

        $job['status']       = self::DIR_COMPLETED;
        $job['completed_at'] = date('c');
        $job['result']       = $result;

        file_put_contents(
            $this->baseDir . '/' . self::DIR_COMPLETED . '/' . $jobId . '.json',
            json_encode($job),
            LOCK_EX
        );
    }

    /**
     * Marks a processing job as failed and records the error message.
     *
     * @param string $jobId Job identifier returned by enqueue()
     * @param string $error Human-readable error description
     */
    public function fail(string $jobId, string $error): void
    {
        $procFile = $this->baseDir . '/' . self::DIR_PROCESSING . '/' . $jobId . '.json';
        $job      = is_file($procFile)
            ? (json_decode((string) file_get_contents($procFile), true) ?? [])
            : ['id' => $jobId];

        if (is_file($procFile)) unlink($procFile);

        $job['status']    = self::DIR_FAILED;
        $job['error']     = $error;
        $job['failed_at'] = date('c');

        file_put_contents(
            $this->baseDir . '/' . self::DIR_FAILED . '/' . $jobId . '.json',
            json_encode($job),
            LOCK_EX
        );
    }

    /**
     * Returns the current state of a job, searching all status directories.
     *
     * @param  string                    $jobId Job identifier returned by enqueue()
     * @return array<string, mixed>|null        Job data array, or null if not found
     */
    public function status(string $jobId): ?array
    {
        foreach ([self::DIR_PENDING, self::DIR_PROCESSING, self::DIR_COMPLETED, self::DIR_FAILED] as $dir) {
            $file = $this->baseDir . '/' . $dir . '/' . $jobId . '.json';
            if (is_file($file)) {
                return json_decode((string) file_get_contents($file), true);
            }
        }
        return null;
    }

    /**
     * Returns the number of jobs currently waiting in the pending queue.
     *
     * @return int Count of pending jobs
     */
    public function pendingCount(): int
    {
        return count(glob($this->baseDir . '/' . self::DIR_PENDING . '/*.json') ?: []);
    }
}
