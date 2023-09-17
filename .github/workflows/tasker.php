<?php
// update_tasks_manifesto.php

$workspace = getenv('GITHUB_WORKSPACE');
$filePath = $workspace . '/tasks_manifesto.md';

$tasks = file_get_contents($filePath);
$issueNumber = getenv('ISSUE_NUMBER');

if (strpos($tasks, "| $issueNumber |") !== false) {
    exit("Task already exists.");
} else {
    // Your task creation logic here...
    // ...
    file_put_contents($filePath, $tasks);
}
?>
