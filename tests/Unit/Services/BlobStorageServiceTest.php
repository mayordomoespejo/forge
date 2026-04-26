<?php

declare(strict_types=1);

namespace Forge\Tests\Unit\Services;

use Forge\Services\BlobStorageService;
use PHPUnit\Framework\TestCase;

class BlobStorageServiceTest extends TestCase
{
    private BlobStorageService $service;

    protected function setUp(): void
    {
        $this->service = new BlobStorageService();
    }

    public function test_is_not_configured_without_env_vars(): void
    {
        $this->assertFalse($this->service->isConfigured());
    }

    public function test_upload_throws_when_not_configured(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service->upload('/tmp/test.txt', 'test.txt');
    }

    public function test_upload_throws_for_nonexistent_file_when_configured(): void
    {
        $ref = new \ReflectionProperty(BlobStorageService::class, 'account');
        $ref->setAccessible(true);
        $ref->setValue($this->service, 'testaccount');

        $keyRef = new \ReflectionProperty(BlobStorageService::class, 'key');
        $keyRef->setAccessible(true);
        $keyRef->setValue($this->service, base64_encode('fakekey12345678901234567890123456'));

        $this->expectException(\RuntimeException::class);
        $this->service->upload('/nonexistent/file.pdf', 'file.pdf');
    }
}
