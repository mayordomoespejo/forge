<?php

declare(strict_types=1);

namespace Forge\Tests\Unit\Services;

use Forge\Services\DocumentService;
use PHPUnit\Framework\TestCase;

class DocumentServiceParseTest extends TestCase
{
    private \ReflectionMethod $parseResult;
    private DocumentService   $service;

    protected function setUp(): void
    {
        $this->service     = new DocumentService();
        $this->parseResult = new \ReflectionMethod(DocumentService::class, 'parseResult');
        $this->parseResult->setAccessible(true);
    }

    public function test_parse_result_returns_required_keys(): void
    {
        $result = $this->parseResult->invoke($this->service, []);
        $this->assertArrayHasKey('page_count', $result);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('paragraphs', $result);
        $this->assertArrayHasKey('tables', $result);
        $this->assertArrayHasKey('fields', $result);
    }

    public function test_parse_result_with_empty_input(): void
    {
        $result = $this->parseResult->invoke($this->service, []);
        $this->assertSame(0, $result['page_count']);
        $this->assertSame('', $result['content']);
        $this->assertSame([], $result['paragraphs']);
        $this->assertSame([], $result['tables']);
        $this->assertSame([], $result['fields']);
    }

    public function test_parse_result_counts_pages(): void
    {
        $input  = ['pages' => [[], [], []]];
        $result = $this->parseResult->invoke($this->service, $input);
        $this->assertSame(3, $result['page_count']);
    }

    public function test_parse_result_extracts_content(): void
    {
        $result = $this->parseResult->invoke($this->service, ['content' => 'Extracted text.']);
        $this->assertSame('Extracted text.', $result['content']);
    }

    public function test_parse_result_filters_empty_paragraphs(): void
    {
        $input = ['paragraphs' => [
            ['content' => 'First'],
            ['content' => ''],
            ['content' => 'Third'],
        ]];
        $result = $this->parseResult->invoke($this->service, $input);
        $this->assertCount(2, $result['paragraphs']);
        $this->assertSame('First', $result['paragraphs'][0]);
        $this->assertSame('Third', $result['paragraphs'][1]);
    }

    public function test_parse_result_builds_table_grid(): void
    {
        $input = ['tables' => [[
            'rowCount'    => 2,
            'columnCount' => 2,
            'cells'       => [
                ['rowIndex' => 0, 'columnIndex' => 0, 'content' => 'A'],
                ['rowIndex' => 0, 'columnIndex' => 1, 'content' => 'B'],
                ['rowIndex' => 1, 'columnIndex' => 0, 'content' => 'C'],
                ['rowIndex' => 1, 'columnIndex' => 1, 'content' => 'D'],
            ],
        ]]];
        $result = $this->parseResult->invoke($this->service, $input);
        $this->assertCount(1, $result['tables']);
        $this->assertSame('A', $result['tables'][0][0][0]);
        $this->assertSame('D', $result['tables'][0][1][1]);
    }

    public function test_parse_result_skips_empty_tables(): void
    {
        $input = ['tables' => [
            ['rowCount' => 0, 'columnCount' => 2, 'cells' => []],
            ['rowCount' => 2, 'columnCount' => 0, 'cells' => []],
        ]];
        $result = $this->parseResult->invoke($this->service, $input);
        $this->assertSame([], $result['tables']);
    }
}
