<?php

$projectsDir = __DIR__ . "/projects";
$projects = is_dir($projectsDir) ? array_diff(scandir($projectsDir), ['.', '..']) : [];

$project = $_GET['project'] ?? '';
$path = __DIR__ . "/projects/" . $project;

if (!is_dir($path)) {
    die("Project not found");
}

$allowedExt = ['webm', 'subtitle'];

$files = array_values(array_filter(scandir($path), function($f) use ($allowedExt) {
    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
    return in_array($ext, $allowedExt);
}));
?>

<!DOCTYPE html>
<html>
<head>
    <title>Project Viewer</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="dashboard">

    <!-- MODAL PREVIEW -->
    <div id="videoModal" class="modal">
        <div class="modal-content glow-strong">
            <span class="close-btn" onclick="closeModal()">&times;</span>

            <video id="modalVideo" controls autoplay>
                <source id="modalSource" src="">
            </video>

            <p id="modalTitle"></p>
        </div>
    </div>

    <!-- SUBTITLE MODAL -->
    <div id="subtitleModal" class="modal">
        <div class="modal-content glow-strong">

            <span class="close-btn" onclick="closeSubtitleModal()">&times;</span>

            <h3 id="subtitleTitle"></h3>

            <pre id="subtitleContent" style="white-space: pre-wrap; max-height: 500px; overflow:auto;"></pre>

            <button class="copy-btn" onclick="copySubtitleWithPrompt()">
                Copy to Clipboard
            </button>

        </div>
    </div>

    <!-- HEADER -->
    <div class="header">
        <h1>PROJECT: <?php echo htmlspecialchars($project); ?></h1>
        <a href="index.php" class="back-btn" title="Back to Home">← Home</a>
    </div>

    <!-- CONTENT -->
    <div class="content">

        <!-- LEFT PANEL -->
        <div class="panel main-panel">

            <h3>Clips</h3>

            <div class="file-grid">
                <?php foreach ($files as $file): 
                    $filePath = "projects/$project/$file";
                    $fullPath = $path . "/" . $file;
                    $size = filesize($fullPath);
                    $sizeMB = round($size / (1024*1024), 2);
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                ?>
                    <div class="file-card glow">

                    <?php if ($ext === 'webm'): ?>
                        <!-- VIDEO THUMBNAIL -->
                        <video class="thumb"
                               preload="metadata"
                               onclick="openModal('<?php echo $filePath; ?>', '<?php echo htmlspecialchars($file); ?>')">
                            <source src="<?php echo $filePath; ?>#t=1" type="video/webm">
                        </video>

                    <?php elseif ($ext === 'subtitle'): ?>
                        <!-- SUBTITLE FILE -->
                        <div class="thumb subtitle-thumb"
                             onclick="openSubtitleModal('<?php echo $filePath; ?>', '<?php echo htmlspecialchars($file); ?>')">
                            <div class="subtitle-icon">TXT</div>
                        </div>
                    <?php endif; ?>

                        <div class="file-info">
                            <p class="file-name"><?php echo htmlspecialchars($file); ?></p>
                            <p class="file-size"><?php echo $sizeMB; ?> MB</p>
                        </div>

                        <a class="download-btn" href="<?php echo $filePath; ?>" download>
                            Download
                        </a>

                    </div>
                <?php endforeach; ?>
            </div>

        </div>

        <!-- RIGHT PANEL -->
        <div class="panel sidebar-panel">
            <h3>Available Projects</h3>

            <?php if (empty($projects)): ?>
                <p class="muted">No projects yet</p>
            <?php else: ?>
                <?php foreach ($projects as $p): ?>
                    <div class="project-item">
                        <a href="download.php?project=<?php echo urlencode($p); ?>">
                            <?php echo htmlspecialchars($p); ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>

let currentSubtitleText = "";

function openModal(src, title) {
    const modal = document.getElementById("videoModal");
    const video = document.getElementById("modalVideo");
    const source = document.getElementById("modalSource");
    const text = document.getElementById("modalTitle");

    source.src = src;
    video.load();

    text.innerText = title;
    modal.style.display = "flex";
}

function closeModal() {
    const modal = document.getElementById("videoModal");
    const video = document.getElementById("modalVideo");

    video.pause();
    modal.style.display = "none";
}

function openSubtitleModal(src, title) {
    const modal = document.getElementById("subtitleModal");
    const content = document.getElementById("subtitleContent");
    const text = document.getElementById("subtitleTitle");

    text.innerText = title;

    fetch(src)
        .then(res => res.text())
        .then(data => {
            content.textContent = data;
            modal.style.display = "flex";
        })
        .catch(err => {
            content.textContent = "Failed to load subtitle file.";
            modal.style.display = "flex";
        });
}

function closeSubtitleModal() {
    document.getElementById("subtitleModal").style.display = "none";
}

function openSubtitleModal(src, title) {
    const modal = document.getElementById("subtitleModal");
    const content = document.getElementById("subtitleContent");
    const text = document.getElementById("subtitleTitle");

    text.innerText = title;

    fetch(src)
        .then(res => res.text())
        .then(data => {
            currentSubtitleText = data;
            content.textContent = data;
            modal.style.display = "flex";
        })
        .catch(() => {
            currentSubtitleText = "";
            content.textContent = "Failed to load subtitle file.";
            modal.style.display = "flex";
        });
}

function copySubtitleWithPrompt() {
    const promptHeader = `You are an AI specialized in content analysis and highlight extraction.

Your task is to analyze a YouTube video's subtitle file (.srt, auto-generated) and identify ONLY the "sweet spot" segments.

Definition of "sweet spot":
- The most valuable, insightful, engaging, or important parts of the video
- Contains key information, conclusions, unique insights, or high-impact statements
- Avoid filler, repetition, greetings, transitions, or low-value content

Strict rules:
1. Only return segments that are truly high-value (sweet spots)
2. If NO such segment exists, respond exactly with:
   TIDAK ADA
3. Do NOT include explanations, commentary, or additional text
4. Merge overlapping or very close segments into one
5. Ensure timestamps are accurate and derived from the .srt
6. Keep captions concise but meaningful (summarize if needed)
7. Duration less than 2 minutes (the shorter and more substantial the better)
8. Take the best three

Output format (JSON only):
[
  {
    "start": "hh:mm:ss",
    "end": "hh:mm:ss",
    "caption": "..."
  }
]

Input

`;

    const finalText = promptHeader + "\n" + currentSubtitleText;

    navigator.clipboard.writeText(finalText)
        .then(() => {
            alert("Copied to clipboard");
        })
        .catch(() => {
            alert("Failed to copy");
        });
}

window.onclick = function(e) {
    const modal = document.getElementById("videoModal");
    if (e.target === modal) {
        closeModal();
    }
};
</script>

</body>
</html>