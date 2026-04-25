<?php
$projectsDir = __DIR__ . "/projects";
$projects = is_dir($projectsDir) ? array_diff(scandir($projectsDir), ['.', '..']) : [];
?>

<!DOCTYPE html>
<html>
<head>
    <title>ClipperIN</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="dashboard">

    <!-- LOADING MODAL -->
    <div id="loadingModal" class="loading-modal hidden">
        <div class="loading-box">
            <div class="spinner"></div>
            <p id="loadingText">Processing...</p>
        </div>
    </div>

    <!-- HEADER -->
    <div class="header">
        <h1>ClipperIN Youtube</h1>
    </div>

    <!-- MAIN CONTENT -->
    <div class="content">

        <!-- LEFT PANEL -->
        <div class="panel main-panel">

            <form id="clipForm" action="process.php" method="POST">

                <div class="panel-box glow">
                    <h3>Project Configuration</h3>

                    <label>YouTube URL</label>
                    <div class="url-info">
                        <input type="text" name="url" id="url" required>
                        <button type="button" class="btn-secondary" onclick="loadYoutubeTitle()">Load Title</button>
                        <button type="button" class="btn-secondary" onclick="downloadTranscribe()">Download Transcribe</button>
                    </div>

                    <label>Project Name</label>
                    <input type="text" name="project_name" id="projectName" required>

                </div>

                <div class="panel-box glow json-box">
                    <h3>Paste JSON Clips</h3>

                    <textarea id="clipJson" placeholder='[
  {
    "start": "00:00:05",
    "end": "00:00:20",
    "caption": "Great Point"
  }
]'></textarea>

                    <button type="button" class="btn-secondary" onclick="loadClipsFromJson()">
                        Load from JSON
                    </button>
                </div>

                <div class="panel-box glow">
                    <h3>Clip Segments (maximum 2 minutes)</h3>

                    <div id="clips">
                        <div class="clip">
                            <input type="text" name="start[]" placeholder="Start (HH:MM:SS)">
                            <input type="text" name="end[]" placeholder="End (HH:MM:SS)">
                            <input type="text" name="caption[]" placeholder="Maximum length 64 characters">
                            <button type="button" class="btn-secondary" onclick="removeClip(this)">Delete</button>
                        </div>
                    </div>

                    <button type="button" class="btn-secondary" onclick="addClip()">+ Add Clip</button>
                </div>

                <button type="submit" class="btn-primary glow-strong">
                    Process Clips
                </button>

            </form>
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

function showLoading(text = "Processing...") {
    document.getElementById("loadingText").innerText = text;
    document.getElementById("loadingModal").classList.remove("hidden");
}

function hideLoading() {
    document.getElementById("loadingModal").classList.add("hidden");
}

function addClip() {
    const div = document.createElement('div');
    div.classList.add('clip');
    div.innerHTML = `
        <input type="text" name="start[]" placeholder="Start (HH:MM:SS)">
        <input type="text" name="end[]" placeholder="End (HH:MM:SS)">
        <input type="text" name="caption[]" placeholder="Maximum length 64 characters">
        <button type="button" class="btn-secondary" onclick="removeClip(this)">Delete</button>
    `;
    document.getElementById('clips').appendChild(div);
}

function removeClip(button) {
    const clip = button.parentElement;

    const container = document.getElementById("clips");

    if (container.children.length <= 1) {
        alert("At least one clip is required.");
        return;
    }

    clip.remove();
}

// VALIDATION + CONFIRM
document.getElementById("clipForm").addEventListener("submit", function(e) {
    e.preventDefault();

    const projectName = document.getElementById("projectName").value.trim();
    const url = document.getElementById("url").value.trim();

    if (projectName.length === 0 || projectName.length > 64) {
        alert("Project name must be between 1 and 64 characters.");
        return;
    }

    const ytRegex = /^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be)\//;
    if (!ytRegex.test(url)) {
        alert("Invalid YouTube URL.");
        return;
    }

    const starts = document.getElementsByName("start[]");
    const ends = document.getElementsByName("end[]");
    const captions = document.getElementsByName("caption[]");

    function toSeconds(time) {
        const p = time.split(":").map(Number);
        return p[0]*3600 + p[1]*60 + p[2];
    }

    for (let i = 0; i < starts.length; i++) {
        if (!starts[i].value || !ends[i].value) continue;

        const s = toSeconds(starts[i].value);
        const eTime = toSeconds(ends[i].value);

        if (eTime <= s) {
            alert(`Clip ${i+1}: End must be greater than Start`);
            return;
        }

        if ((eTime - s) > 120) {
            alert(`Clip ${i+1}: Duration must not exceed 2 minute`);
            return;
        }

        if (captions[i].length > 64) {
            alert("Captions must not exceed 64 characters.");
            return;
        }
    }

    if (confirm("Are you sure you want to process this video and generate clips?")) {
        e.target.submit();
    }
});

function loadYoutubeTitle() {
    const url = document.getElementById("url").value;

    if (!url) {
        alert("Please input YouTube URL");
        return;
    }

    showLoading("Fetching YouTube title...");

    fetch("get-title.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded"
        },
        body: "url=" + encodeURIComponent(url)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById("projectName").value = data.title;
        } else {
            alert("Error: " + data.error);
        }
    })
    .catch(err => {
        console.error(err);
        alert("Failed to fetch title");
    })
    .finally(() => {
        hideLoading();
    });
}

function downloadTranscribe() {
    const url = document.getElementById("url").value;
    const project_name = document.getElementById("projectName").value;

    if (!url) {
        alert("Please input YouTube URL");
        return;
    }

    showLoading("Downloading transcription...");

    fetch("get-transcribe.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded"
        },
        body: "url=" + encodeURIComponent(url) +
              "&project_name=" + encodeURIComponent(project_name)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert("Success to download transcribe");
            window.location.href = data.url;
        } else {
            alert("Error: " + data.message);
        }
    })
    .catch(err => {
        console.error(err);
        alert("Failed to download transcribe");
    })
    .finally(() => {
        hideLoading();
    });
}

function loadClipsFromJson() {
    const jsonText = document.getElementById("clipJson").value.trim();

    if (!jsonText) {
        alert("JSON cannot be empty.");
        return;
    }

    let data;

    try {
        data = JSON.parse(jsonText);
    } catch (e) {
        alert("Invalid JSON format.");
        return;
    }

    if (!Array.isArray(data)) {
        alert("JSON must be an array.");
        return;
    }

    const container = document.getElementById("clips");

    // reset clips
    container.innerHTML = "";

    let validCount = 0;

    data.forEach((clip, i) => {
        if (!clip.start || !clip.end) return;

        const row = document.createElement("div");
        row.classList.add("clip");

        row.innerHTML = `
            <input type="text" name="start[]" value="${clip.start}" placeholder="Start (HH:MM:SS)">
            <input type="text" name="end[]" value="${clip.end}" placeholder="End (HH:MM:SS)">
            <input type="text" name="caption[]" value="${clip.caption}" placeholder="Maximum length 64 characters">
            <button type="button" class="btn-secondary" onclick="removeClip(this)">Delete</button>
        `;

        container.appendChild(row);
        validCount++;
    });

    if (validCount === 0) {
        alert("No valid clip data found.");
    }
}
</script>

</body>
</html>