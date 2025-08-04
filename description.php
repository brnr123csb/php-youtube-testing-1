<?php
// description.php – Fetch and return video description as HTML
header('Content-Type: application/json; charset=UTF-8');

$videoId = isset($_GET['id']) ? trim($_GET['id']) : '';
if (!preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId)) {
    echo json_encode(['error' => 'Invalid video ID']);
    exit;
}

$url = "https://www.youtube.com/watch?v=$videoId";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
$html = curl_exec($ch);
curl_close($ch);

if (!$html) {
    echo json_encode(['error' => 'Failed to fetch video page']);
    exit;
}

// Extract the ytInitialPlayerResponse JSON from the page
if (preg_match('/ytInitialPlayerResponse\s*=\s*(\{.*?\});/s', $html, $matches)) {
    $json_data = $matches[1];
    $data = json_decode($json_data);
    if ($data === null) {
        echo json_encode(['error' => 'Failed to parse player response']);
        exit;
    }
} else {
    echo json_encode(['error' => 'No player response found']);
    exit;
}

$description = $data->videoDetails->shortDescription ?? '';
// Sanitize text and convert URLs into clickable links
$description = htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE);
$description = preg_replace('#(https?://\S+)#', '<a href="$1" target="_blank">$1</a>', $description);
$description = nl2br($description);

echo json_encode(['description' => $description]);
?>
