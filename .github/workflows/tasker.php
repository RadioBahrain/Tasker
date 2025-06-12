<?php
require_once __DIR__ . '/vendor/autoload.php';

$workspace    = getenv('GITHUB_WORKSPACE') ?: __DIR__;
$filePath     = $workspace . '/tasks_manifesto.md';
$issueNumber  = getenv('ISSUE_NUMBER');
$issueTitle   = getenv('ISSUE_TITLE') ?: "Untitled Event";

if (!$issueNumber) {
    fwrite(STDERR, "ISSUE_NUMBER is not set.\n");
    exit(1);
}

// Check for existing task
if (!file_exists($filePath)) {
    file_put_contents($filePath, "| Issue | Task | Status |\n|---|---|---|\n");
    chmod($filePath, 0666);
}
$tasks = file_get_contents($filePath);

if (strpos($tasks, "| $issueNumber |") !== false) {
    fwrite(STDOUT, "Task already exists for Issue #$issueNumber.\n");
    exit(0);
}

// --- YouTube OAuth2 Setup ---
$client = new Google_Client();
$client->setClientId(getenv('YOUTUBE_CLIENT_ID'));
$client->setClientSecret(getenv('YOUTUBE_CLIENT_SECRET'));
$client->setAccessType('offline');
$client->setScopes(['https://www.googleapis.com/auth/youtube.force-ssl']);
$client->setRedirectUri('urn:ietf:wg:oauth:2.0:oob');
$refreshToken = getenv('YOUTUBE_REFRESH_TOKEN');
if (!$refreshToken) {
    fwrite(STDERR, "YOUTUBE_REFRESH_TOKEN is not set.\n");
    exit(1);
}
$client->refreshToken($refreshToken);

if ($client->isAccessTokenExpired()) {
    fwrite(STDERR, "Failed to refresh YouTube access token.\n");
    exit(1);
}
$youtube = new Google_Service_YouTube($client);

// --- Create YouTube Live Broadcast ---
try {
    $broadcastSnippet = new Google_Service_YouTube_LiveBroadcastSnippet([
        'title'               => "Issue #$issueNumber: $issueTitle",
        'scheduledStartTime'  => date('c', strtotime('+1 hour')),
    ]);
    $status = new Google_Service_YouTube_LiveBroadcastStatus(['privacyStatus' => 'private']);
    $broadcast = new Google_Service_YouTube_LiveBroadcast([
        'snippet' => $broadcastSnippet,
        'status'  => $status,
        'kind'    => 'youtube#liveBroadcast'
    ]);
    $broadcastResponse = $youtube->liveBroadcasts->insert('snippet,status', $broadcast);

    // --- Create Live Stream ---
    $streamSnippet = new Google_Service_YouTube_LiveStreamSnippet([
        'title' => "Stream for Issue #$issueNumber"
    ]);
    $cdn = new Google_Service_YouTube_CdnSettings([
        'format'        => "1080p",
        'ingestionType' => 'rtmp'
    ]);
    $stream = new Google_Service_YouTube_LiveStream([
        'snippet' => $streamSnippet,
        'cdn'     => $cdn,
        'kind'    => 'youtube#liveStream'
    ]);
    $streamResponse = $youtube->liveStreams->insert('snippet,cdn', $stream);

    // --- Bind Broadcast to Stream ---
    $youtube->liveBroadcasts->bind(
        $broadcastResponse['id'],
        'id,contentDetails',
        ['streamId' => $streamResponse['id']]
    );

    $youtubeEventUrl = "https://www.youtube.com/watch?v=" . $broadcastResponse['id'];
    $newTask = "| $issueNumber | [YouTube Live Event]($youtubeEventUrl) | Created |\n";
    file_put_contents($filePath, $tasks . $newTask);
    fwrite(STDOUT, "Created YouTube Live Stream Event: $youtubeEventUrl\n");

} catch (Exception $e) {
    fwrite(STDERR, "YouTube API error: " . $e->getMessage() . "\n");
    exit(1);
}
