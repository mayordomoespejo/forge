<?php

declare(strict_types=1);

namespace Forge;

use Forge\Services\AppInsightsService;
use Forge\Services\BlobStorageService;
use Forge\Services\ChatService;
use Forge\Services\ComputerVisionService;
use Forge\Services\DocumentService;
use Forge\Services\HealthcareNerService;
use Forge\Services\LanguageService;
use Forge\Services\SearchService;
use Forge\Services\SpeechService;
use Forge\Services\VideoIndexerService;
use Forge\Pipeline\IntelligencePipeline;

class ContentAnalyzer
{
    private ChatService           $chat;
    private LanguageService       $language;
    private DocumentService       $document;
    private IntelligencePipeline  $pipeline;
    private SearchService         $search;
    private SpeechService         $speech;
    private HealthcareNerService  $healthcare;
    private VideoIndexerService   $video;
    private BlobStorageService    $blob;
    private AppInsightsService    $insights;
    private ComputerVisionService $vision;

    public function __construct()
    {
        $this->chat       = new ChatService();
        $this->language   = new LanguageService();
        $this->document   = new DocumentService();
        $this->pipeline   = new IntelligencePipeline();
        $this->search     = new SearchService();
        $this->speech     = new SpeechService();
        $this->healthcare = new HealthcareNerService();
        $this->video      = new VideoIndexerService();
        $this->blob       = new BlobStorageService();
        $this->insights   = new AppInsightsService();
        $this->vision     = new ComputerVisionService();
    }

    /**
     * Analyses content of any supported type and returns structured insights.
     *
     * Dispatches to the appropriate private method based on $type, runs the
     * intelligence pipeline, optionally persists the result to Azure AI Search
     * and Blob Storage, and tracks telemetry via Application Insights.
     *
     * @param  string               $type     Content type: 'text' | 'image' | 'document' | 'audio' | 'video'
     * @param  string               $content  Plain text input (used when type = 'text')
     * @param  string               $filePath Absolute path to the file (used when type != 'text')
     * @param  array<string, mixed> $options  Optional parameters: doc_model, speech_language, medical_mode, query_fields
     * @return array<string, mixed>           Structured analysis result, or ['type' => ..., 'error' => ...] on failure
     */
    public function analyze(string $type, string $content = '', string $filePath = '', array $options = []): array
    {
        try {
            $startTime = microtime(true);

            $result = match ($type) {
                'text'     => (function () use ($content, $options) {
                    $langAnalysis = $this->language->analyze($content);
                    $result = [
                        'type'     => 'text',
                        'content'  => $content,
                        'analysis' => $langAnalysis,
                    ];
                    try {
                        $result['pipeline'] = $this->pipeline->run($content, $langAnalysis['entities'] ?? []);
                    } catch (\Throwable) {
                        $result['pipeline'] = [];
                    }
                    $healthEntities = [];
                    if (!empty($options['medical_mode'])) {
                        try { $healthEntities = $this->healthcare->analyze($content); } catch (\Throwable) {}
                    }
                    $result['health_entities'] = $healthEntities;
                    return $result;
                })(),
                'image'    => $this->analyzeImage($filePath),
                'document' => $this->analyzeDocument($filePath, $options),
                'audio'    => $this->analyzeAudio($filePath, $options),
                'video'    => $this->analyzeVideo($filePath, $options),
                default    => throw new \InvalidArgumentException("Unknown type: {$type}"),
            };

            if (!empty($result['pipeline']['summary'])) {
                try {
                    $this->search->save(uniqid('forge_', true), $result);
                } catch (\Throwable) {}
            }

            // Upload to Blob Storage if configured
            if ($filePath !== '' && $this->blob->isConfigured()) {
                try {
                    $blobName = uniqid('forge_', true) . '.' . pathinfo($filePath, PATHINFO_EXTENSION);
                    $blobUrl  = $this->blob->upload($filePath, $blobName);
                    $result['blob_url'] = $blobUrl;
                } catch (\Throwable) {}
            }

            // Track to App Insights
            $duration = round((microtime(true) - $startTime) * 1000);
            $this->insights->trackEvent('AnalysisCompleted', [
                'type'        => $type,
                'durationMs'  => $duration,
                'hasPipeline' => !empty($result['pipeline']) ? 'true' : 'false',
            ]);

            return $result;
        } catch (\Throwable $e) {
            $this->insights->trackException($e);
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

        $cvAnalysis = [];
        if ($this->vision->isConfigured()) {
            try {
                $cvAnalysis = $this->vision->analyze($filePath);
            } catch (\Throwable) {}
        }

        $language = [];
        if (!empty($imageAnalysis['description'])) {
            try {
                $language = $this->language->analyze($imageAnalysis['description']);
            } catch (\Throwable) {
                $language = [];
            }
        }

        $result = [
            'type'        => 'image',
            'file'        => basename($filePath),
            'analysis'    => $imageAnalysis,
            'language'    => $language,
            'cv_analysis' => $cvAnalysis,
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
    private function analyzeDocument(string $filePath, array $options = []): array
    {
        $docModel    = $options['doc_model']    ?? 'prebuilt-read';
        $queryFields = $options['query_fields'] ?? [];
        $doc         = $this->document->extract($filePath, $docModel, $queryFields);

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
    private function analyzeAudio(string $filePath, array $options = []): array
    {
        $language   = $options['speech_language'] ?? 'en-US';
        $transcript = $this->speech->transcribe($filePath, $language);

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

    /**
     * @return array<string, mixed>
     */
    private function analyzeVideo(string $filePath, array $options = []): array
    {
        $insights = $this->video->index($filePath);

        $langAnalysis = [];
        if ($insights['transcript'] !== '') {
            try { $langAnalysis = $this->language->analyze($insights['transcript']); } catch (\Throwable) {}
        }

        $pipeline = [];
        if ($insights['transcript'] !== '') {
            try { $pipeline = $this->pipeline->run($insights['transcript'], $langAnalysis['entities'] ?? []); } catch (\Throwable) {}
        }

        return [
            'type'     => 'video',
            'file'     => basename($filePath),
            'analysis' => $insights,
            'language' => $langAnalysis,
            'pipeline' => $pipeline,
        ];
    }
}
