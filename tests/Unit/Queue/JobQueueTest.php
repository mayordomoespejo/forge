<?php

declare(strict_types=1);

namespace Forge\Tests\Unit\Queue;

use Forge\Queue\JobQueue;
use PHPUnit\Framework\TestCase;

class JobQueueTest extends TestCase
{
    private string $tmpBase;
    private JobQueue $queue;

    protected function setUp(): void
    {
        $this->tmpBase = sys_get_temp_dir() . '/forge_queue_test_' . uniqid();

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

    public function test_enqueue_creates_pending_file(): void
    {
        $jobId = $this->queue->enqueue('text', '/tmp/test.txt');
        $this->assertNotEmpty($jobId);
        $this->assertFileExists($this->tmpBase . '/pending/' . $jobId . '.json');
    }

    public function test_enqueue_returns_unique_ids(): void
    {
        $id1 = $this->queue->enqueue('text', '/tmp/a.txt');
        $id2 = $this->queue->enqueue('text', '/tmp/b.txt');
        $this->assertNotSame($id1, $id2);
    }

    public function test_dequeue_returns_null_when_empty(): void
    {
        $this->assertNull($this->queue->dequeue());
    }

    public function test_dequeue_returns_job_and_moves_to_processing(): void
    {
        $jobId = $this->queue->enqueue('audio', '/tmp/test.mp3', ['speech_language' => 'en-US']);
        $job   = $this->queue->dequeue();

        $this->assertNotNull($job);
        $this->assertSame($jobId, $job['id']);
        $this->assertSame('audio', $job['type']);
        $this->assertSame('processing', $job['status']);
        $this->assertFileDoesNotExist($this->tmpBase . '/pending/' . $jobId . '.json');
        $this->assertFileExists($this->tmpBase . '/processing/' . $jobId . '.json');
    }

    public function test_dequeue_processes_jobs_in_order(): void
    {
        $id1 = $this->queue->enqueue('text', '/tmp/1.txt');
        usleep(10000); // ensure different filenames
        $id2 = $this->queue->enqueue('text', '/tmp/2.txt');

        $first  = $this->queue->dequeue();
        $second = $this->queue->dequeue();

        $this->assertSame($id1, $first['id']);
        $this->assertSame($id2, $second['id']);
    }

    public function test_complete_moves_job_to_completed(): void
    {
        $jobId = $this->queue->enqueue('video', '/tmp/test.mp4');
        $this->queue->dequeue();
        $this->queue->complete($jobId, ['type' => 'video', 'analysis' => []]);

        $this->assertFileDoesNotExist($this->tmpBase . '/processing/' . $jobId . '.json');
        $this->assertFileExists($this->tmpBase . '/completed/' . $jobId . '.json');

        $job = $this->queue->status($jobId);
        $this->assertSame('completed', $job['status']);
        $this->assertArrayHasKey('result', $job);
    }

    public function test_fail_moves_job_to_failed(): void
    {
        $jobId = $this->queue->enqueue('audio', '/tmp/test.wav');
        $this->queue->dequeue();
        $this->queue->fail($jobId, 'Speech service error');

        $this->assertFileDoesNotExist($this->tmpBase . '/processing/' . $jobId . '.json');
        $this->assertFileExists($this->tmpBase . '/failed/' . $jobId . '.json');

        $job = $this->queue->status($jobId);
        $this->assertSame('failed', $job['status']);
        $this->assertSame('Speech service error', $job['error']);
    }

    public function test_status_returns_null_for_unknown_job(): void
    {
        $this->assertNull($this->queue->status('nonexistent_job_id'));
    }

    public function test_status_returns_pending_for_enqueued_job(): void
    {
        $jobId = $this->queue->enqueue('text', '/tmp/x.txt');
        $job   = $this->queue->status($jobId);

        $this->assertSame('pending', $job['status']);
    }

    public function test_pending_count_is_accurate(): void
    {
        $this->assertSame(0, $this->queue->pendingCount());
        $this->queue->enqueue('text', '/tmp/a.txt');
        $this->assertSame(1, $this->queue->pendingCount());
        $this->queue->enqueue('text', '/tmp/b.txt');
        $this->assertSame(2, $this->queue->pendingCount());
        $this->queue->dequeue();
        $this->assertSame(1, $this->queue->pendingCount());
    }
}
