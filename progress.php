<?php

$project = $_GET['project'] ?? '';
$path = __DIR__ . "/projects/" . $project;

if (!is_dir($path)) {
    echo json_encode(["error" => "not found"]);
    exit;
}

$metaFile = $path . "/meta.json";
$total = 0;

if (file_exists($metaFile)) {
    $meta = json_decode(file_get_contents($metaFile), true);
    $total = $meta['total'] ?? 0;
}

$completed = [];
$downloading = [];
$downloaded = [];

foreach (scandir($path) as $f) {
    if ($f === '.' || $f === '..') continue;

    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));

    $fullPath = $path . "/" . $f;

    if (in_array($ext, ['mp4'])) {
        if (str_starts_with($f, 'final_')) {
            $completed[] = $f;
        }
        else
        {
            $downloaded[] = $f;
        }
    }
    else if ($ext === 'part') {
        $downloading[] = [
            "name" => $f,
            "size" => filesize($fullPath)
        ];
    }
}

echo json_encode([
    "total" => $total,
    "completed" => count($completed),
    "files" => $completed,
    "downloading" => $downloading,
    "downloaded" => $downloaded
]);