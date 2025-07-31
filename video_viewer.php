<?php
function fetchDescription($videoID) {
    $videoURL = "https://www.youtube.com/watch?v=" . $videoID;
    $html = @file_get_contents($videoURL);
    if (!$html) return "Failed to load video page.";

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
            return nl2br($linked);
        }
    }
    return "Description not found.";
}

function logError($msg) {
    $logFile = __DIR__ . '/log.txt';
    $timestamp = date('[Y-m-d H:i:s]');
    file_put_contents($logFile, "$timestamp $msg\n", FILE_APPEND);
}

$videoID = $_GET['videoID'] ?? null;
$mode = $_GET['mode'] ?? 'local';
$useLocal = $mode === 'local';

$videoDir = __DIR__ . '/videos';
$sessionFile = __DIR__ . '/video_sessions.json'; // stores last session timestamps
$maxIdle = 300; // 5 minutes in seconds

// === Session Management and Cleanup ===
if (isset($_GET['session']) && isset($_GET['videoID'])) {
    $vid = $_GET['videoID'];
    if (!preg_match('/^[a-zA-Z0-9_-]{11}$/', $vid)) {
        http_response_code(400);
        exit('Invalid video ID');
    }

    $sessions = [];
    if (file_exists($sessionFile)) {
        $sessions = json_decode(file_get_contents($sessionFile), true);
        if (!is_array($sessions)) $sessions = [];
    }

    $now = time();
    $action = $_GET['session']; // 'start' or 'end'

    if ($action === 'start') {
        // Mark session start time for video
        $sessions[$vid] = ['lastActive' => $now, 'active' => true];
    } elseif ($action === 'end') {
        // Mark session ended (not active), keep lastActive time
        if (isset($sessions[$vid])) {
            $sessions[$vid]['active'] = false;
            $sessions[$vid]['lastActive'] = $now;
        }
    }

    // Cleanup files older than 5 mins from lastActive and not active
    foreach ($sessions as $v => $data) {
        $filePath = "$videoDir/$v.mp4";
        if (file_exists($filePath) && isset($data['lastActive']) && isset($data['active'])) {
            if (!$data['active'] && ($now - $data['lastActive'] > $maxIdle)) {
                unlink($filePath);
                unset($sessions[$v]);
                logError("Deleted video file after inactivity: $v.mp4");
            }
        }
    }

    // Save updated sessions
    file_put_contents($sessionFile, json_encode($sessions));
    exit('OK');
}

// === Main Viewer Logic ===
$error = null;
$youtubeLink = null;
$currentPageLink = null;
$localPath = null;
$localUrlPath = null;

