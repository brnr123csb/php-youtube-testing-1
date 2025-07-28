<?php
set_time_limit(0);

function fetch_url($url, $post = null, $headers = []) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_USERAGENT, $headers['User-Agent'] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 ' .
        '(KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_HEADER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$response, $httpCode];
}

function random_user_agent() {
    $versions = range(90, 115);
    $version = $versions[array_rand($versions)];
    return "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/{$version}.0.0.0 Safari/537.36";
}

function extract_yt_initial_data($html) {
    if (preg_match('/ytInitialData\s*=\s*({.+?});<\/script>/s', $html, $matches)) {
        return $matches[1];
    }
    return null;
}

function extract_innertube_key($html) {
    if (preg_match('/"INNERTUBE_API_KEY":"([^"]+)"/', $html, $matches)) {
        return $matches[1];
    }
    return null;
}

function extract_client_version($html) {
    if (preg_match('/"INNERTUBE_CLIENT_VERSION":"([^"]+)"/', $html, $matches)) {
        return $matches[1];
    }
    return null;
}

function parse_videos($items) {
    $videos = [];
    if (!is_array($items)) return $videos;

    foreach ($items as $item) {
        if (!is_array($item)) continue;

        if (isset($item['videoRenderer'])) {
            $vr = $item['videoRenderer'];
            $videoId = $vr['videoId'] ?? null;
            $titleRuns = $vr['title']['runs'] ?? [];
            $title = '';
            foreach ($titleRuns as $run) {
                $title .= $run['text'] ?? '';
            }
            $url = $videoId ? "https://www.youtube.com/watch?v=$videoId" : null;

            // Extract best thumbnail URL (pick the last highest res available)
            $thumbnails = $vr['thumbnail']['thumbnails'] ?? [];
            $thumbnailUrl = end($thumbnails)['url'] ?? null;

            $videos[] = [
                'title' => $title,
                'url' => $url,
                'thumbnail' => $thumbnailUrl,
                'videoId' => $videoId
            ];            
        } elseif (isset($item['itemSectionRenderer'])) {
            if (isset($item['itemSectionRenderer']['contents'])) {
                $videos = array_merge($videos, parse_videos($item['itemSectionRenderer']['contents']));
            }
        } elseif (isset($item['richItemRenderer'])) {
            $content = $item['richItemRenderer']['content'] ?? null;
            if ($content && isset($content['videoRenderer'])) {
                $vr = $content['videoRenderer'];
                $videoId = $vr['videoId'] ?? null;
                $titleRuns = $vr['title']['runs'] ?? [];
                $title = '';
                foreach ($titleRuns as $run) {
                    $title .= $run['text'] ?? '';
                }
                $url = $videoId ? "https://www.youtube.com/watch?v=$videoId" : null;

                $thumbnails = $vr['thumbnail']['thumbnails'] ?? [];
                $thumbnailUrl = end($thumbnails)['url'] ?? null;

                $videos[] = [
                    'title' => $title,
                    'url' => $url,
                    'thumbnail' => $thumbnailUrl,
                    'videoId' => $videoId
                ];                
            }
        }
    }
    return $videos;
}

function find_continuation_token($items) {
    if (!is_array($items)) return null;

    foreach ($items as $item) {
        if (isset($item['continuationItemRenderer'])) {
            return $item['continuationItemRenderer']['continuationEndpoint']['continuationCommand']['token'] ?? null;
        }
        // Recursively dive into arrays
        foreach ($item as $v) {
            if (is_array($v)) {
                $token = find_continuation_token([$v]);
                if ($token) return $token;
            }
        }
    }
    return null;
}

function extract_results_and_token($ytInitialData) {
    $results = [];
    $token = null;
    $jsonData = json_decode($ytInitialData, true);
    if (!$jsonData) return [$results, $token];

    $contents = $jsonData['contents']['twoColumnSearchResultsRenderer']['primaryContents']['sectionListRenderer']['contents'] ?? null;
    if (!$contents) return [$results, $token];

    $results = parse_videos($contents);
    $token = find_continuation_token($contents);

    return [$results, $token];
}

function fetch_continuation_results($innertubeKey, $continuationToken, $clientVersion, $clientName) {
    $url = "https://www.youtube.com/youtubei/v1/search?key=" . urlencode($innertubeKey);

    $payload = json_encode([
        "context" => [
            "client" => [
                "clientName" => $clientName,
                "clientVersion" => $clientVersion,
            ]
        ],
        "continuation" => $continuationToken
    ]);

    $userAgent = random_user_agent();

    $headers = [
        "Content-Type: application/json",
        "User-Agent: {$userAgent}",
        "Accept-Language: en-US,en;q=0.9",
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 400) {
        return [false, $httpCode];
    }

    return [$response, $httpCode];
}

