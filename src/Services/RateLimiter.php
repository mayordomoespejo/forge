<?php

declare(strict_types=1);

namespace Forge\Services;

class RateLimiter
{
    private string $dir;
    private int    $maxRequests;
    private int    $windowSeconds;

    public function __construct(int $maxRequests = 20, int $windowSeconds = 3600)
    {
        $this->maxRequests   = $maxRequests;
        $this->windowSeconds = $windowSeconds;
        $this->dir           = sys_get_temp_dir() . '/forge_rate/';

        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0700, true);
        }
    }

    /**
     * Returns true if request is allowed, false if rate limit exceeded.
     */
    public function allow(string $ip): bool
    {
        $window = (int) floor(time() / $this->windowSeconds);
        $file   = $this->dir . hash('sha256', $ip) . '_' . $window;

        $count = (int) @file_get_contents($file);

        if ($count >= $this->maxRequests) {
            return false;
        }

        file_put_contents($file, $count + 1, LOCK_EX);
        return true;
    }

    public function remaining(string $ip): int
    {
        $window = (int) floor(time() / $this->windowSeconds);
        $file   = $this->dir . hash('sha256', $ip) . '_' . $window;
        $count  = (int) @file_get_contents($file);
        return max(0, $this->maxRequests - $count);
    }
}
