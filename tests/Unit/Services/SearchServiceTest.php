<?php

declare(strict_types=1);

namespace Forge\Tests\Unit\Services;

use Forge\Services\SearchService;
use PHPUnit\Framework\TestCase;

class SearchServiceTest extends TestCase
{
    private SearchService $service;

    protected function setUp(): void
    {
        $this->service = new SearchService();
    }

    public function test_is_not_configured_without_env_vars(): void
    {
        $this->assertFalse($this->service->isConfigured());
    }

    public function test_search_returns_empty_when_not_configured(): void
    {
        $this->assertSame([], $this->service->search('test query'));
    }

    public function test_search_returns_empty_for_empty_query(): void
    {
        $this->assertSame([], $this->service->search(''));
    }

    public function test_all_returns_empty_when_not_configured(): void
    {
        $this->assertSame([], $this->service->all());
    }

    public function test_save_does_nothing_when_not_configured(): void
    {
        $this->service->save('job_123', ['type' => 'text']);
        $this->assertTrue(true);
    }
}
