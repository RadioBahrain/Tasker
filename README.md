# Tasker

A GitHub Action PHP application that automates task management and YouTube livestream scheduling.

## Features

- Tracks GitHub issues in a task manifesto file
- Automatically creates scheduled YouTube livestreams from GitHub issues
- Simplifies YouTube API integration

## Setup

### Prerequisites

- PHP 7.4 or higher
- Composer
- A GitHub repository
- A Google account with YouTube channel

### Installation

1. Clone this repository
2. Install dependencies:
   ```
   composer install
   ```

### YouTube API Setup

1. Create a project in the [Google Cloud Console](https://console.cloud.google.com/)
2. Enable the YouTube Data API v3
3. Create OAuth 2.0 credentials (Web application type)
4. Set the redirect URI to `http://localhost:8080/callback`
5. Get your API key, client ID, and client secret
6. Run the token generator script:
   ```
   php setup/get_youtube_token.php
   ```
7. Follow the prompts and authorize the application
8. Save the generated refresh token

### GitHub Repository Setup

Add the following secrets to your GitHub repository:

- `YOUTUBE_API_KEY`: Your YouTube API key
- `YOUTUBE_CLIENT_ID`: Your Google client ID
- `YOUTUBE_CLIENT_SECRET`: Your Google client secret
- `YOUTUBE_REFRESH_TOKEN`: The refresh token from the previous step
- `YOUTUBE_CHANNEL_ID`: Your YouTube channel ID

## Usage

### Creating a Scheduled Livestream

1. Create a new GitHub issue with the title "New Live" or "New Livestream"
2. In the issue body, include the following details:
   ```
   Title: Your Livestream Title
   Description: A description of your livestream
   Date: YYYY-MM-DD HH:MM (e.g., 2025-06-05 15:00)
   Privacy: public (or private, unlisted)
   ```
3. Submit the issue
4. The GitHub Action will automatically create a scheduled livestream on your YouTube channel
5. The tasks_manifesto.md file will be updated with the livestream details

### Testing the YouTube API

To test your YouTube API credentials:

```
php setup/test_youtube_api.php <api_key> <client_id> <client_secret> <refresh_token>
```

Or set the credentials as environment variables and run:

```
php setup/test_youtube_api.php
```

## License

See the [LICENSE](LICENSE) file for details.