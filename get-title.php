<?php

function sanitizeFolderName($name) {
    $name = preg_replace('/[\\\\\\/\\:\\*\\?\\"\\<\\>\\|]/', '', $name);
    $name = preg_replace('/[^A-Za-z0-9\\-_.() ]/', '', $name);
    $name = preg_replace('/\\s+/', ' ', $name);
    $name = trim($name);
    return $name;
}

header('Content-Type: application/json');

if (!isset($_POST['url'])) {
    echo json_encode([
        "success" => false,
        "error" => "No URL provided"
    ]);
    exit;
}

$url = escapeshellarg($_POST['url']);
$ytDlpPath = "yt-dlp_linux";

$command = "$ytDlpPath --no-warnings --cookies cookies.txt --user-agent \"Mozilla/5.0\" --sleep-interval 3 --max-sleep-interval 6 --print \"%(title)s\" $url 2>&1";

$output = shell_exec($command);

if ($output === null) {
    echo json_encode([
        "success" => false,
        "error" => "Failed to execute yt-dlp"
    ]);
    exit;
}

$title = trim($output);

if ($title === "") {
    echo json_encode([
        "success" => false,
        "error" => "Title not found"
    ]);
    exit;
}

$cleanTitle = sanitizeFolderName($title);

echo json_encode([
    "success" => true,
    "title" => $cleanTitle
]);