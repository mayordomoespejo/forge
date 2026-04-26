<?php

namespace Forge;

use Forge\Services\ChatService;
use Forge\Services\LanguageService;
use Forge\Services\DocumentService;

class ContentAnalyzer
{
    private ChatService $chat;
    private LanguageService $language;
    private DocumentService $document;

    public function __construct()
    {
        $this->chat     = new ChatService();
        $this->language = new LanguageService();
        $this->document = new DocumentService();
    }

    /**
     * @param  string $type     'text' | 'image' | 'document'
     * @param  string $content  Plain text (for type=text)
     * @param  string $filePath Absolute path to file (for type=image|document)
     * @return array<string, mixed>
     */
    public function analyze(string $type, string $content = '', string $filePath = ''): array
    {
        try {
            return match($type) {
                'text'     => [
                    'type'     => 'text',
                    'content'  => $content,
                    'analysis' => $this->language->analyze($content),
                ],
                'image'    => [
                    'type'     => 'image',
                    'file'     => basename($filePath),
                    'analysis' => $this->chat->analyzeImage($filePath),
                ],
                'document' => [
                    'type'     => 'document',
                    'file'     => basename($filePath),
                    'analysis' => $this->document->extract($filePath),
                ],
                default    => throw new \InvalidArgumentException("Unknown type: {$type}"),
            };
        } catch (\Throwable $e) {
            return [
                'type'  => $type,
                'error' => $e->getMessage(),
            ];
        }
    }
}
