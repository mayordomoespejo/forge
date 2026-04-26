<?php

declare(strict_types=1);

namespace Forge\Contracts;

interface StorageService extends AnalyzableService
{
    /**
     * Uploads a local file and returns its public URL.
     *
     * @throws \RuntimeException when upload fails
     */
    public function upload(string $localPath, string $remoteName): string;
}
