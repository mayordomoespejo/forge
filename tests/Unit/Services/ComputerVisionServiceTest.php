<?php

declare(strict_types=1);

namespace Forge\Tests\Unit\Services;

use Forge\Services\ComputerVisionService;
use PHPUnit\Framework\TestCase;

class ComputerVisionServiceTest extends TestCase
{
    private ComputerVisionService $service;

    protected function setUp(): void
    {
        $this->service = new ComputerVisionService();
    }

    public function test_is_not_configured_without_env_vars(): void
    {
        $this->assertFalse($this->service->isConfigured());
    }

    public function test_analyze_returns_empty_result_when_not_configured(): void
    {
        $result = $this->service->analyze('/some/image.jpg');
        $this->assertSame('', $result['ocr_text']);
        $this->assertSame('', $result['caption']);
        $this->assertSame(0.0, $result['caption_confidence']);
        $this->assertSame([], $result['tags']);
    }

    public function test_analyze_result_has_correct_types(): void
    {
        $result = $this->service->analyze('/tmp/test.jpg');
        $this->assertIsString($result['ocr_text']);
        $this->assertIsString($result['caption']);
        $this->assertIsFloat($result['caption_confidence']);
        $this->assertIsArray($result['tags']);
    }
}