if (!$videoID || !preg_match('/^[a-zA-Z0-9_-]{11}$/', $videoID)) {
    $error = "Invalid or missing video ID.";
    logError("Invalid video ID: " . var_export($videoID, true));
} else {
    $youtubeLink = "https://www.youtube.com/watch?v=" . htmlspecialchars($videoID);
    $currentPageLink = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

    if ($useLocal) {
        if (!is_dir($videoDir)) {
            if (!mkdir($videoDir, 0755, true)) {
                $error = "Failed to create videos directory.";
                logError("Cannot create videos directory at: $videoDir");
            }
        }

        if (!is_writable($videoDir)) {
            $error = "Videos directory is not writable.";
            logError("Videos directory not writable: $videoDir");
        } else {
            $localFilename = "$videoID.mp4";
            $localPath = "$videoDir/$localFilename";
            $localUrlPath = "videos/$localFilename";

            if (!file_exists($localPath)) {
                $ytBinary = 'yt-dlp';
                $ytCheck = trim(shell_exec("which $ytBinary 2>/dev/null"));
                if (!$ytCheck) {
                    $error = "yt-dlp not installed or not in PATH.";
                    logError("yt-dlp not found.");
                } else {
                    $escapedID = escapeshellarg("https://www.youtube.com/watch?v=$videoID");
                    $escapedOut = escapeshellarg($localPath);
                    $command = "$ytBinary -f mp4 -o $escapedOut $escapedID 2>&1";
                    $output = [];
                    $exitCode = 0;
                    exec($command, $output, $exitCode);

                    if ($exitCode !== 0 || !file_exists($localPath) || filesize($localPath) === 0) {
                        $error = "Failed to download video.";
                        logError("yt-dlp error (exit $exitCode):\n" . implode("\n", $output));
                        if (!file_exists($localPath)) logError("File not created: $localPath");
                        elseif (filesize($localPath) === 0) logError("File created but empty: $localPath");
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Your tube</title>
<style>
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        max-width: 700px;
        margin: 2rem auto;
        padding: 1.5rem;
        text-align: center;
        background-color: #121212;
        color: #e0e0e0;
    }
    iframe, video {
        margin-top: 2rem;
        width: 100%;
        height: 360px;
        border: none;
        border-radius: 8px;
        box-shadow: 0 0 10px rgba(0,0,0,0.6);
    }
    #video-container { min-height: 360px; position: relative; }
    #loading-placeholder {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        color: #888;
        font-style: italic;
    }
    #description {
        margin-top: 1rem;
        text-align: left;
        background: #1e1e1e;
        padding: 1rem 1.25rem;
        border-radius: 10px;
        font-size: 0.95rem;
        line-height: 1.5;
        box-shadow: 0 0 12px rgba(0, 0, 0, 0.3);
        border: 1px solid #2c2c2c;
    }
    #description h3 { margin-bottom: 0.75rem; font-size: 1.1rem; color: #f1f1f1; }
    #description p { margin: 0; color: #ccc; }
    .error { color: #ff4d4d; margin-top: 1rem; font-weight: bold; }
    .copy-btn {
        margin: 1rem 0.5rem 0 0.5rem;
        padding: 0.6rem 1.2rem;
        font-size: 0.95rem;
        background: #1e88e5;
        color: #ffffff;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        text-decoration: none;
        transition: background 0.3s ease, transform 0.2s ease;
    }
    .copy-btn:hover { background: #1565c0; transform: translateY(-1px); }
    .copy-buttons { margin-top: 1.5rem; }
    #toast {
        visibility: hidden;
        min-width: 180px;
        background-color: #2e2e2e;
        color: #fff;
        text-align: center;
        border-radius: 6px;
        padding: 0.7rem 1rem;
        position: fixed;
        z-index: 1000;
        left: 50%;
        bottom: 30px;
        transform: translateX(-50%);
        font-size: 0.9rem;
        opacity: 0;
        transition: opacity 0.3s ease, bottom 0.3s ease;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.6);
    }
    #toast.show { visibility: visible; opacity: 1; bottom: 50px; }
</style>
</head>
<body>
    <h1>Viewer v1</h1>

    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php else: ?>
        <div id="video-container">
            <div id="loading-placeholder">Loading video…</div>
        </div>

        <div class="copy-buttons">
            <a class="copy-btn" href="?videoID=<?= htmlspecialchars($videoID) ?>&mode=local">View Local</a>
            <a class="copy-btn" href="?videoID=<?= htmlspecialchars($videoID) ?>&mode=embed">View Embed</a>
            <button class="copy-btn" onclick="copyText('<?= htmlspecialchars($youtubeLink) ?>')">Copy YouTube Link</button>
            <button class="copy-btn" onclick="copyText('<?= htmlspecialchars($currentPageLink) ?>')">Copy Page Link</button>
        </div>

        <div id="description">
            <h3>Description:</h3>
            <p class="loading">Loading description...</p>
        </div>
    <?php endif; ?>

    <div id="toast">Copied to clipboard!</div>

<script>
    const videoID = <?= json_encode($videoID) ?>;
    const mode = <?= json_encode($mode) ?>;
    const useLocal = mode === 'local';
    const videoContainer = document.getElementById('video-container');
    const loadingPlaceholder = document.getElementById('loading-placeholder');
    const descriptionEl = document.querySelector('#description p');

    function fetchDescription() {
        fetch(`fetch_description.php?videoID=${encodeURIComponent(videoID)}`)
            .then(resp => resp.json())
            .then(data => {
                if (data.error) {
                    descriptionEl.textContent = data.error;
                    descriptionEl.classList.remove('loading');
                    descriptionEl.style.color = '#ff4d4d';
                } else {
                    descriptionEl.innerHTML = data.description;
                    descriptionEl.classList.remove('loading');
                    descriptionEl.style.color = '#ccc';
                }
            })
            .catch(() => {
                descriptionEl.textContent = "Failed to load description.";
                descriptionEl.classList.remove('loading');
                descriptionEl.style.color = '#ff4d4d';
            });
    }

    function insertVideo() {
        let videoHTML;
        if (useLocal) {
            videoHTML = `
                <video controls autoplay preload="metadata">
                    <source src="<?= htmlspecialchars($localUrlPath) ?>" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            `;
        } else {
            videoHTML = `
                <iframe
                    src="https://www.youtube.com/embed/${videoID}"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen
                    loading="lazy"
                    title="YouTube video player"
                ></iframe>
            `;
        }
        loadingPlaceholder.style.display = 'none';
        videoContainer.innerHTML = videoHTML;
    }

    function copyText(text) {
        navigator.clipboard.writeText(text).then(() => {
            showToast();
        });
    }

    function showToast() {
        const toast = document.getElementById('toast');
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 1800);
    }

    // Notify server of session start/end for video cleanup
    function notifySession(action) {
        if (!useLocal) return; // only track local mode

        navigator.sendBeacon(`?session=${action}&videoID=${encodeURIComponent(videoID)}`);
    }

    window.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            insertVideo();
            fetchDescription();
            notifySession('start');
        }, 100);
    });

    window.addEventListener('beforeunload', () => {
        notifySession('end');
    });

    // Fallback: also send 'end' after 5 minutes of inactivity if user does not close tab properly
    setTimeout(() => {
        notifySession('end');
    }, 5 * 60 * 1000); // 5 minutes

</script>
</body>
</html>
