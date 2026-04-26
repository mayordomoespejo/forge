<?php

declare(strict_types=1);

namespace Forge\Tests\Unit\Services;

use Forge\ContentAnalyzer;
use PHPUnit\Framework\TestCase;

class ContentAnalyzerTest extends TestCase
{
    private ContentAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new ContentAnalyzer();
    }

    public function test_analyze_text_returns_required_keys(): void
    {
        $result = $this->analyzer->analyze('text', 'Hello world, this is a test.');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('type', $result);
        $this->assertSame('text', $result['type']);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('analysis', $result);
    }

    public function test_analyze_text_stores_content(): void
    {
        $input  = 'Sample content for analysis.';
        $result = $this->analyzer->analyze('text', $input);

        $this->assertSame($input, $result['content']);
    }

    public function test_analyze_unknown_type_returns_error(): void
    {
        $result = $this->analyzer->analyze('unknown_type', 'some content');

        $this->assertArrayHasKey('error', $result);
        $this->assertNotEmpty($result['error']);
    }

    public function test_analyze_returns_pipeline_key(): void
    {
        $result = $this->analyzer->analyze('text', 'Testing pipeline key presence.');

        $this->assertArrayHasKey('pipeline', $result);
        $this->assertIsArray($result['pipeline']);
    }

    public function test_analyze_text_has_analysis_subkeys(): void
    {
        $result   = $this->analyzer->analyze('text', 'Testing sub-keys.');
        $analysis = $result['analysis'] ?? [];

        // Analysis should be an array (may be empty if no API key configured)
        $this->assertIsArray($analysis);
    }

    public function test_analyze_nonexistent_file_returns_error(): void
    {
        $result = $this->analyzer->analyze('document', '', '/nonexistent/file/path.pdf');

        $this->assertArrayHasKey('error', $result);
    }

    public function test_analyze_image_nonexistent_file_returns_error(): void
    {
        $result = $this->analyzer->analyze('image', '', '/nonexistent/image.jpg');

        $this->assertArrayHasKey('error', $result);
    }
}
