<?php
// load_more.php – fetches next page via Innertube API continuation
header('Content-Type: application/json; charset=UTF-8;');

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$key = isset($_GET['key']) ? trim($_GET['key']) : '';
$version = isset($_GET['version']) ? trim($_GET['version']) : '';

if (!$token || !$key || !$version) {
    echo json_encode(['error' => 'Missing continuation token or API parameters']);
    exit;
}

$endpoint = 'https://www.youtube.com/youtubei/v1/search?key=' . urlencode($key);
$payload = [
    'context' => [
        'client' => [
            'clientName' => 'WEB',
            'clientVersion' => $version
        ]
    ],
    'continuation' => $token
];
$post = json_encode($payload);
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (!$userAgent) {
    $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)';
}

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Accept-Language: en-US,en;q=0.9',
        'User-Agent: ' . $userAgent
    ],
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $post,
    CURLOPT_FOLLOWLOCATION => true
]);
$json = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$json || $code >= 400) {
    echo json_encode(['error' => "Continuation fetch failed ($code)"]);
    exit;
}

$data = json_decode($json, true);
if (!is_array($data)) {
    echo json_encode(['error' => 'Invalid continuation JSON']);
    exit;
}

$items = $data['onResponseReceivedCommands'][0]['appendContinuationItemsAction']['continuationItems']
    ?? $data['onResponseReceivedActions'][0]['appendContinuationItemsAction']['continuationItems']
    ?? $data['onResponseReceivedEndpoints'][0]['appendContinuationItemsAction']['continuationItems']
    ?? [];

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
                $res = array_merge($res, parseItems([$inner]));
            }
        }
    }
    return $res;
}
$videos = parseItems($items);

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
$newToken = findContinuation($items);

echo json_encode([
    'results' => $videos,
    'continuation' => $newToken ?: null
]);
