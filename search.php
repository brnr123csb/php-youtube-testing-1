<?php
// search.php – returns JSON with YouTube search results, initial continuation token, and Innertube parameters
header('Content-Type: application/json; charset=UTF-8;');

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
if ($query === '') {
    echo json_encode(['error' => 'Empty query']);
    exit;
}

$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (!$userAgent) {
    $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)';
}
$headers = [
    "User-Agent: $userAgent",
    "Accept-Language: en-US,en;q=0.9"
];
$url = 'https://www.youtube.com/results?search_query=' . urlencode($query);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_FOLLOWLOCATION => true
]);
$html = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$html || $code >= 400) {
    echo json_encode(['error' => "Failed to fetch YouTube page ($code)"]);
    exit;
}

if (preg_match('#ytInitialData\s*=\s*(\{.+?\})\s*;\s*</script>#s', $html, $m)) {
    $ytJson = $m[1];
} else {
    echo json_encode(['error' => 'ytInitialData not found']);
    exit;
}

if (preg_match('#"INNERTUBE_API_KEY"\s*:\s*"([^"]+)"#', $html, $m2)) {
    $key = $m2[1];
} else {
    echo json_encode(['error' => 'INNERTUBE_API_KEY not found']);
    exit;
}

if (preg_match('#"INNERTUBE_CLIENT_VERSION"\s*:\s*"([^"]+)"#', $html, $m3)) {
    $version = $m3[1];
} else {
    echo json_encode(['error' => 'INNERTUBE_CLIENT_VERSION not found']);
    exit;
}

$data = json_decode($ytJson, true);
if (!is_array($data)) {
    echo json_encode(['error' => 'Invalid ytInitialData JSON']);
    exit;
}

$contents = $data['contents']['twoColumnSearchResultsRenderer']['primaryContents']['sectionListRenderer']['contents'] ?? [];
$videos = [];
function parseItems($items) {
    $res = [];
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        if (isset($item['videoRenderer'])) {
            $v = $item['videoRenderer'];
            $vid = $v['videoId'] ?? null;
            $title = '';
            if (isset($v['title']['simpleText'])) {
                $title = $v['title']['simpleText'];
            } elseif (isset($v['title']['runs'])) {
                foreach ($v['title']['runs'] as $r) {
                    $title .= $r['text'] ?? '';
                }
            }
            $thumb = '';
            if (!empty($v['thumbnail']['thumbnails']) && is_array($v['thumbnail']['thumbnails'])) {
                $t = end($v['thumbnail']['thumbnails']);
                $thumb = $t['url'] ?? '';
            }
            if ($vid && $title) {
                $res[] = ['videoId' => $vid, 'title' => htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE), 'thumbnail' => $thumb];
            }
        } elseif (isset($item['itemSectionRenderer']['contents'])) {
            $res = array_merge($res, parseItems($item['itemSectionRenderer']['contents']));
        } elseif (isset($item['richItemRenderer']['content'])) {
            $inner = $item['richItemRenderer']['content'];
            if (isset($inner['videoRenderer'])) {
                $wrapper = [$inner];
                $res = array_merge($res, parseItems($wrapper));
            }
        }
    }
    return $res;
}
$videos = parseItems($contents);

function findContinuation($items) {
    foreach ($items as $it) {
        if (!is_array($it)) continue;
        if (isset($it['continuationItemRenderer']['continuationEndpoint']['continuationCommand']['token'])) {
            return $it['continuationItemRenderer']['continuationEndpoint']['continuationCommand']['token'];
        }
        foreach ($it as $child) {
            if (is_array($child)) {
                $tok = findContinuation([$child]);
                if ($tok) return $tok;
            }
        }
    }
    return null;
}
$token = findContinuation($contents);

echo json_encode([
    'results' => $videos,
    'continuation' => $token ?: null,
    'innertube_key' => $key,
    'client_version' => $version
]);
