<?php
// session.php – Track active sessions and clean up old videos
header('Content-Type: application/json; charset=UTF-8');

$videoId = $_REQUEST['videoId'] ?? '';
$action  = $_REQUEST['action'] ?? '';

if (!preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId)) {
    echo json_encode(['error' => 'Invalid video ID']);
    exit;
}
if (!in_array($action, ['start', 'end'])) {
    echo json_encode(['error' => 'Invalid action']);
    exit;
}

$sessionsFile = __DIR__ . '/sessions.json';
$sessions = [];
if (file_exists($sessionsFile)) {
    $json = file_get_contents($sessionsFile);
    $sessions = json_decode($json, true) ?: [];
}

$now = time();
// Update session status
if ($action === 'start') {
    $sessions[$videoId] = ['active' => true,  'lastAccess' => $now];
} elseif ($action === 'end') {
    if (isset($sessions[$videoId])) {
        $sessions[$videoId]['active'] = false;
        $sessions[$videoId]['lastAccess'] = $now;
    }
}

// Cleanup old videos (inactive for >5 minutes)
$videosDir = __DIR__ . '/videos';
foreach ($sessions as $vid => $info) {
    if (!$info['active'] && ($now - $info['lastAccess'] > 300)) {
        $filePath = "$videosDir/{$vid}.mp4";
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        unset($sessions[$vid]);
    }
}

// Write updated sessions back to file
file_put_contents($sessionsFile, json_encode($sessions));

echo json_encode(['status' => 'ok']);
?>
