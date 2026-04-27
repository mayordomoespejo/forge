<?php

declare(strict_types=1);

// Let the built-in PHP server serve static files directly
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
}

session_start();

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

if ($uri === '/' && $method === 'GET') {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    require __DIR__ . '/views/home.php';

} elseif ($uri === '/analyze' && $method === 'POST') {

    $submittedToken = $_POST['_csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $submittedToken)) {
        $_SESSION['result'] = ['type' => 'error', 'error' => 'Invalid request. Please try again.'];
        header('Location: /results');
        exit;
    }

    $rateLimiter = new Forge\Services\RateLimiter(20, 3600);
    $clientIp    = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (!$rateLimiter->allow($clientIp)) {
        $_SESSION['result'] = [
            'type'  => 'error',
            'error' => 'Rate limit exceeded. You can analyze up to 20 files per hour. Please try again later.',
        ];
        header('Location: /results');
        exit;
    }

    $analyzer = new Forge\ContentAnalyzer();

    try {
        $options = [
            'doc_model'       => $_POST['doc_model']       ?? 'prebuilt-read',
            'speech_language' => $_POST['speech_language'] ?? 'en-US',
            'medical_mode'    => !empty($_POST['medical_mode']),
            'query_fields'    => array_filter(array_map('trim', explode(',', $_POST['query_fields'] ?? ''))),
        ];

        $asyncTypes = ['video', 'audio'];

        if (!empty($_FILES['file']['name']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $ext  = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
            $dest = __DIR__ . '/uploads/' . uniqid('forge_', true) . '.' . $ext;

            if (!move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
                throw new \RuntimeException('Failed to move uploaded file.');
            }

            // Validate actual file content (not just extension)
            $finfo    = new \finfo(FILEINFO_MIME_TYPE);
            $realMime = $finfo->file($dest);
            $allowed  = [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                'application/pdf',
                'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/flac', 'audio/mp4', 'audio/x-m4a', 'video/webm',
                'video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/x-matroska',
            ];

            if (!in_array($realMime, $allowed, true)) {
                @unlink($dest);
                throw new \RuntimeException('File type not allowed: ' . $realMime);
            }

            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $type = 'image';
            } elseif (in_array($ext, ['mp3', 'wav', 'ogg', 'flac', 'm4a', 'webm'])) {
                $type = 'audio';
            } elseif (in_array($ext, ['mp4', 'mov', 'avi', 'mkv', 'webm'])) {
                $type = 'video';
            } else {
                $type = 'document';
            }

            if (in_array($type, $asyncTypes, true)) {
                $queue  = new Forge\Queue\JobQueue();
                $jobId  = $queue->enqueue($type, $dest, $options);
                $_SESSION['forge_job'] = $jobId;
                unset($_SESSION['result']);
            } else {
                $result = $analyzer->analyze($type, '', $dest, $options);
                $_SESSION['result'] = $result;
            }
        } else {
            $text = trim($_POST['text'] ?? '');
            if ($text === '') {
                header('Location: /');
                exit;
            }
            $result = $analyzer->analyze('text', $text, '', $options);
            $_SESSION['result'] = $result;
        }
    } catch (\Throwable $e) {
        $_SESSION['result'] = [
            'type'  => 'error',
            'error' => $e->getMessage(),
        ];
    }

    header('Location: /results');
    exit;

} elseif ($uri === '/results' && $method === 'GET') {

    if (empty($_SESSION['result']) && empty($_SESSION['forge_job'])) {
        header('Location: /');
        exit;
    }

    $result = $_SESSION['result'] ?? [];
    require __DIR__ . '/views/results.php';

} elseif ($uri === '/history' && $method === 'GET') {
    require __DIR__ . '/views/history.php';

} elseif (str_starts_with($uri, '/ajax/') && $method === 'GET') {

    $script = __DIR__ . $uri . '.php';
    if (is_file($script)) {
        require $script;
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Endpoint not found.']);
    }

} elseif (str_starts_with($uri, '/ajax/') && $method === 'POST') {

    $script = __DIR__ . $uri . '.php';
    if (is_file($script)) {
        require $script;
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Endpoint not found.']);
    }

} else {
    http_response_code(404);
    echo '404 Not Found';
}
