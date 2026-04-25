<?php

namespace Forge;

use Forge\Services\ChatService;
use Forge\Services\DocumentService;
use Forge\Services\LanguageService;
use Forge\Services\SearchService;
use Forge\Services\SpeechService;
use Forge\Pipeline\IntelligencePipeline;

class ContentAnalyzer
{
    private ChatService          $chat;
    private LanguageService      $language;
    private DocumentService      $document;
    private IntelligencePipeline $pipeline;
    private SearchService        $search;
    private SpeechService        $speech;

    public function __construct()
    {
        $this->chat     = new ChatService();
        $this->language = new LanguageService();
        $this->document = new DocumentService();
        $this->pipeline = new IntelligencePipeline();
        $this->search   = new SearchService();
        $this->speech   = new SpeechService();
    }

    /**
     * @param  string $type     'text' | 'image' | 'document' | 'audio'
     * @param  string $content  Plain text (for type=text)
     * @param  string $filePath Absolute path to file (for type=image|document|audio)
     * @return array<string, mixed>
     */
    public function analyze(string $type, string $content = '', string $filePath = ''): array
    {
        try {
            $result = match($type) {
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
                'audio'    => $this->analyzeAudio($filePath),
                default    => throw new \InvalidArgumentException("Unknown type: {$type}"),
            };

            if (!empty($result['pipeline']['summary'])) {
                try {
                    $this->search->save(uniqid('forge_', true), $result);
                } catch (\Throwable) {}
            }

            return $result;
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

    /**
     * @return array<string, mixed>
     */
    private function analyzeAudio(string $filePath): array
    {
        $transcript = $this->speech->transcribe($filePath);
        $langAnalysis = [];
        if ($transcript !== '') {
            try {
                $langAnalysis = $this->language->analyze($transcript);
            } catch (\Throwable) {}
        }
        $pipeline = [];
        if ($transcript !== '') {
            try {
                $pipeline = $this->pipeline->run($transcript, $langAnalysis['entities'] ?? []);
            } catch (\Throwable) {}
        }
        return [
            'type'     => 'audio',
            'file'     => basename($filePath),
            'content'  => $transcript,
            'analysis' => $langAnalysis,
            'pipeline' => $pipeline,
        ];
    }
}
