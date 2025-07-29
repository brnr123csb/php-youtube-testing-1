<?php
function fetchDescription($videoID) {
    $videoURL = "https://www.youtube.com/watch?v=" . $videoID;
    $html = @file_get_contents($videoURL);
    if (!$html) {
        return "Failed to load video page.";
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

            return nl2br($linked);
        }
    }

    return "Description not found.";
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'description' && isset($_GET['videoID'])) {
    $videoID = $_GET['videoID'];
    if (!preg_match('/^[a-zA-Z0-9_-]{11}$/', $videoID)) {
        echo json_encode(['error' => 'Invalid video ID.']);
        exit;
    }
    $desc = fetchDescription($videoID);
    echo json_encode(['description' => $desc]);
    exit;
}

$videoID = $_GET['videoID'] ?? null;
$mode = $_GET['mode'] ?? 'local';
$useLocal = $mode === 'local';
$error = null;
$youtubeLink = null;
$currentPageLink = null;
$localPath = "videos/$videoID.mp4";

if (!$videoID || !preg_match('/^[a-zA-Z0-9_-]{11}$/', $videoID)) {
    $error = "Invalid or missing video ID.";
} else {
    $youtubeLink = "https://www.youtube.com/watch?v=" . htmlspecialchars($videoID);
    $currentPageLink = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

    if ($useLocal && !file_exists($localPath)) {
        @mkdir('videos', 0755, true);
        $escapedID = escapeshellarg("https://www.youtube.com/watch?v=$videoID");
        $command = "yt-dlp -f mp4 -o 'videos/$videoID.%(ext)s' $escapedID";
        exec($command);
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

        #video-container {
            min-height: 360px;
            position: relative;
        }

        #loading-placeholder {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #888;
            font-style: italic;
            user-select: none;
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
            min-height: 80px;
            position: relative;
        }

        #description h3 {
            margin: 0 0 0.75rem 0;
            font-size: 1.1rem;
            color: #f1f1f1;
        }

        #description p {
            margin: 0;
            color: #ccc;
        }

        .loading {
            color: #888;
            font-style: italic;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .error {
            color: #ff4d4d;
            margin-top: 1rem;
            font-weight: bold;
        }

        a.back {
            display: inline-block;
            margin-top: 1rem;
            color: #ff4d4d;
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s ease;
        }

        a.back:hover {
            text-decoration: underline;
            color: #ff1a1a;
        }

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

        .copy-btn:hover {
            background: #1565c0;
            transform: translateY(-1px);
        }

        .copy-buttons {
            margin-top: 1.5rem;
        }

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

        #toast.show {
            visibility: visible;
            opacity: 1;
            bottom: 50px;
        }
    </style>
</head>
<body>
    <h1>Viewer v1</h1>

    <?php if ($error): ?>
        <p class="error"><?= $error ?></p>
    <?php else: ?>
        <div id="video-container">
            <div id="loading-placeholder">Loading video…</div>
            <!-- Video element will be inserted here by JS -->
        </div>

        <div class="copy-buttons">
            <a class="copy-btn" href="?videoID=<?= $videoID ?>&mode=local">View Local</a>
            <a class="copy-btn" href="?videoID=<?= $videoID ?>&mode=embed">View Embed</a>
            <button class="copy-btn" onclick="copyText('<?= $youtubeLink ?>')">Copy YouTube Link</button>
            <button class="copy-btn" onclick="copyText('<?= $currentPageLink ?>')">Copy Page Link</button>
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
        const localPath = <?= json_encode($localPath) ?>;
        const videoContainer = document.getElementById('video-container');
        const loadingPlaceholder = document.getElementById('loading-placeholder');
        const descriptionEl = document.querySelector('#description p');

        function fetchDescription() {
            fetch(`?ajax=description&videoID=${encodeURIComponent(videoID)}`)
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
            if (useLocal && <?= json_encode(file_exists($localPath)) ?>) {
                videoHTML = `
                    <video controls preload="metadata">
                        <source src="${localPath}" type="video/mp4">
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

        window.addEventListener('DOMContentLoaded', () => {
            // Insert video with slight delay so page renders first
            setTimeout(() => {
                insertVideo();
            }, 100); // 100ms delay for minimal blocking but less intrusive

            fetchDescription();
        });
    </script>
</body>
</html>
