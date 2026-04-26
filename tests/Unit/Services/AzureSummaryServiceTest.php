<?php

declare(strict_types=1);

namespace Forge\Tests\Unit\Services;

use Forge\Services\AzureSummaryService;
use PHPUnit\Framework\TestCase;

class AzureSummaryServiceTest extends TestCase
{
    private AzureSummaryService $service;

    protected function setUp(): void
    {
        $this->service = new AzureSummaryService();
    }

    public function test_extractive_returns_null_when_no_key(): void
    {
        $this->assertNull($this->service->extractive('Long text to summarize.'));
    }

    public function test_extractive_returns_null_for_empty_text(): void
    {
        $this->assertNull($this->service->extractive(''));
    }

    public function test_extractive_returns_null_or_string(): void
    {
        $result = $this->service->extractive('Some content.');
        $this->assertTrue($result === null || is_string($result));
    }
}
