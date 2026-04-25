<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

if ($uri === '/' && $method === 'GET') {
    require __DIR__ . '/views/home.php';

} elseif ($uri === '/analyze' && $method === 'POST') {

    $analyzer = new Forge\ContentAnalyzer();

    try {
        if (!empty($_FILES['file']['name']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $ext  = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
            $dest = __DIR__ . '/uploads/' . uniqid('forge_', true) . '.' . $ext;

            if (!move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
                throw new \RuntimeException('Failed to move uploaded file.');
            }

            $type   = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']) ? 'image' : 'document';
            $result = $analyzer->analyze($type, '', $dest);
        } else {
            $text = trim($_POST['text'] ?? '');
            if ($text === '') {
                header('Location: /');
                exit;
            }
            $result = $analyzer->analyze('text', $text);
        }

        $_SESSION['result'] = $result;
    } catch (\Throwable $e) {
        $_SESSION['result'] = [
            'type'  => 'error',
            'error' => $e->getMessage(),
        ];
    }

    header('Location: /results');
    exit;

} elseif ($uri === '/results' && $method === 'GET') {

    if (empty($_SESSION['result'])) {
        header('Location: /');
        exit;
    }

    $result = $_SESSION['result'];
    require __DIR__ . '/views/results.php';

} else {
    http_response_code(404);
    echo '404 Not Found';
}
