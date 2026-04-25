<?php

function sanitizeProjectName($name) {
    return preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
}

function extractVideoId($url) {
    preg_match('/(v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $url, $matches);
    return $matches[2] ?? null;
}

function timeToSeconds($time) {
    $parts = array_reverse(explode(':', $time));
    $seconds = 0;

    if (isset($parts[0])) $seconds += (int)$parts[0];
    if (isset($parts[1])) $seconds += (int)$parts[1] * 60;
    if (isset($parts[2])) $seconds += (int)$parts[2] * 3600;

    return $seconds;
}

function secondsToTime($seconds) {
    if ($seconds < 0) $seconds = 0;

    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    $s = $seconds % 60;

    return sprintf('%02d:%02d:%02d', $h, $m, $s);
}

$projectName = sanitizeProjectName($_POST['project_name']);
$url = $_POST['url'];
$starts = $_POST['start'];
$ends = $_POST['end'];

$videoId = extractVideoId($url);
if (!$videoId) {
    die("Invalid URL");
}

$timestamp = date("ymd");
$folderName = "{$timestamp}_{$projectName}";
$projectPath = __DIR__ . "/projects/" . $folderName;

mkdir($projectPath, 0777, true);

$cleanUrl = "https://www.youtube.com/watch?v=" . $videoId;

$scriptPath = "{$projectPath}/cmd.sh";
$scriptContent = "#!/bin/bash\n\n";

// ======================
// STEP 1: PARALLEL DOWNLOAD
// Download start-10 detik sampai end
// Output selalu .webm
// Best video + best audio
// ======================
foreach ($starts as $i => $start) {
    if (!$start || !$ends[$i]) continue;

    $end = $ends[$i];

    $startSec = timeToSeconds($start);
    $downloadStart = secondsToTime($startSec - 10);

    $baseName = "{$projectPath}/clip_" . ($i + 1);

    $scriptContent .= "
yt-dlp_linux --cookies cookies.txt --user-agent \"Mozilla/5.0\" --sleep-interval 3 --max-sleep-interval 6 \\
-f \"bestvideo[ext=webm]+bestaudio[ext=webm]/best[ext=webm]\" \\
--merge-output-format webm \\
--download-sections \"*{$downloadStart}-{$end}\" \\
-o \"{$baseName}.webm\" \\
\"{$cleanUrl}\" \\
> /dev/null 2>&1 &
";
}

// wait all download complete
$scriptContent .= "\nwait\n\n";

// ======================
// STEP 2: FFMPEG
// Buang 10 detik awal hasil download
// ======================
foreach ($starts as $i => $start) {
    if (!$start || !$ends[$i]) continue;

    $baseName = "{$projectPath}/clip_" . ($i + 1);

    $scriptContent .= "
ffmpeg -y \\
    -ss 10 \\
    -i \"{$baseName}.webm\" \\
    -c copy \\
    -f webm \"{$baseName}.webm.part\" \\
&& rm \"{$baseName}.webm\" && mv \"{$baseName}.webm.part\" \"{$projectPath}/final_clip_" . ($i+1) . ".webm\"
";
}

// ======================
// SAVE FILE
// ======================
file_put_contents($scriptPath, $scriptContent);

// permission execute
chmod($scriptPath, 0755);

// ======================
// RUN BACKGROUND
// ======================
exec("bash " . escapeshellarg($scriptPath) . " > " . escapeshellarg($projectPath . "/log.log") . " 2>&1 &");

$validClips = 0;
foreach ($starts as $i => $start) {
    if ($start && $ends[$i]) {
        $validClips++;
    }
}

file_put_contents(
    $projectPath . "/meta.json",
    json_encode([
        "total" => $validClips
    ])
);

// Redirect
header("Location: status.php?project=" . urlencode($folderName));
exit;