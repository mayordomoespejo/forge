<?php

declare(strict_types=1);

namespace Forge\Services;

class BlobStorageService
{
    private string $account;
    private string $key;
    private string $container;
    private string $apiVersion = '2020-10-02';

    public function __construct()
    {
        $this->account   = $_ENV['AZURE_STORAGE_ACCOUNT']   ?? '';
        $this->key       = $_ENV['AZURE_STORAGE_KEY']       ?? '';
        $this->container = $_ENV['AZURE_STORAGE_CONTAINER'] ?? 'forge-uploads';
    }

    public function isConfigured(): bool
    {
        return $this->account !== '' && $this->key !== '';
    }

    /**
     * Uploads a local file to Azure Blob Storage and returns the blob URL.
     *
     * @throws \RuntimeException
     */
    public function upload(string $localPath, string $blobName): string
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Blob Storage not configured.');
        }

        $data        = file_get_contents($localPath);
        if ($data === false) {
            throw new \RuntimeException('Cannot read file: ' . $localPath);
        }

        $ext         = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));
        $contentType = $this->mimeFor($ext);
        $dateHeader  = gmdate('D, d M Y H:i:s T');
        $contentLen  = strlen($data);

        $canonHeaders = implode("\n", [
            'x-ms-blob-type:BlockBlob',
            'x-ms-date:' . $dateHeader,
            'x-ms-version:' . $this->apiVersion,
        ]);

        $canonResource = '/' . $this->account . '/' . $this->container . '/' . $blobName;

        $stringToSign = implode("\n", [
            'PUT',
            '',              // Content-Encoding
            '',              // Content-Language
            (string) $contentLen,
            '',              // Content-MD5
            $contentType,
            '',              // Date (using x-ms-date instead)
            '',              // If-Modified-Since
            '',              // If-Match
            '',              // If-None-Match
            '',              // If-Unmodified-Since
            '',              // Range
            $canonHeaders,
            $canonResource,
        ]);

        $sig = base64_encode(hash_hmac('sha256', $stringToSign, base64_decode($this->key), true));
        $auth = 'SharedKey ' . $this->account . ':' . $sig;

        $url = sprintf(
            'https://%s.blob.core.windows.net/%s/%s',
            $this->account, $this->container, rawurlencode($blobName)
        );

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Authorization: '    . $auth,
                'x-ms-date: '        . $dateHeader,
                'x-ms-version: '     . $this->apiVersion,
                'x-ms-blob-type: BlockBlob',
                'Content-Type: '     . $contentType,
                'Content-Length: '   . $contentLen,
            ],
        ]);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_exec($ch);
        curl_close($ch);

        if ($httpCode !== 201) {
            throw new \RuntimeException('Blob upload failed (HTTP ' . $httpCode . ').');
        }

        return sprintf(
            'https://%s.blob.core.windows.net/%s/%s',
            $this->account, $this->container, $blobName
        );
    }

    private function mimeFor(string $ext): string
    {
        return match($ext) {
            'pdf'        => 'application/pdf',
            'png'        => 'image/png',
            'jpg', 'jpeg'=> 'image/jpeg',
            'gif'        => 'image/gif',
            'webp'       => 'image/webp',
            'mp3'        => 'audio/mpeg',
            'wav'        => 'audio/wav',
            'ogg'        => 'audio/ogg',
            'mp4'        => 'video/mp4',
            'mov'        => 'video/quicktime',
            default      => 'application/octet-stream',
        };
    }
}
