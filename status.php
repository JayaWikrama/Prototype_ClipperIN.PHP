<?php

$projectsDir = __DIR__ . "/projects";
$projects = is_dir($projectsDir) ? array_diff(scandir($projectsDir), ['.', '..']) : [];

$project = $_GET['project'] ?? '';
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="dashboard">

    <div class="header">
        <h1>Processing: <?php echo htmlspecialchars($project); ?></h1>
        <a href="index.php" class="back-btn" title="Back to Home">← Home</a>
    </div>

    <div class="content">

        <!-- LEFT PANEL -->
        <div class="panel">

            <h3>Downloading Progress</h3>

            <!-- PROGRESS BAR -->
            <div class="progress-bar">
                <div id="progressFillPart"></div>
            </div>

            <p id="progressTextPart">Initializing...</p>

            <h3>Editing Progress</h3>

            <!-- PROGRESS BAR VIDEO EDITING -->
            <div class="progress-bar">
                <div id="progressFill"></div>
            </div>

            <p id="progressText">Initializing...</p>

            <!-- FILE GRID -->
            <div id="fileGrid" class="file-grid"></div>

            <!-- DOWNLOAD LINK -->
            <div id="doneSection" style="display:none;">
                <a class="btn-primary" id="downloadLink">Open Project</a>
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
const project = "<?php echo $project; ?>";

let lastFileCount = 0;
let lastDownloadedCount = 0;
let lastPartSignature = "";

function updateProgress() {
    fetch(`progress.php?project=${project}`)
        .then(res => res.json())
        .then(data => {

            if (data.error) return;

            const total = data.total;
            const done = data.completed;

            if (done === total) {
                lastDownloadedCount = done;
            }

            const percentPart = total ? (lastDownloadedCount / total) * 100 : 0;
            const percent = total ? (done / total) * 100 : 0;

            if (lastDownloadedCount > 0 || done > 0 || lastPartSignature.length > 0) {
                document.getElementById("progressFillPart").style.width = percentPart + "%";
                document.getElementById("progressTextPart").innerText =
                    `Downloaded ${lastDownloadedCount} / ${total}`;

                document.getElementById("progressFill").style.width = percent + "%";
                document.getElementById("progressText").innerText =
                    `Processed ${done} / ${total}`;
            }

            const grid = document.getElementById("fileGrid");

            // signature untuk file .part (name + size)
            const partSignature = data.downloading
                .map(f => f.name + ":" + f.size)
                .join("|");

            const shouldRender =
                data.files.length !== lastFileCount ||
                data.downloaded.length > lastDownloadedCount ||
                partSignature !== lastPartSignature;

            if (shouldRender) {

                grid.innerHTML = "";

                if (data.downloaded.length > lastDownloadedCount) {
                    lastDownloadedCount = data.downloaded.length;
                }

                // FILE SELESAI
                data.files.forEach(file => {
                    const card = document.createElement("div");
                    card.className = "file-card";

                    card.innerHTML = `
                        <video class="thumb" preload="metadata">
                            <source src="projects/${project}/${file}#t=1" type="video/mp4">
                        </video>
                        <p>${file}</p>
                    `;

                    grid.appendChild(card);
                });

                // FILE TERDOWNLOAD
                data.downloaded.forEach(file => {
                    const card = document.createElement("div");
                    card.className = "file-card";

                    card.innerHTML = `
                        <video class="thumb" preload="metadata">
                            <source src="projects/${project}/${file}#t=1" type="video/mp4">
                        </video>
                        <p>${file}</p>
                    `;

                    grid.appendChild(card);
                });

                // FILE SEDANG DOWNLOAD
                data.downloading.forEach(file => {

                    const sizeMB = (file.size / (1024 * 1024)).toFixed(2);

                    const card = document.createElement("div");
                    card.className = "file-card";

                    card.innerHTML = `
                        <div class="thumb placeholder"></div>
                        <p>${file.name}</p>

                        <div class="mini-progress">
                            <div class="mini-fill"></div>
                        </div>

                        <p class="muted">${sizeMB} MB (${lastDownloadedCount === total ? "editing..." : "downloading..."})</p>
                    `;

                    grid.appendChild(card);
                });

                lastFileCount = data.files.length;

                lastPartSignature = partSignature;
            }

            if (total > 0 && done === total) {
                document.getElementById("doneSection").style.display = "block";
                document.getElementById("downloadLink").href =
                    `download.php?project=${project}`;
                document.getElementById("progressText").innerText = "Completed";
            } else {
                setTimeout(updateProgress, 500);
            }

        });
}

updateProgress();
</script>

</body>
</html>