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
                'image'    => $this->analyzeImage($filePath),
                'document' => $this->analyzeDocument($filePath),
                default    => throw new \InvalidArgumentException("Unknown type: {$type}"),
            };
        } catch (\Throwable $e) {
            return [
                'type'  => $type,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function analyzeImage(string $filePath): array
    {
        $imageAnalysis = $this->chat->analyzeImage($filePath);

        $language = [];
        if (!empty($imageAnalysis['description'])) {
            try {
                $language = $this->language->analyze($imageAnalysis['description']);
            } catch (\Throwable) {
                $language = [];
            }
        }

        return [
            'type'     => 'image',
            'file'     => basename($filePath),
            'analysis' => $imageAnalysis,
            'language' => $language,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function analyzeDocument(string $filePath): array
    {
        $doc = $this->document->extract($filePath);

        $language = [];
        if (!empty($doc['content'])) {
            try {
                $language = $this->language->analyze(mb_substr($doc['content'], 0, 5000));
            } catch (\Throwable) {
                $language = [];
            }
        }

        return [
            'type'     => 'document',
            'file'     => basename($filePath),
            'analysis' => $doc,
            'language' => $language,
        ];
    }
}
