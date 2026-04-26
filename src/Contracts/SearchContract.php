<?php

declare(strict_types=1);

namespace Forge\Contracts;

interface SearchContract extends AnalyzableService
{
    /**
     * Persists an analysis result to the search index.
     *
     * @param array<string, mixed> $result
     */
    public function save(string $id, array $result): void;

    /**
     * Searches the index and returns matching documents.
     *
     * @return array<int, array<string, string>>
     */
    public function search(string $query, int $top = 3): array;

    /**
     * Returns the most recent analyses ordered by timestamp descending.
     *
     * @return array<int, array<string, string>>
     */
    public function all(int $top = 20): array;
}
