<?php

declare(strict_types=1);

namespace Forge\Tests\Unit\Services;

use Forge\Services\RateLimiter;
use PHPUnit\Framework\TestCase;

class RateLimiterTest extends TestCase
{
    private string $tmpDir;
    private RateLimiter $limiter;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/forge_rate_test_' . uniqid();
        mkdir($this->tmpDir, 0700, true);

        // Use reflection to override the private dir property
        $this->limiter = new RateLimiter(3, 3600);
        $ref = new \ReflectionProperty(RateLimiter::class, 'dir');
        $ref->setAccessible(true);
        $ref->setValue($this->limiter, $this->tmpDir . '/');
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tmpDir . '/*') ?: []);
        rmdir($this->tmpDir);
    }

    public function test_first_request_is_allowed(): void
    {
        $this->assertTrue($this->limiter->allow('127.0.0.1'));
    }

    public function test_requests_within_limit_are_allowed(): void
    {
        $this->assertTrue($this->limiter->allow('192.168.1.1'));
        $this->assertTrue($this->limiter->allow('192.168.1.1'));
        $this->assertTrue($this->limiter->allow('192.168.1.1'));
    }

    public function test_request_exceeding_limit_is_denied(): void
    {
        $this->limiter->allow('10.0.0.1');
        $this->limiter->allow('10.0.0.1');
        $this->limiter->allow('10.0.0.1');
        $this->assertFalse($this->limiter->allow('10.0.0.1'));
    }

    public function test_different_ips_are_tracked_independently(): void
    {
        $this->limiter->allow('1.1.1.1');
        $this->limiter->allow('1.1.1.1');
        $this->limiter->allow('1.1.1.1');

        // Different IP should still be allowed
        $this->assertTrue($this->limiter->allow('2.2.2.2'));
    }

    public function test_remaining_decrements_on_each_request(): void
    {
        $this->assertSame(3, $this->limiter->remaining('5.5.5.5'));
        $this->limiter->allow('5.5.5.5');
        $this->assertSame(2, $this->limiter->remaining('5.5.5.5'));
        $this->limiter->allow('5.5.5.5');
        $this->assertSame(1, $this->limiter->remaining('5.5.5.5'));
    }

    public function test_remaining_is_zero_when_limit_reached(): void
    {
        $this->limiter->allow('6.6.6.6');
        $this->limiter->allow('6.6.6.6');
        $this->limiter->allow('6.6.6.6');
        $this->assertSame(0, $this->limiter->remaining('6.6.6.6'));
    }
}
