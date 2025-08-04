<?php
// viewer.php – Display a YouTube video (embed or local playback)
$videoId = isset($_GET['id']) ? trim($_GET['id']) : '';
$mode = isset($_GET['mode']) ? trim($_GET['mode']) : 'embed';

// Validate video ID format (11 characters, alphanumeric + - or _)
if (!preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId)) {
    http_response_code(400);
    echo "<p>Invalid video ID.</p>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Video Viewer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
body {
  font-family: Arial, sans-serif;
  margin: 20px;
  background: linear-gradient(135deg, #0f0f0f, #1a001a);
  color: #f0eaff;
}

h1 {
  font-size: 1.2em;
  color: #f5e146;
  text-shadow: 0 0 8px #f5e14680;
}

#playerContainer {
  text-align: center;
  margin-bottom: 20px;
}

#description {
  white-space: pre-wrap;
  border-top: 1px solid #8a2be2;
  padding-top: 10px;
  color: #e0d0ff;
  background-color: #1c1c1c;
  padding: 12px;
  border-radius: 6px;
  box-shadow: 0 0 10px #8a2be240 inset;
}

#toast {
  visibility: hidden;
  position: fixed;
  bottom: 20px;
  left: 50%;
  transform: translateX(-50%);
  background: #8a2be2;
  color: #fff;
  padding: 10px 20px;
  border-radius: 8px;
  box-shadow: 0 0 12px #8a2be280;
  font-weight: bold;
}

#toast.show {
  visibility: visible;
}
    </style>
</head>
<body>
    <h1>Video ID: <?php echo htmlspecialchars($videoId, ENT_QUOTES); ?></h1>
    <div id="playerContainer">
    <?php if ($mode === 'embed'): ?>
        <!-- Embed mode: YouTube iframe -->
        <iframe width="560" height="315"
            src="https://www.youtube.com/embed/<?php echo $videoId; ?>?autoplay=0"
            frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"
            allowfullscreen>
        </iframe>
    <?php elseif ($mode === 'local'): ?>
        <!-- Local mode: Download via yt-dlp and serve -->
        <?php
        $videosDir = __DIR__ . '/videos';
        if (!file_exists($videosDir)) {
            mkdir($videosDir, 0777, true);
        }
        $videoFile = "$videosDir/{$videoId}.mp4";
        if (!file_exists($videoFile)) {
            // Attempt to download the video using yt-dlp
            $youtubeUrl = "https://www.youtube.com/watch?v={$videoId}";
            // Check yt-dlp installation
            $ytCheck = shell_exec('which yt-dlp');
            if (!$ytCheck) {
                echo "<p>Error: yt-dlp not found on server.</p>";
            } else {
                // Download to videos/VIDEOID.mp4
                $cmd = "yt-dlp -f mp4 -o " . escapeshellarg("{$videosDir}/{$videoId}.%(ext)s") . " " . escapeshellarg($youtubeUrl);
                exec($cmd, $output, $status);
                if ($status !== 0) {
                    echo "<p>Error downloading video (status $status).</p>";
                }
            }
        }
        // If download succeeded or file already exists, embed video tag
        if (file_exists($videoFile)) {
            $videoUrl = "videos/{$videoId}.mp4";
            echo "<video width=\"100%\" controls>
                    <source src=\"$videoUrl\" type=\"video/mp4\">
                    Your browser does not support the video tag.
                  </video>";
        } else {
            echo "<p>Unable to retrieve video.</p>";
        }
        ?>
    <?php else: ?>
        <p>Unknown mode.</p>
    <?php endif; ?>
    </div>
    <div>
        <button id="copyLink">Copy Video Link</button>
        <div id="toast">Link copied to clipboard!</div>
    </div>
    <div id="description">Loading description...</div>

    <script>
    // Session tracking using Beacon API
    (function() {
        const videoId = '<?php echo $videoId; ?>';
        // On page load, mark session start
        window.addEventListener('load', () => {
            navigator.sendBeacon('session.php?action=start&videoId=' + encodeURIComponent(videoId));
        });
        // On page unload, mark session end
        window.addEventListener('unload', () => {
            navigator.sendBeacon('session.php?action=end&videoId=' + encodeURIComponent(videoId));
        });
    })();

    // Copy link button functionality with toast notification
    document.getElementById('copyLink').addEventListener('click', function() {
        navigator.clipboard.writeText(window.location.href).then(() => {
            const toast = document.getElementById('toast');
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 2000);
        });
    });

    // Fetch video description asynchronously and display
    (async function() {
        try {
            const res = await fetch('description.php?id=<?php echo $videoId; ?>');
            const data = await res.json();
            if (data.description) {
                document.getElementById('description').innerHTML = data.description;
            } else {
                document.getElementById('description').textContent = 'Description not available.';
            }
        } catch (err) {
            document.getElementById('description').textContent = 'Failed to load description.';
        }
    })();
    </script>
</body>
</html>
