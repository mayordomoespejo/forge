<?php

declare(strict_types=1);

namespace Forge\Services;

class RateLimiter
{
    private const DEFAULT_MAX_REQUESTS   = 20;
    private const DEFAULT_WINDOW_SECONDS = 3600;

    private string $dir;
    private int    $maxRequests;
    private int    $windowSeconds;

    public function __construct(int $maxRequests = self::DEFAULT_MAX_REQUESTS, int $windowSeconds = self::DEFAULT_WINDOW_SECONDS)
    {
        $this->maxRequests   = $maxRequests;
        $this->windowSeconds = $windowSeconds;
        $this->dir           = sys_get_temp_dir() . '/forge_rate/';

        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0700, true);
        }
    }

    /**
     * Determines whether the given IP address is allowed to make a request.
     *
     * Increments the request counter for the current time window and returns
     * false when the limit is reached.
     *
     * @param  string $ip IPv4 or IPv6 address of the client
     * @return bool       True if the request is permitted, false if rate-limited
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

    /**
     * Returns the number of requests remaining in the current window for the given IP.
     *
     * @param  string $ip IPv4 or IPv6 address of the client
     * @return int        Remaining allowed requests (minimum 0)
     */
    public function remaining(string $ip): int
    {
        $window = (int) floor(time() / $this->windowSeconds);
        $file   = $this->dir . hash('sha256', $ip) . '_' . $window;
        $count  = (int) @file_get_contents($file);
        return max(0, $this->maxRequests - $count);
    }
}
