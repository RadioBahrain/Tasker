<?php
// update_tasks_manifesto.php and create YouTube livestreams

/**
 * This script performs two main functions:
 * 1. Updates the tasks manifesto file with new GitHub issues
 * 2. Creates scheduled YouTube livestreams when an issue has a specific title
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// Get environment variables
$workspace = getenv('GITHUB_WORKSPACE');
$filePath = $workspace . '/tasks_manifesto.md';
$issueNumber = getenv('ISSUE_NUMBER');
$issueTitle = getenv('ISSUE_TITLE');
$issueBody = getenv('ISSUE_BODY');

// Check if the tasks manifesto file exists, create it if it doesn't
if (!file_exists($filePath)) {
    file_put_contents($filePath, "| Issue | Task | Status |\n|---|---|---|\n");
    chmod($filePath, 0666);
}
$tasks = file_get_contents($filePath);

// Check if the task already exists
if (strpos($tasks, "| $issueNumber |") !== false) {
    exit("Task already exists.");
} else {
    // Check if this is a livestream creation request
    if ($issueTitle === "New Live" || $issueTitle === "New Livestream") {
        echo "Creating a new scheduled YouTube livestream...\n";

        try {
            $livestreamDetails = createYouTubeLivestream($issueBody);

            // Add the task to the manifesto with the livestream details
            $tasks .= "| $issueNumber | Created YouTube Livestream: {$livestreamDetails['title']} | Scheduled |\n";
            file_put_contents($filePath, $tasks);

            echo "YouTube livestream scheduled successfully!\n";
            echo "Title: " . $livestreamDetails['title'] . "\n";
            echo "Scheduled for: " . $livestreamDetails['scheduledStartTime'] . "\n";
            echo "Stream URL: https://youtube.com/watch?v=" . $livestreamDetails['id'] . "\n";
        } catch (Exception $e) {
            echo "Error creating YouTube livestream: " . $e->getMessage() . "\n";

            // Still add the task to the manifesto, but mark it as failed
            $tasks .= "| $issueNumber | Failed to create YouTube Livestream | Error |\n";
            file_put_contents($filePath, $tasks);

            exit(1);
        }
    } else {
        // Regular task creation logic
        $tasks .= "| $issueNumber | $issueTitle | New |\n";
        file_put_contents($filePath, $tasks);
    }
}

/**
 * Creates a scheduled YouTube livestream using the YouTube API
 *
 * @param string $issueBody The body of the GitHub issue containing livestream details
 * @return array Details of the created livestream
 */
