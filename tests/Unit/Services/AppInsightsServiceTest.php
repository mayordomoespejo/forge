<?php

declare(strict_types=1);

namespace Forge\Tests\Unit\Services;

use Forge\Services\AppInsightsService;
use PHPUnit\Framework\TestCase;

class AppInsightsServiceTest extends TestCase
{
    private AppInsightsService $service;

    protected function setUp(): void
    {
        $this->service = new AppInsightsService();
    }

    public function test_is_not_configured_without_ikey(): void
    {
        $this->assertFalse($this->service->isConfigured());
    }

    public function test_track_event_does_not_throw_when_not_configured(): void
    {
        $this->service->trackEvent('TestEvent', ['type' => 'text']);
        $this->assertTrue(true);
    }

    public function test_track_exception_does_not_throw_when_not_configured(): void
    {
        $this->service->trackException(new \RuntimeException('Test'));
        $this->assertTrue(true);
    }

    public function test_track_event_with_empty_properties_does_not_throw(): void
    {
        $this->service->trackEvent('EmptyProps');
        $this->assertTrue(true);
    }
}
