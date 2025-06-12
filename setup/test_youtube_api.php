<?php
/**
 * This script tests the YouTube API integration
 * It verifies that your API credentials work correctly
 */

// Check if composer dependencies are installed
if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    die("Please run 'composer install' first\n");
}

require_once __DIR__ . '/../vendor/autoload.php';

// Check command line arguments
if ($argc < 5) {
    echo "Usage: php test_youtube_api.php <api_key> <client_id> <client_secret> <refresh_token>\n";
    echo "Or set these as environment variables:\n";
    echo "YOUTUBE_API_KEY, YOUTUBE_CLIENT_ID, YOUTUBE_CLIENT_SECRET, YOUTUBE_REFRESH_TOKEN\n";
    exit(1);
}

// Get credentials from command line or environment variables
$apiKey = $argc > 1 ? $argv[1] : getenv('YOUTUBE_API_KEY');
$clientId = $argc > 2 ? $argv[2] : getenv('YOUTUBE_CLIENT_ID');
$clientSecret = $argc > 3 ? $argv[3] : getenv('YOUTUBE_CLIENT_SECRET');
$refreshToken = $argc > 4 ? $argv[4] : getenv('YOUTUBE_REFRESH_TOKEN');

if (!$apiKey || !$clientId || !$clientSecret || !$refreshToken) {
    die("Missing required credentials\n");
}

try {
    // Set up the Google API Client
    $client = new Google_Client();
    $client->setClientId($clientId);
    $client->setClientSecret($clientSecret);
    $client->setDeveloperKey($apiKey);
    $client->setScopes(['https://www.googleapis.com/auth/youtube.readonly']);
    $client->setAccessType('offline');

    // Set the refresh token to get a new access token
    $client->refreshToken($refreshToken);

    // Create YouTube service
    $youtube = new \Google\Service\YouTube($client);

    // Test API by fetching channel information
    $channelsResponse = $youtube->channels->listChannels('snippet,contentDetails,statistics', [
        'mine' => true
    ]);

    if (empty($channelsResponse->getItems())) {
        echo "No channel found\n";
        exit(1);
    }

    $channel = $channelsResponse->getItems()[0];

    echo "YouTube API test successful!\n";
    echo "Connected to channel: " . $channel->getSnippet()->getTitle() . "\n";
    echo "Channel ID: " . $channel->getId() . "\n";
    echo "Subscriber count: " . $channel->getStatistics()->getSubscriberCount() . "\n";
    echo "Video count: " . $channel->getStatistics()->getVideoCount() . "\n";

    echo "\nChannel details:\n";
    echo json_encode($channel, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo "YouTube API test failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>