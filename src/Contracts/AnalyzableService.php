<?php

declare(strict_types=1);

namespace Forge\Contracts;

interface AnalyzableService
{
    /**
     * Returns true if the service is configured with valid credentials.
     */
    public function isConfigured(): bool;
}