$searchTerm = $_GET['search'] ?? '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>You tube</title>
<style>
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        margin: 20px;
        background: #f9f9f9;
        color: #222;
    }
    form {
        margin-bottom: 30px;
    }
    input[type=text] {
        width: 320px;
        padding: 8px;
        font-size: 1rem;
        border: 1px solid #ccc;
        border-radius: 4px;
    }
    button {
        padding: 8px 16px;
        font-size: 1rem;
        cursor: pointer;
        background-color: #cc0000;
        color: white;
        border: none;
        border-radius: 4px;
        margin-left: 8px;
        transition: background-color 0.3s ease;
    }
    button:hover {
        background-color: #b30000;
    }
    #results {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
    }
    .video {
        background: white;
        border-radius: 6px;
        box-shadow: 0 2px 6px rgb(0 0 0 / 0.1);
        width: 320px;
        padding: 10px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        transition: box-shadow 0.3s ease;
    }
    .video:hover {
        box-shadow: 0 6px 12px rgb(0 0 0 / 0.15);
    }
    .thumbnail {
        flex-shrink: 0;
        width: 120px;
        height: 67px;
        background: #eee;
        border-radius: 4px;
        object-fit: cover;
    }
    .info {
        flex-grow: 1;
    }
    .info a {
        font-weight: 600;
        color: #cc0000;
        text-decoration: none;
        font-size: 1rem;
        line-height: 1.2;
        display: block;
    }
    .info a:hover {
        text-decoration: underline;
    }
</style>
</head>
<body>
<h1>Search for Fun</h1>
<form method="get" action="">
    <input type="text" name="search" placeholder="Enter search term" value="<?=htmlspecialchars($searchTerm)?>" required />
    <button type="submit">Search</button>
</form>

