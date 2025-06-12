<?php
/**
 * This script helps in getting a YouTube API refresh token
 *
 * Instructions:
 * 1. Create a project in Google Cloud Console
 * 2. Enable the YouTube Data API v3
 * 3. Create OAuth 2.0 credentials (Web application type)
 * 4. Set the redirect URI to http://localhost:8080/callback
 * 5. Run this script and follow the prompts
 *
 *
 */

// Check if composer dependencies are installed
if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    die("Please run 'composer install' first\n");
}

require_once __DIR__ . '/../vendor/autoload.php';

// Check if this is being run from the command line
if (php_sapi_name() != 'cli') {
    die("This script must be run from the command line\n");
}

// Prompt for client ID and client secret
echo "Enter your Google Client ID: ";
$clientId = trim(fgets(STDIN));

echo "Enter your Google Client Secret: ";
$clientSecret = trim(fgets(STDIN));

// Create the client object
$client = new Google\Client();
$client->setClientId($clientId);
$client->setClientSecret($clientSecret);
$client->setRedirectUri('http://localhost:8080/callback');
$client->setAccessType('offline');
$client->setApprovalPrompt('force'); // Force to get a refresh token
$client->setIncludeGrantedScopes(true);
$client->addScope('https://www.googleapis.com/auth/youtube');

// Generate the authorization URL
$authUrl = $client->createAuthUrl();
echo "Open the following URL in your browser:\n" . $authUrl . "\n";

// Start a local web server to receive the callback
echo "Starting local web server on port 8080...\n";
$command = "php -S localhost:8080 " . __DIR__ . "/callback_handler.php";
echo "Please authorize the application in your browser.\n";
echo "After authorization, you'll be redirected to the local server.\n";
exec($command);

/**
 * This script would normally continue here after the callback_handler.php script
 * saves the refresh token, but since we're starting a separate web server,
 * the actual token retrieval happens in callback_handler.php
 */
?>