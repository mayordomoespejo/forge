<?php

declare(strict_types=1);

namespace Forge\Tests\Unit\Pipeline;

use Forge\Pipeline\IntelligencePipeline;
use PHPUnit\Framework\TestCase;

class IntelligencePipelineTest extends TestCase
{
    private IntelligencePipeline $pipeline;

    protected function setUp(): void
    {
        $this->pipeline = new IntelligencePipeline();
    }

    public function test_run_with_empty_text_returns_expected_structure(): void
    {
        $result = $this->pipeline->run('');
        $this->assertArrayHasKey('entities', $result);
        $this->assertArrayHasKey('redacted', $result);
        $this->assertArrayHasKey('pii_found', $result);
        $this->assertArrayHasKey('consistent', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('blocked', $result);
    }

    public function test_run_with_empty_text_is_not_consistent(): void
    {
        $result = $this->pipeline->run('');
        $this->assertFalse($result['consistent']);
        $this->assertNull($result['summary']);
    }

    public function test_run_not_blocked_when_safety_not_configured(): void
    {
        $result = $this->pipeline->run('Normal text content.');
        $this->assertFalse($result['blocked']);
    }

    public function test_run_pii_passthrough_when_not_configured(): void
    {
        $text   = 'My phone is 555-000-0000';
        $result = $this->pipeline->run($text);
        $this->assertSame($text, $result['redacted']);
        $this->assertSame([], $result['pii_found']);
    }

    public function test_run_preserves_provided_entities(): void
    {
        $entities = [['text' => 'Microsoft', 'category' => 'Organization']];
        $result   = $this->pipeline->run('Microsoft is a company.', $entities);
        $this->assertSame($entities, $result['entities']);
    }

    public function test_run_confidence_is_valid_float(): void
    {
        $result = $this->pipeline->run('Test.');
        $this->assertIsFloat($result['confidence']);
        $this->assertGreaterThanOrEqual(0.0, $result['confidence']);
        $this->assertLessThanOrEqual(1.0, $result['confidence']);
    }

    public function test_run_blocked_flag_is_bool(): void
    {
        $result = $this->pipeline->run('Some content.');
        $this->assertIsBool($result['blocked']);
    }
}
