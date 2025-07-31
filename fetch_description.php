<?php
header('Content-Type: application/json');

function logError($msg) {
    $logFile = __DIR__ . '/log.txt';
    $timestamp = date('[Y-m-d H:i:s]');
    file_put_contents($logFile, "$timestamp $msg\n", FILE_APPEND);
}

$videoID = $_GET['videoID'] ?? '';
if (!preg_match('/^[a-zA-Z0-9_-]{11}$/', $videoID)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid video ID']);
    exit;
}

$videoURL = "https://www.youtube.com/watch?v=" . $videoID;
$html = @file_get_contents($videoURL);
if (!$html) {
    logError("Failed to fetch video page for ID: $videoID");
    echo json_encode(['error' => 'Failed to load video page.']);
    exit;
}

if (preg_match('/ytInitialPlayerResponse\s*=\s*({.+?});<\/script>/', $html, $matches)) {
    $json = $matches[1];
    $data = json_decode($json, true);
    if (isset($data['videoDetails']['shortDescription'])) {
        $rawDescription = $data['videoDetails']['shortDescription'];
        $escaped = htmlspecialchars($rawDescription);
        $linked = preg_replace(
            '/(https?:\/\/[^\s<]+)/i',
            '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>',
            $escaped
        );
        echo json_encode(['description' => nl2br($linked)]);
        exit;
    }
}

logError("Description not found for video ID: $videoID");
echo json_encode(['error' => 'Description not found.']);
