<?php

namespace Forge;

use Forge\Services\ChatService;
use Forge\Services\LanguageService;
use Forge\Services\DocumentService;
use Forge\Pipeline\IntelligencePipeline;

class ContentAnalyzer
{
    private ChatService $chat;
    private LanguageService $language;
    private DocumentService $document;
    private IntelligencePipeline $pipeline;

    public function __construct()
    {
        $this->chat     = new ChatService();
        $this->language = new LanguageService();
        $this->document = new DocumentService();
        $this->pipeline = new IntelligencePipeline();
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
                'text'     => (function () use ($content) {
                    $result = [
                        'type'     => 'text',
                        'content'  => $content,
                        'analysis' => $this->language->analyze($content),
                    ];
                    try {
                        $result['pipeline'] = $this->pipeline->run($content, $result['analysis']['entities'] ?? []);
                    } catch (\Throwable) {
                        $result['pipeline'] = [];
                    }
                    return $result;
                })(),
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

        $result = [
            'type'     => 'image',
            'file'     => basename($filePath),
            'analysis' => $imageAnalysis,
            'language' => $language,
        ];

        try {
            $result['pipeline'] = $this->pipeline->run(
                $imageAnalysis['description'] ?? '',
                $language['entities'] ?? []
            );
        } catch (\Throwable) {
            $result['pipeline'] = [];
        }

        return $result;
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

        $result = [
            'type'     => 'document',
            'file'     => basename($filePath),
            'analysis' => $doc,
            'language' => $language,
        ];

        try {
            $result['pipeline'] = $this->pipeline->run(
                mb_substr($doc['content'] ?? '', 0, 5000),
                $language['entities'] ?? []
            );
        } catch (\Throwable) {
            $result['pipeline'] = [];
        }

        return $result;
    }
}
