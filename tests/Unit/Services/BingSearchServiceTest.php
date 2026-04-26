<?php

declare(strict_types=1);

namespace Forge\Tests\Unit\Services;

use Forge\Services\BingSearchService;
use PHPUnit\Framework\TestCase;

class BingSearchServiceTest extends TestCase
{
    private BingSearchService $service;

    protected function setUp(): void
    {
        $this->service = new BingSearchService();
    }

    public function test_is_not_configured_without_api_key(): void
    {
        $this->assertFalse($this->service->isConfigured());
    }

    public function test_search_returns_empty_string_when_not_configured(): void
    {
        $this->assertSame('', $this->service->search('Azure AI'));
    }

    public function test_search_returns_empty_for_empty_query(): void
    {
        $this->assertSame('', $this->service->search(''));
    }
}
