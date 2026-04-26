<?php

declare(strict_types=1);

namespace Forge\Tests\Unit\Services;

use Forge\Services\ContentSafetyService;
use PHPUnit\Framework\TestCase;

class ContentSafetyServiceTest extends TestCase
{
    private ContentSafetyService $service;

    protected function setUp(): void
    {
        $this->service = new ContentSafetyService();
    }

    public function test_analyze_text_returns_safe_when_no_key(): void
    {
        $result = $this->service->analyzeText('Some content.');
        $this->assertTrue($result['safe']);
        $this->assertSame([], $result['flags']);
    }

    public function test_analyze_text_returns_safe_for_empty_text(): void
    {
        $result = $this->service->analyzeText('');
        $this->assertTrue($result['safe']);
    }

    public function test_analyze_image_returns_safe_when_no_key(): void
    {
        $result = $this->service->analyzeImage('/nonexistent.jpg');
        $this->assertTrue($result['safe']);
        $this->assertSame([], $result['flags']);
    }

    public function test_result_has_required_keys_and_types(): void
    {
        $result = $this->service->analyzeText('test');
        $this->assertArrayHasKey('safe', $result);
        $this->assertArrayHasKey('flags', $result);
        $this->assertIsBool($result['safe']);
        $this->assertIsArray($result['flags']);
    }
}
