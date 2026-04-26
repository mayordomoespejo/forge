<?php

declare(strict_types=1);

namespace Forge\Queue;

class JobQueue
{
    private string $baseDir;

    public function __construct()
    {
        $this->baseDir = __DIR__ . '/../../storage/queue';
        foreach (['pending', 'processing', 'completed', 'failed'] as $dir) {
            $path = $this->baseDir . '/' . $dir;
            if (!is_dir($path)) mkdir($path, 0755, true);
        }
    }

    /**
     * Enqueues a new job and returns the job ID.
     *
     * @param array<string, mixed> $options
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
            'status'     => 'pending',
        ];

        file_put_contents(
            $this->baseDir . '/pending/' . $jobId . '.json',
            json_encode($job),
            LOCK_EX
        );

        return $jobId;
    }

    /**
     * Returns the next pending job and moves it to processing.
     *
     * @return array<string, mixed>|null
     */
    public function dequeue(): ?array
    {
        $files = glob($this->baseDir . '/pending/*.json') ?: [];
        if (empty($files)) return null;

        sort($files);
        $file = $files[0];
        $job  = json_decode((string) file_get_contents($file), true);
        if (!is_array($job)) return null;

        unlink($file);
        $job['status']        = 'processing';
        $job['started_at']    = date('c');

        file_put_contents(
            $this->baseDir . '/processing/' . $job['id'] . '.json',
            json_encode($job),
            LOCK_EX
        );

        return $job;
    }

    /**
     * @param array<string, mixed> $result
     */
    public function complete(string $jobId, array $result): void
    {
        $procFile = $this->baseDir . '/processing/' . $jobId . '.json';
        $job      = is_file($procFile)
            ? (json_decode((string) file_get_contents($procFile), true) ?? [])
            : [];

        if (is_file($procFile)) unlink($procFile);

        $job['status']       = 'completed';
        $job['completed_at'] = date('c');
        $job['result']       = $result;

        file_put_contents(
            $this->baseDir . '/completed/' . $jobId . '.json',
            json_encode($job),
            LOCK_EX
        );
    }

    public function fail(string $jobId, string $error): void
    {
        $procFile = $this->baseDir . '/processing/' . $jobId . '.json';
        $job      = is_file($procFile)
            ? (json_decode((string) file_get_contents($procFile), true) ?? [])
            : ['id' => $jobId];

        if (is_file($procFile)) unlink($procFile);

        $job['status']    = 'failed';
        $job['error']     = $error;
        $job['failed_at'] = date('c');

        file_put_contents(
            $this->baseDir . '/failed/' . $jobId . '.json',
            json_encode($job),
            LOCK_EX
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function status(string $jobId): ?array
    {
        foreach (['pending', 'processing', 'completed', 'failed'] as $dir) {
            $file = $this->baseDir . '/' . $dir . '/' . $jobId . '.json';
            if (is_file($file)) {
                return json_decode((string) file_get_contents($file), true);
            }
        }
        return null;
    }

    public function pendingCount(): int
    {
        return count(glob($this->baseDir . '/pending/*.json') ?: []);
    }
}