function createYouTubeLivestream($issueBody) {
    // Get YouTube API credentials from environment variables
    $youtubeApiKey = getenv('YOUTUBE_API_KEY');
    $youtubeClientId = getenv('YOUTUBE_CLIENT_ID');
    $youtubeClientSecret = getenv('YOUTUBE_CLIENT_SECRET');
    $youtubeRefreshToken = getenv('YOUTUBE_REFRESH_TOKEN');
    $youtubeChannelId = getenv('YOUTUBE_CHANNEL_ID');

    // Debug: Check if credentials are loaded (don't log actual values for security)
    echo "YouTube API credentials loaded: " .
         (empty($youtubeApiKey) ? "NO" : "YES") . " (API Key), " .
         (empty($youtubeClientId) ? "NO" : "YES") . " (Client ID), " .
         (empty($youtubeClientSecret) ? "NO" : "YES") . " (Client Secret), " .
         (empty($youtubeRefreshToken) ? "NO" : "YES") . " (Refresh Token), " .
         (empty($youtubeChannelId) ? "NO" : "YES") . " (Channel ID)\n";

    if (!$youtubeApiKey || !$youtubeClientId || !$youtubeClientSecret || !$youtubeRefreshToken || !$youtubeChannelId) {
        throw new Exception("Missing YouTube API credentials in environment variables");
    }

    // Parse livestream details from issue body
    $livestreamDetails = parseIssueBody($issueBody);

    // Add channel ID to livestream details
    $livestreamDetails['channelId'] = $youtubeChannelId;

    // Set up the Google API Client using proper namespaces
    $client = new \Google\Client();
    $client->setClientId($youtubeClientId);
    $client->setClientSecret($youtubeClientSecret);
    $client->setDeveloperKey($youtubeApiKey);
    $client->setScopes(['https://www.googleapis.com/auth/youtube']);
    $client->setAccessType('offline');

    // Set the refresh token to get a new access token
    $client->refreshToken($youtubeRefreshToken);

    // Create YouTube service
    $youtube = new \Google\Service\YouTube($client);

    // Create the broadcast (metadata for the livestream)
    $broadcastSnippet = new \Google\Service\YouTube\LiveBroadcastSnippet();
    $broadcastSnippet->setTitle($livestreamDetails['title']);
    $broadcastSnippet->setDescription($livestreamDetails['description']);
    $broadcastSnippet->setScheduledStartTime($livestreamDetails['scheduledStartTime']);

    $status = new \Google\Service\YouTube\LiveBroadcastStatus();
    $status->setPrivacyStatus($livestreamDetails['privacyStatus']);

    $broadcast = new \Google\Service\YouTube\LiveBroadcast();
    $broadcast->setSnippet($broadcastSnippet);
    $broadcast->setStatus($status);
    $broadcast->setKind('youtube#liveBroadcast');

    // Set the broadcast content details
    $contentDetails = new \Google\Service\YouTube\LiveBroadcastContentDetails();
    $contentDetails->setEnableAutoStart(true);
    $contentDetails->setEnableAutoStop(true);
    $broadcast->setContentDetails($contentDetails);

    try {
        // Create the broadcast on YouTube
        $broadcastInsert = $youtube->liveBroadcasts->insert('snippet,status,contentDetails', $broadcast);

        // Create the stream (the actual video stream)
        $streamSnippet = new \Google\Service\YouTube\LiveStreamSnippet();
        $streamSnippet->setTitle($livestreamDetails['title']);

        $cdn = new \Google\Service\YouTube\CdnSettings();
        $cdn->setFormat("1080p");
        $cdn->setIngestionType('rtmp');

        $stream = new \Google\Service\YouTube\LiveStream();
        $stream->setSnippet($streamSnippet);
        $stream->setCdn($cdn);
        $stream->setKind('youtube#liveStream');

        // Create the stream on YouTube
        $streamInsert = $youtube->liveStreams->insert('snippet,cdn', $stream);

        // Bind the broadcast to the stream
        $bindBroadcast = $youtube->liveBroadcasts->bind(
            $broadcastInsert->getId(),
            'id,contentDetails',
            ['streamId' => $streamInsert->getId()]
        );

        // Return details about the created livestream
        return [
            'id' => $broadcastInsert->getId(),
            'title' => $livestreamDetails['title'],
            'description' => $livestreamDetails['description'],
            'scheduledStartTime' => $livestreamDetails['scheduledStartTime'],
            'streamKey' => $streamInsert->getCdn()->getIngestionInfo()->getStreamName(),
            'streamUrl' => $streamInsert->getCdn()->getIngestionInfo()->getIngestionAddress()
        ];
    } catch (\Exception $e) {
        // Add more detailed error information
        echo "YouTube API Error: " . $e->getMessage() . "\n";
        echo "Error Code: " . (method_exists($e, 'getCode') ? $e->getCode() : 'N/A') . "\n";
        throw new \Exception("Failed to create YouTube livestream: " . $e->getMessage(), 0, $e);
    }
}

/**
 * Parses the GitHub issue body to extract livestream details
 *
 * @param string $issueBody The body of the GitHub issue
 * @return array The parsed livestream details
 */
function parseIssueBody($issueBody) {
    // Default values
    $details = [
        'title' => 'Scheduled Livestream',
        'description' => 'Scheduled through GitHub Actions',
        'scheduledStartTime' => date('c', strtotime('+1 day')),
        'privacyStatus' => 'public'
    ];

    // Parse title
    if (preg_match('/Title:\s*(.*?)(?:\r|\n|$)/i', $issueBody, $matches)) {
        $details['title'] = trim($matches[1]);
    }

    // Parse description
    if (preg_match('/Description:\s*(.*?)(?:\r|\n|$)/i', $issueBody, $matches)) {
        $details['description'] = trim($matches[1]);
    }

    // Parse scheduled start time
    if (preg_match('/Date:\s*(.*?)(?:\r|\n|$)/i', $issueBody, $matches)) {
        $dateString = trim($matches[1]);
        $scheduledTime = strtotime($dateString);

        if ($scheduledTime) {
            $details['scheduledStartTime'] = date('c', $scheduledTime);
        }
    }

    // Parse privacy status
    if (preg_match('/Privacy:\s*(public|private|unlisted)(?:\r|\n|$)/i', $issueBody, $matches)) {
        $details['privacyStatus'] = strtolower(trim($matches[1]));
    }

    return $details;
}

