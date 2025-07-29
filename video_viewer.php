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
            return nl2br(htmlspecialchars($data['videoDetails']['shortDescription']));
        }
    }

    return "Description not found.";
}

$videoID = $_GET['videoID'] ?? null;
$mode = $_GET['mode'] ?? 'local';
$useLocal = $mode === 'local';
$error = null;
$description = null;
$youtubeLink = null;
$currentPageLink = null;
$localPath = "videos/$videoID.mp4";

if (!$videoID || !preg_match('/^[a-zA-Z0-9_-]{11}$/', $videoID)) {
    $error = "Invalid or missing video ID.";
} else {
    $description = fetchDescription($videoID);
    $youtubeLink = "https://www.youtube.com/watch?v=" . htmlspecialchars($videoID);
    $currentPageLink = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

    if ($useLocal && !file_exists($localPath)) {
        // Ensure the "videos" directory exists and is writable
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
    <title>YouTube Viewer</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 700px;
            margin: 2rem auto;
            padding: 1rem;
            text-align: center;
        }
        iframe, video {
            margin-top: 2rem;
            width: 100%;
            height: 360px;
            border: none;
        }
        #description {
            margin-top: 1rem;
            text-align: left;
            background: #f4f4f4;
            padding: 0.8rem 1rem;
            border-radius: 6px;
            font-size: 0.9rem;
            line-height: 1.4;
            box-shadow: 0 0 6px rgba(0,0,0,0.05);
        }
        #description h3 {
            margin: 0 0 0.5rem 0;
            font-size: 1rem;
            color: #333;
        }
        #description p {
            margin: 0;
        }
        .error {
            color: red;
            margin-top: 1rem;
        }
        a.back {
            display: inline-block;
            margin-top: 1rem;
            color: #cc0000;
            text-decoration: none;
            font-weight: bold;
        }
        a.back:hover {
            text-decoration: underline;
        }
        .copy-btn {
            margin: 1rem 0.5rem 0 0.5rem;
            padding: 0.6rem 1.2rem;
            font-size: 0.95rem;
            background: #0073e6;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
        }
        .copy-btn:hover {
            background: #005bb5;
        }
        .copy-buttons {
            margin-top: 1.5rem;
        }
        #toast {
            visibility: hidden;
            min-width: 180px;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 4px;
            padding: 0.7rem 1rem;
            position: fixed;
            z-index: 1000;
            left: 50%;
            bottom: 30px;
            transform: translateX(-50%);
            font-size: 0.9rem;
            opacity: 0;
            transition: opacity 0.3s ease, bottom 0.3s ease;
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
        <?php if ($useLocal && file_exists($localPath)): ?>
            <video controls>
                <source src="<?= $localPath ?>" type="video/mp4">
                Your browser does not support the video tag.
            </video>
        <?php else: ?>
            <iframe
                src="https://www.youtube.com/embed/<?= htmlspecialchars($videoID) ?>"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen
                title="YouTube video player"
            ></iframe>
        <?php endif; ?>

        <div class="copy-buttons">
            <a class="copy-btn" href="?videoID=<?= $videoID ?>&mode=local">View Local</a>
            <a class="copy-btn" href="?videoID=<?= $videoID ?>&mode=embed">View Embed</a>
            <button class="copy-btn" onclick="copyText('<?= $youtubeLink ?>')">Copy YouTube Link</button>
            <button class="copy-btn" onclick="copyText('<?= $currentPageLink ?>')">Copy Page Link</button>
        </div>

        <div id="description">
            <h3>Description:</h3>
            <p><?= $description ?></p>
        </div>
    <?php endif; ?>

    <div id="toast">Copied to clipboard!</div>

    <script>
        function copyText(text) {
            const temp = document.createElement("textarea");
            temp.value = text;
            document.body.appendChild(temp);
            temp.select();
            document.execCommand("copy");
            document.body.removeChild(temp);
            showToast("Copied to clipboard!");
        }

        function showToast(message) {
            const toast = document.getElementById("toast");
            toast.textContent = message;
            toast.classList.add("show");
            setTimeout(() => {
                toast.classList.remove("show");
            }, 2000);
        }
    </script>
</body>
</html>
