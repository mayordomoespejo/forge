<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

header('Content-Type: application/json');

$jobId = $_SESSION['forge_job'] ?? '';

if ($jobId === '') {
    echo json_encode(['status' => 'unknown']);
    exit;
}

$queue = new Forge\Queue\JobQueue();
$job   = $queue->status($jobId);

if ($job === null) {
    echo json_encode(['status' => 'unknown']);
    exit;
}

$status = $job['status'] ?? 'unknown';

if ($status === 'completed') {
    $_SESSION['result']    = $job['result'];
    unset($_SESSION['forge_job']);
    echo json_encode(['status' => 'completed']);
    exit;
}

if ($status === 'failed') {
    $_SESSION['result'] = ['type' => 'error', 'error' => $job['error'] ?? 'Job failed.'];
    unset($_SESSION['forge_job']);
    echo json_encode(['status' => 'failed', 'error' => $job['error'] ?? 'Job failed.']);
    exit;
}

echo json_encode(['status' => $status]);
