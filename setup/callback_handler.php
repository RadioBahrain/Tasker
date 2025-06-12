<?php
/**
 * This script handles the OAuth2 callback from Google
 * It's used as part of the get_youtube_token.php process
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Check if this is a callback from Google Auth
if (isset($_GET['code'])) {
    $authCode = $_GET['code'];

    // Create the client object
    $client = new Google_Client();

    // Load client ID and client secret from temporary file
    // (In a real app, these would be stored securely)
    $clientId = getenv('GOOGLE_CLIENT_ID');
    $clientSecret = getenv('GOOGLE_CLIENT_SECRET');

    if (!$clientId || !$clientSecret) {
        echo "Client ID and Client Secret not found. Please restart the auth process.";
        exit(1);
    }

    $client->setClientId($clientId);
    $client->setClientSecret($clientSecret);
    $client->setRedirectUri('http://localhost:8080/callback');

    // Exchange auth code for access token
    $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

    if (isset($accessToken['access_token'])) {
        // Save tokens to a file
        $tokenFile = __DIR__ . '/../youtube_tokens.json';
        file_put_contents($tokenFile, json_encode($accessToken));
        chmod($tokenFile, 0600); // Secure permissions

        echo "<h1>Authorization Successful!</h1>";
        echo "<p>Your refresh token has been saved. You can now close this window and return to the terminal.</p>";

        // Extract and display the refresh token
        if (isset($accessToken['refresh_token'])) {
            echo "<h2>Your Refresh Token:</h2>";
            echo "<pre>" . $accessToken['refresh_token'] . "</pre>";
            echo "<p>Add this to your GitHub repository secrets as YOUTUBE_REFRESH_TOKEN</p>";
        } else {
            echo "<p>Warning: No refresh token was received. You may need to revoke access and try again.</p>";
        }

        // Terminate the PHP server
        echo "<script>setTimeout(function() { window.close(); }, 60000);</script>";
        echo "<p>This window will close automatically after 1 minute.</p>";
    } else {
        echo "<h1>Error</h1>";
        echo "<p>Failed to get access token.</p>";
        echo "<pre>" . print_r($accessToken, true) . "</pre>";
    }
} else {
    echo "<h1>Invalid Request</h1>";
    echo "<p>No authorization code provided.</p>";
}
?>