<?php
if ($searchTerm) {
    $userAgent = random_user_agent();
    $headers = [
        "User-Agent: $userAgent",
        "Accept-Language: en-US,en;q=0.9",
    ];

    list($html, $httpCode) = fetch_url('https://www.youtube.com/results?search_query=' . urlencode($searchTerm), null, $headers);

    if (!$html || $httpCode >= 400) {
        echo '<p style="color:red; font-weight:bold;">Error: Failed to fetch YouTube search page. HTTP code: ' . $httpCode . '</p>';
        exit;
    }

    $ytInitialDataJson = extract_yt_initial_data($html);
    if (!$ytInitialDataJson) {
        echo '<p style="color:red; font-weight:bold;">Error: Unable to extract ytInitialData from YouTube page.</p>';
        exit;
    }

    $innertubeKey = extract_innertube_key($html);
    $clientVersion = extract_client_version($html);

    if (!$innertubeKey || !$clientVersion) {
        echo '<p style="color:red; font-weight:bold;">Error: Unable to extract INNERTUBE_API_KEY or CLIENT_VERSION.</p>';
        exit;
    }

    list($videos, $continuationToken) = extract_results_and_token($ytInitialDataJson);

    echo "<h2>Results for '" . htmlspecialchars($searchTerm) . "'</h2>";
    echo '<div id="results">';
    foreach ($videos as $v) {
        $thumb = $v['thumbnail'] ?? '';
        echo '<div class="video">';
        if ($thumb) {
            echo '<a href="video_viewer.php?videoID=' . urlencode($v['videoId']) . '" target="_blank" rel="noopener noreferrer">';
            echo '<img src="' . htmlspecialchars($thumb) . '" alt="Thumbnail" class="thumbnail" loading="lazy" />';
            echo '</a>';
        }
        echo '<div class="info">';
        echo '<a href="video_viewer.php?videoID=' . urlencode($v['videoId']) . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($v['title']) . '</a>';
        echo '</div></div>';
    }
    echo '</div>';

    $clientName = 'WEB';
$enable_parallel = true; // Set to false to disable parallel fetching
$max_continuations = 5;
$continuationTokens = [$continuationToken];
$allVideos = [];

if ($enable_parallel) {
    // Prepare multiple continuation tokens by walking through them linearly first
    for ($i = 0; $i < $max_continuations; $i++) {
        list($resp, $code) = fetch_continuation_results($innertubeKey, end($continuationTokens), $clientVersion, $clientName);
        if (!$resp || $code >= 400) break;
        $json = json_decode($resp, true);
        $items = $json['onResponseReceivedCommands'][0]['appendContinuationItemsAction']['continuationItems']
              ?? $json['onResponseReceivedActions'][0]['appendContinuationItemsAction']['continuationItems']
              ?? $json['onResponseReceivedEndpoints'][0]['appendContinuationItemsAction']['continuationItems']
              ?? null;
        if (!$items) break;
        $nextToken = find_continuation_token($items);
        if ($nextToken) $continuationTokens[] = $nextToken;
    }

    // Fetch all continuation tokens in parallel
    $multi = curl_multi_init();
    $handles = [];
    $results = [];

    foreach ($continuationTokens as $token) {
        $url = "https://www.youtube.com/youtubei/v1/search?key=" . urlencode($innertubeKey);
        $payload = json_encode([
            "context" => [
                "client" => [
                    "clientName" => $clientName,
                    "clientVersion" => $clientVersion,
                ]
            ],
            "continuation" => $token
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "User-Agent: " . random_user_agent(),
            "Accept-Language: en-US,en;q=0.9",
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_multi_add_handle($multi, $ch);
        $handles[] = $ch;
    }

    do {
        $status = curl_multi_exec($multi, $active);
        curl_multi_select($multi);
    } while ($active && $status == CURLM_OK);

    foreach ($handles as $ch) {
        $response = curl_multi_getcontent($ch);
        curl_multi_remove_handle($multi, $ch);
        curl_close($ch);

        $data = json_decode($response, true);
        $items = $data['onResponseReceivedCommands'][0]['appendContinuationItemsAction']['continuationItems']
              ?? $data['onResponseReceivedActions'][0]['appendContinuationItemsAction']['continuationItems']
              ?? $data['onResponseReceivedEndpoints'][0]['appendContinuationItemsAction']['continuationItems']
              ?? null;
        if ($items) {
            $allVideos = array_merge($allVideos, parse_videos($items));
        }
    }
    curl_multi_close($multi);
} else {
    for ($i = 0; $i < $max_continuations && $continuationToken; $i++) {
        list($contJsonStr, $httpCode) = fetch_continuation_results($innertubeKey, $continuationToken, $clientVersion, $clientName);
        if (!$contJsonStr || $httpCode >= 400) break;

        $contJson = json_decode($contJsonStr, true);
        $contContents = $contJson['onResponseReceivedCommands'][0]['appendContinuationItemsAction']['continuationItems']
                      ?? $contJson['onResponseReceivedActions'][0]['appendContinuationItemsAction']['continuationItems']
                      ?? $contJson['onResponseReceivedEndpoints'][0]['appendContinuationItemsAction']['continuationItems']
                      ?? null;
        if (!$contContents) break;

        $allVideos = array_merge($allVideos, parse_videos($contContents));
        $continuationToken = find_continuation_token($contContents);
        usleep(200000); // shorter delay
    }
}

// Render fetched videos from all continuation requests
foreach ($allVideos as $v) {
    $thumb = $v['thumbnail'] ?? '';
    echo '<div class="video">';
    if ($thumb) {
        echo '<a href="video_viewer.php?videoID=' . urlencode($v['videoId'] ?? '') . '" target="_blank" rel="noopener noreferrer">';
        echo '<img src="' . htmlspecialchars($thumb) . '" alt="Thumbnail" class="thumbnail" loading="lazy" />';
        echo '</a>';
    }
    echo '<div class="info">';
    echo '<a href="' . htmlspecialchars($v['url']) . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($v['title']) . '</a>';
    echo '</div></div>';
}
}
?>
<script>
document.addEventListener("DOMContentLoaded", () => {
    const loadMore = document.createElement("button");
    loadMore.textContent = "Load More";
    loadMore.style.display = "block";
    loadMore.style.margin = "30px auto";
    loadMore.style.padding = "10px 20px";
    loadMore.style.background = "#cc0000";
    loadMore.style.color = "#fff";
    loadMore.style.border = "none";
    loadMore.style.borderRadius = "4px";
    loadMore.style.cursor = "pointer";

    let page = 1;

    loadMore.addEventListener("click", () => {
        loadMore.disabled = true;
        loadMore.textContent = "Loading...";

        fetch("load_more.php?search=" + encodeURIComponent("{$searchTerm}") + "&page=" + (++page))
            .then(res => res.text())
            .then(html => {
                document.getElementById("results").insertAdjacentHTML("beforeend", html);
                loadMore.disabled = false;
                loadMore.textContent = "Load More";
            })
            .catch(err => {
                loadMore.textContent = "Failed to load";
            });
    });

    document.body.appendChild(loadMore);
});
</script>
</body>
</html>
