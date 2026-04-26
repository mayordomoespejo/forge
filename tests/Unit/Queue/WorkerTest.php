<?php

declare(strict_types=1);

namespace Forge\Tests\Unit\Queue;

use Forge\Queue\JobQueue;
use PHPUnit\Framework\TestCase;

class WorkerTest extends TestCase
{
    private string $tmpBase;
    private JobQueue $queue;

    protected function setUp(): void
    {
        $this->tmpBase = sys_get_temp_dir() . '/forge_worker_test_' . uniqid();

        $this->queue = new JobQueue();
        $ref = new \ReflectionProperty(JobQueue::class, 'baseDir');
        $ref->setAccessible(true);
        $ref->setValue($this->queue, $this->tmpBase);

        foreach (['pending', 'processing', 'completed', 'failed'] as $dir) {
            mkdir($this->tmpBase . '/' . $dir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpBase);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (glob($dir . '/*') ?: [] as $item) {
            is_dir($item) ? $this->removeDir($item) : unlink($item);
        }
        rmdir($dir);
    }

    public function test_queue_job_lifecycle_pending_to_completed(): void
    {
        $jobId = $this->queue->enqueue('text', '/tmp/test.txt');

        $this->assertSame('pending', $this->queue->status($jobId)['status']);

        $job = $this->queue->dequeue();
        $this->assertSame('processing', $this->queue->status($jobId)['status']);

        $this->queue->complete($jobId, ['type' => 'text', 'content' => 'done']);
        $this->assertSame('completed', $this->queue->status($jobId)['status']);
    }

    public function test_queue_job_lifecycle_pending_to_failed(): void
    {
        $jobId = $this->queue->enqueue('audio', '/tmp/audio.mp3');
        $this->queue->dequeue();
        $this->queue->fail($jobId, 'Transcription failed');

        $job = $this->queue->status($jobId);
        $this->assertSame('failed', $job['status']);
        $this->assertSame('Transcription failed', $job['error']);
    }

    public function test_completed_job_has_result(): void
    {
        $jobId  = $this->queue->enqueue('text', '/tmp/t.txt');
        $result = ['type' => 'text', 'analysis' => ['sentiment' => 'positive']];
        $this->queue->dequeue();
        $this->queue->complete($jobId, $result);

        $job = $this->queue->status($jobId);
        $this->assertSame($result, $job['result']);
    }

    public function test_completed_job_has_timestamps(): void
    {
        $jobId = $this->queue->enqueue('text', '/tmp/t.txt');
        $this->queue->dequeue();
        $this->queue->complete($jobId, []);

        $job = $this->queue->status($jobId);
        $this->assertArrayHasKey('created_at', $job);
        $this->assertArrayHasKey('started_at', $job);
        $this->assertArrayHasKey('completed_at', $job);
    }

    public function test_options_are_preserved_through_queue(): void
    {
        $options = ['speech_language' => 'es-ES', 'doc_model' => 'prebuilt-invoice'];
        $jobId   = $this->queue->enqueue('audio', '/tmp/a.mp3', $options);
        $job     = $this->queue->dequeue();

        $this->assertSame($options, $job['options']);
    }
}
