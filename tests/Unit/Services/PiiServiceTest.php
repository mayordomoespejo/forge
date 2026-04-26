<?php

declare(strict_types=1);

namespace Forge\Tests\Unit\Services;

use Forge\Services\PiiService;
use PHPUnit\Framework\TestCase;

class PiiServiceTest extends TestCase
{
    private PiiService $service;

    protected function setUp(): void
    {
        $this->service = new PiiService();
    }

    public function test_redact_returns_original_text_when_no_key(): void
    {
        $text   = 'Call me at 555-123-4567';
        $result = $this->service->redact($text);
        $this->assertSame($text, $result['redacted_text']);
        $this->assertSame([], $result['pii_found']);
    }

    public function test_redact_returns_empty_for_empty_text(): void
    {
        $result = $this->service->redact('');
        $this->assertSame('', $result['redacted_text']);
        $this->assertSame([], $result['pii_found']);
    }

    public function test_redact_result_has_required_keys(): void
    {
        $result = $this->service->redact('Some text.');
        $this->assertArrayHasKey('redacted_text', $result);
        $this->assertArrayHasKey('pii_found', $result);
        $this->assertIsArray($result['pii_found']);
    }
}
