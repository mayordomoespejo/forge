<?php

declare(strict_types=1);

namespace Forge\Tests\Unit\Services;

use Forge\Services\TranslationService;
use PHPUnit\Framework\TestCase;

class TranslationServiceTest extends TestCase
{
    private TranslationService $service;

    protected function setUp(): void
    {
        $this->service = new TranslationService();
    }

    public function test_translate_returns_original_text_when_no_key(): void
    {
        $text   = 'Hello world';
        $result = $this->service->translate($text, 'es');
        $this->assertSame($text, $result);
    }

    public function test_translate_returns_empty_for_empty_input(): void
    {
        $this->assertSame('', $this->service->translate('', 'fr'));
    }

    public function test_translate_returns_string(): void
    {
        $this->assertIsString($this->service->translate('Test', 'de'));
    }
}
