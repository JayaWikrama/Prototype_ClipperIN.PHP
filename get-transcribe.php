<?php

function sanitizeProjectName($name) {
    return preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
}

function formatTime($time) {
    return explode(',', $time)[0];
}

function parseTimeToSeconds($time) {
    list($h, $m, $s) = explode(':', str_replace(',', '.', $time));
    return ($h * 3600) + ($m * 60) + floatval($s);
}

function convertSrtToCustom($inputFile, $outputFile) {
    $content = file_get_contents($inputFile);
    $blocks = preg_split('/\\n\\s*\\n/', trim($content));

    $out = [];

    foreach ($blocks as $block) {
        $lines = explode("\n", $block);
        if (count($lines) < 3) continue;

        list($start, $end) = explode(' --> ', $lines[1]);

        $start = formatTime($start);
        $end = formatTime($end);

        $text = trim(preg_replace('/\\s+/', ' ', implode(' ', array_slice($lines, 2))));

        $out[] = "$start|$end|$text";
    }

    file_put_contents($outputFile, implode("\n", $out));
}

function splitAndConvert($file, $dir, $chunk = 600) {
    $content = file_get_contents($file);
    $blocks = preg_split('/\\n\\s*\\n/', trim($content));

    $chunks = [];

    foreach ($blocks as $block) {
        $lines = explode("\n", $block);
        if (count($lines) < 3) continue;

        list($start, $end) = explode(' --> ', $lines[1]);
        $sec = parseTimeToSeconds($start);

        $index = floor($sec / $chunk);
        $chunks[$index][] = $block;
    }

    foreach ($chunks as $i => $data) {
        $tmp = "$dir/tmp_$i.srt";
        file_put_contents($tmp, implode("\n\n", $data));

        convertSrtToCustom($tmp, "$dir/part_" . ($i + 1) . ".subtitle");

        unlink($tmp);
    }
}

header('Content-Type: application/json');

if (!isset($_POST['url'])) {
    echo json_encode([
        "success" => false,
        "message" => "No URL provided"
    ]);
    exit;
}

if (!isset($_POST['project_name'])) {
    echo json_encode([
        "success" => false,
        "message" => "No Project Name provided"
    ]);
    exit;
}

$url = $_POST['url'] ?? '';
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode([
        "success" => false,
        "message" => "invalid url"
    ]);
    exit;
}

$escapedUrl = escapeshellarg($url);
$ytDlpPath = "yt-dlp_linux";

$rawProjectName = trim($_POST['project_name']);
$projectName = sanitizeProjectName($rawProjectName);
if (strlen($projectName) === 0 || strlen($projectName) > 64) {
    echo json_encode([
        "success" => false,
        "message" => "Project name must be between 1 and 64 characters"
    ]);
    exit;
}
$timestamp = date("ymd");
$folderName = "{$timestamp}_{$projectName}";
$dir = __DIR__ . "/projects/" . $folderName;

if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

/* DOWNLOAD SUBTITLE */
$cmd = "$ytDlpPath \
--cookies cookies.txt \
--user-agent \"Mozilla/5.0\" \
--sleep-interval 3 \
--max-sleep-interval 6 \
--write-auto-subs \
--sub-lang en \
--convert-subs srt \
--skip-download \
-o \"$dir/%(title)s.%(ext)s\" \
$escapedUrl 2>/dev/null";

shell_exec($cmd);

/* FIND SRT */
$files = glob("$dir/*.srt");

if (!$files) {
    echo json_encode([
        "success" => false,
        "message" => "subtitle not found"
    ]);
    exit;
}

/* PROCESS */
splitAndConvert($files[0], $dir, 600);

echo json_encode([
    "success" => true,
    "message" => "done",
    "url" => "download.php?project=" . urlencode($folderName)
]);