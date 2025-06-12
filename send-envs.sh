#!/bin/bash

# Check if GitHub CLI is installed and authenticated
if ! command -v gh &> /dev/null; then
  echo "❌ GitHub CLI (gh) is not installed. Please install it first."
  echo "Visit: https://cli.github.com/"
  exit 1
fi

if ! gh auth status &> /dev/null; then
  echo "❌ Not authenticated with GitHub CLI. Please run 'gh auth login' first."
  exit 1
fi

# List JSON files in current directory
json_files=(*.json)
count=${#json_files[@]}
repo_path="RadioBahrain/Tasker"

if [ $count -eq 0 ]; then
  echo "No JSON files found in the current directory."
  exit 1
elif [ $count -eq 1 ]; then
  selected="${json_files[0]}"
else
  echo "Select a JSON file to flatten and push as GitHub secrets:"
  select fname in "${json_files[@]}"; do
    if [ -n "$fname" ]; then
      selected="$fname"
      break
    fi
  done
fi

echo "Flattening $selected..."

# Flatten JSON (dot notation, uppercase, underscores)
flattened=$(jq -r 'paths(scalars) as $p | [($p | map(tostring) | join("_") | ascii_upcase), (getpath($p) | tostring)] | @tsv' "$selected")

if [ -z "$flattened" ]; then
  echo "Failed to flatten JSON or file is empty."
  exit 1
fi

# Push each key-value as a GitHub secret with rate limit handling
echo "Pushing secrets to GitHub..."
total_secrets=$(echo "$flattened" | wc -l)
current=0

while IFS=$'\t' read -r key value; do
  current=$((current + 1))
  echo "[$current/$total_secrets] Setting secret: $key"

  # Retry logic for rate limiting
  max_retries=3
  retry_count=0

  while [ $retry_count -lt $max_retries ]; do
    if gh secret set "$key" -b"$value" --repo "$repo_path" 2>/dev/null; then
      echo "✓ Successfully set $key"
      break
    else
      retry_count=$((retry_count + 1))
      if [ $retry_count -lt $max_retries ]; then
        wait_time=$((retry_count * 10))
        echo "⚠ Rate limited. Waiting ${wait_time}s before retry $retry_count/$max_retries..."
        sleep $wait_time
      else
        echo "✗ Failed to set $key after $max_retries attempts"
      fi
    fi
  done

  # Add delay between requests to avoid rate limiting
  if [ $current -lt $total_secrets ]; then
    echo "Waiting 2s before next secret..."
    sleep 2
  fi
done <<< "$flattened"

# Get GitHub repo URL and generate secrets page link
git_url=$(git config --get remote.origin.url)
if [[ $git_url == git@github.com:* ]]; then
  repo_path=${git_url#git@github.com:}
  repo_path=${repo_path%.git}
elif [[ $git_url == https://github.com/* ]]; then
  repo_path=${git_url#https://github.com/}
  repo_path=${repo_path%.git}
else
  echo "Could not extract GitHub repo path from remote URL: $git_url"
  exit 1
fi

secrets_url="https://github.com/$repo_path/settings/secrets/actions"

echo "✅ All secrets pushed!"
echo "🔗 Verify here: $secrets_url"