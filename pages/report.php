<?php
$pageTitle = "Report an Incident — NeighbourNet";
$activePage = 'report';
require_once __DIR__ . '/../includes/auth_guard.php';

// Authentication required to report
requireLogin();

$currentUser = getCurrentUser();
$csrfToken = getCsrfToken();
$oldData = $_SESSION['old_incident'] ?? [];
unset($_SESSION['old_incident']);

// Fetch categories and societies
$categories = [];
$societies = [];
try {
    $pdo = getDBConnection();
    $categories = $pdo->query("SELECT id, name, icon_class, color_code, description FROM categories ORDER BY id ASC")->fetchAll();
    $societies = $pdo->query("SELECT id, name, ward_sector FROM societies ORDER BY name ASC")->fetchAll();
} catch (Exception $e) {
    error_log("Error loading categories or societies: " . $e->getMessage());
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
            <!-- Breadcrumb Navigation -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb small">
                    <li class="breadcrumb-item"><a href="/neighbournet/pages/dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Report Incident</li>
                </ol>
            </nav>

            <div class="card border-0 shadow-sm rounded-3 bg-white p-4 p-md-5">
                <!-- Form Header -->
                <div class="d-flex align-items-center gap-3 mb-4 border-bottom pb-3">
                    <div class="brand-badge bg-warning text-dark" style="width: 48px; height: 48px; font-size: 1.35rem;">
                        <i class="fa-solid fa-bullhorn"></i>
                    </div>
                    <div>
                        <h2 class="fw-bold mb-1 text-navy">Report Neighbourhood Issue</h2>
                        <p class="small text-secondary mb-0">
                            Submit a local hazard or civic maintenance problem for community verification.
                        </p>
                    </div>
                </div>

                <!-- Incident Form -->
                <form id="incidentForm" action="/neighbournet/api/incidents/create.php" method="POST" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="csrf_token" id="csrfToken" value="<?= e($csrfToken) ?>">
                    <input type="hidden" id="latitude" name="latitude" value="">
                    <input type="hidden" id="longitude" name="longitude" value="">

                    <!-- Voice Reporting Hidden State Preservation -->
                    <input type="hidden" id="voice_input_used" name="voice_input_used" value="0">
                    <input type="hidden" id="original_transcript" name="original_transcript" value="">
                    <input type="hidden" id="original_language" name="original_language" value="">
                    <input type="hidden" id="translated_transcript" name="translated_transcript" value="">
                    <input type="hidden" id="translation_language" name="translation_language" value="">
                    <input type="hidden" id="selected_description_type" name="selected_description_type" value="manual">

                    <div class="alert alert-info py-2 small" role="note">
                        <strong>Offline draft:</strong> NeighbourNet can keep only your report title and description in this browser on this device. Drafts are not sent automatically. A report is submitted only after the server confirms it.
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="restoreReportDraft">Restore saved text</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="clearReportDraft">Clear saved text</button>
                            <span id="draftStatus" class="align-self-center" aria-live="polite">No local draft loaded.</span>
                        </div>
                    </div>

                    <!-- Incident Title -->
                    <div class="mb-3">
                        <label for="title" class="form-label small fw-bold text-navy">
                            Incident Title * <span class="text-muted fw-normal">(Brief, specific summary)</span>
                        </label>
                        <input type="text" class="form-control" id="title" name="title" 
                               placeholder="e.g. Deep Uncovered Manhole Near Metro Gate 2" 
                               required minlength="5" maxlength="160"
                               value="<?= e($oldData['title'] ?? '') ?>">
                        <div class="form-text" style="font-size: 0.75rem;">Between 5 and 160 characters.</div>
                    </div>

                    <!-- Category & Occurrence Time Grid -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="category_id" class="form-label small fw-bold text-navy">Category *</label>
                            <select class="form-select" id="category_id" name="category_id" required>
                                <option value="">Select issue category...</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= (int)$cat['id'] ?>" <?= ((int)($oldData['category_id'] ?? 0) === (int)$cat['id']) ? 'selected' : '' ?>>
                                        <?= e($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="incident_time" class="form-label small fw-bold text-navy">When did you notice it? *</label>
                            <input type="datetime-local" class="form-control" id="incident_time" name="incident_time" 
                                   value="<?= date('Y-m-d\TH:i') ?>" required>
                        </div>
                    </div>

                    <!-- Society / Area -->
                    <div class="mb-3">
                        <label for="society_id" class="form-label small fw-bold text-navy">Society, Area, or Sector (Optional)</label>
                        <select class="form-select" id="society_id" name="society_id">
                            <option value="">Select local neighbourhood / ward...</option>
                            <?php foreach ($societies as $soc): ?>
                                <option value="<?= (int)$soc['id'] ?>" <?= ((int)($oldData['society_id'] ?? ($currentUser['society_id'] ?? 0)) === (int)$soc['id']) ? 'selected' : '' ?>>
                                    <?= e($soc['name']) ?> (<?= e($soc['ward_sector']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Location Input: GPS or Manual -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="location_text" class="form-label small fw-bold text-navy mb-0">
                                Location & Landmark *
                            </label>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btnDetectGps">
                                <i class="fa-solid fa-location-crosshairs me-1"></i> Detect My GPS (Optional)
                            </button>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-map-pin"></i></span>
                            <input type="text" class="form-control" id="location_text" name="location_text" 
                                   placeholder="e.g. Opposite Green Valley Community Park, Main Road" required
                                   value="<?= e($oldData['location_text'] ?? '') ?>">
                        </div>
                        <div id="gpsStatusMessage" class="small text-muted mt-1" style="font-size: 0.78rem;">
                            <i class="fa-solid fa-circle-info me-1"></i> GPS is optional. You can enter the street or landmark manually.
                        </div>
                    </div>

                    <!-- Interactive Pin Dropper Map -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small fw-semibold text-secondary">Pin Point on Map (Optional)</span>
                            <span class="badge bg-light text-secondary border small">Click anywhere on map to reposition pin</span>
                        </div>
                        <div id="reportMapPicker" class="report-map-picker mb-2"></div>
                    </div>

                    <!-- ========================================================
                         PROMPT 5: VOICE REPORTING MODULE (HINDI & ENGLISH)
                         ======================================================== -->
                    <div class="card border-primary border-opacity-25 bg-light rounded-3 p-3 mb-4">
                        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center border-bottom pb-2 mb-3 gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-primary px-2 py-1"><i class="fa-solid fa-microphone me-1"></i>Voice Reporting</span>
                                <span class="fw-bold text-navy small">Speak in Hindi or English</span>
                            </div>
                            <span class="badge bg-white text-secondary border small">Web Speech API</span>
                        </div>

                        <!-- Privacy & Mic Access Notice -->
                        <div class="small text-muted mb-3" style="font-size: 0.8rem;">
                            <i class="fa-solid fa-shield-halved text-primary me-1"></i>
                            Microphone permission is requested only when you start recording. Audio is processed locally in your browser for transcription and is never stored on our servers.
                        </div>

                        <!-- Language & Translation Mode Selector Grid -->
                        <div class="row g-2 mb-3">
                            <div class="col-sm-6">
                                <label for="voice_language" class="form-label small fw-bold text-secondary mb-1">Spoken Language</label>
                                <select class="form-select form-select-sm" id="voice_language">
                                    <option value="hi-IN" selected>Hindi (हिन्दी) — hi-IN</option>
                                    <option value="en-IN">English (Indian) — en-IN</option>
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label for="voice_mode" class="form-label small fw-bold text-secondary mb-1">Transcription & Translation Mode</label>
                                <select class="form-select form-select-sm" id="voice_mode">
                                    <option value="transcribe_only">Transcription Only</option>
                                    <option value="transcribe_and_translate" selected>Transcription + Translation</option>
                                </select>
                            </div>
                        </div>

                        <!-- Speech Controls: Mic Button & Status Indicator -->
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                            <button type="button" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-2" id="btnStartVoice">
                                <i class="fa-solid fa-microphone" id="micIcon"></i>
                                <span id="micButtonText">Start Voice Recording</span>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger d-none" id="btnStopVoice">
                                <i class="fa-solid fa-stop me-1"></i> Stop Recording
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="btnRetryVoice">
                                <i class="fa-solid fa-rotate-right me-1"></i> Retry
                            </button>
                            <div id="voiceStatusBadge" class="d-none">
                                <span class="badge bg-danger text-white py-2 px-3 animate-pulse">
                                    <i class="fa-solid fa-record-vinyl me-1 fa-fade"></i> Listening... Speak now
                                </span>
                            </div>
                        </div>

                        <!-- Error Banner (if mic denied or API unavailable) -->
                        <div id="voiceErrorAlert" class="alert alert-warning alert-dismissible fade show d-none small py-2 mb-3" role="alert">
                            <span id="voiceErrorMessage"></span>
                            <button type="button" class="btn-close py-2" onclick="document.getElementById('voiceErrorAlert').classList.add('d-none')"></button>
                        </div>

                        <!-- Voice Transcription & Translation Review / Edit Panel -->
                        <div id="voiceResultPanel" class="d-none">
                            <div class="row g-3">
                                <!-- Original Transcript Box -->
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-navy d-flex justify-content-between mb-1">
                                        <span>Original Transcript (<span id="origLangLabel">Hindi</span>)</span>
                                        <small class="text-muted">Editable</small>
                                    </label>
                                    <textarea class="form-control form-control-sm" id="origTranscriptText" rows="3" placeholder="Recognized speech will appear here..."></textarea>
                                </div>

                                <!-- Translated Text Box (if translation requested) -->
                                <div class="col-md-6" id="translatedCol">
                                    <label class="form-label small fw-bold text-navy d-flex justify-content-between mb-1">
                                        <span>Translated Text (<span id="transLangLabel">English</span>)</span>
                                        <small class="text-muted" id="translationProviderBadge">Provider</small>
                                    </label>
                                    <textarea class="form-control form-control-sm" id="transResultText" rows="3" placeholder="Translated text will appear here..."></textarea>
                                </div>
                            </div>

                            <!-- Choice: Which version to submit? -->
                            <div class="mt-3 p-2 bg-white rounded-2 border">
                                <span class="small fw-bold text-navy d-block mb-2">Apply to Final Incident Description:</span>
                                <div class="d-flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnUseOriginal">
                                        <i class="fa-solid fa-file-lines me-1"></i> Use Original
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-success" id="btnUseTranslated">
                                        <i class="fa-solid fa-language me-1"></i> Use Translated
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnUseCombined">
                                        <i class="fa-solid fa-object-ungroup me-1"></i> Combine Both (Bilingual)
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Detailed Description (Directly editable or populated by Voice) -->
                    <div class="mb-3">
                        <label for="description" class="form-label small fw-bold text-navy">
                            Incident Description * <span class="text-muted fw-normal">(Type manually or speak using the voice tool above)</span>
                        </label>
                        <textarea class="form-control" id="description" name="description" rows="4" 
                                  placeholder="Describe the issue, its severity, whether it poses an immediate hazard, and any temporary warning placed..."
                                  required minlength="10"><?= e($oldData['description'] ?? '') ?></textarea>
                        <div class="form-text" style="font-size: 0.75rem;">Minimum 10 characters.</div>
                    </div>

                    <!-- Photo Upload with Preview -->
                    <div class="mb-3">
                        <label for="photo" class="form-label small fw-bold text-navy">Attach Photo (Optional)</label>
                        <input class="form-control" type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp">
                        <div class="form-text" style="font-size: 0.75rem;">Supported formats: JPG, PNG, WEBP. Maximum file size: 5MB.</div>
                        <div id="imagePreviewContainer" class="mt-2 d-none">
                            <img id="imagePreview" src="#" alt="Photo Preview" class="img-thumbnail" style="max-height: 180px;">
                        </div>
                    </div>

                    <!-- Optional Additional Information -->
                    <div class="mb-4">
                        <label for="additional_info" class="form-label small fw-bold text-navy">Additional Information (Optional)</label>
                        <input type="text" class="form-control" id="additional_info" name="additional_info" 
                               placeholder="e.g. Electric wire sparking intermittently; Municipal contractor contacted yesterday">
                    </div>

                    <!-- Submit Button & Anti-Duplicate Mechanism -->
                    <div class="d-grid gap-2">
                        <button type="submit" id="btnSubmitReport" class="btn btn-civic-primary py-2 fs-6">
                            <span id="submitText"><i class="fa-solid fa-paper-plane me-1"></i> Submit Incident Report</span>
                            <span id="submitSpinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
                        </button>
                        <a href="/neighbournet/pages/dashboard.php" class="btn btn-link text-secondary text-decoration-none small text-center">
                            Cancel and return to dashboard
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // ----------------------------------------------------
    // 1. Photo preview handler
    // ----------------------------------------------------
    const photoInput = document.getElementById('photo');
    const previewContainer = document.getElementById('imagePreviewContainer');
    const previewImg = document.getElementById('imagePreview');

    photoInput.addEventListener('change', () => {
        const file = photoInput.files[0];
        if (file) {
            if (file.size > 5 * 1024 * 1024) {
                alert('File size exceeds 5MB limit. Please choose a smaller photo.');
                photoInput.value = '';
                previewContainer.classList.add('d-none');
                return;
            }
            const reader = new FileReader();
            reader.onload = (e) => {
                previewImg.src = e.target.result;
                previewContainer.classList.remove('d-none');
            };
            reader.readAsDataURL(file);
        } else {
            previewContainer.classList.add('d-none');
        }
    });

    // ----------------------------------------------------
    // 2. Leaflet Mini-Map for Pin Dropping
    // ----------------------------------------------------
    const defaultLat = 28.6139;
    const defaultLng = 77.2090;
    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');

    const map = L.map('reportMapPicker').setView([defaultLat, defaultLng], 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    let marker = null;

    function setPin(lat, lng) {
        latInput.value = lat.toFixed(8);
        lngInput.value = lng.toFixed(8);
        if (marker) {
            marker.setLatLng([lat, lng]);
        } else {
            marker = L.marker([lat, lng], { draggable: true }).addTo(map);
            marker.on('dragend', (e) => {
                const pos = e.target.getLatLng();
                latInput.value = pos.lat.toFixed(8);
                lngInput.value = pos.lng.toFixed(8);
            });
        }
    }

    setPin(defaultLat, defaultLng);

    map.on('click', (e) => {
        setPin(e.latlng.lat, e.latlng.lng);
    });

    // ----------------------------------------------------
    // 3. Optional GPS Detection
    // ----------------------------------------------------
    const btnGps = document.getElementById('btnDetectGps');
    const gpsMsg = document.getElementById('gpsStatusMessage');

    btnGps.addEventListener('click', () => {
        if (!navigator.geolocation) {
            alert('Geolocation is not supported by your browser. Please enter location manually.');
            return;
        }

        btnGps.disabled = true;
        btnGps.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Locating...';

        navigator.geolocation.getCurrentPosition(
            (pos) => {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                map.setView([lat, lng], 16);
                setPin(lat, lng);
                gpsMsg.innerHTML = `<span class="text-success"><i class="fa-solid fa-circle-check me-1"></i> GPS Coordinates captured: ${lat.toFixed(5)}, ${lng.toFixed(5)}</span>`;
                btnGps.disabled = false;
                btnGps.innerHTML = '<i class="fa-solid fa-location-crosshairs me-1"></i> Re-detect GPS';
            },
            (err) => {
                btnGps.disabled = false;
                btnGps.innerHTML = '<i class="fa-solid fa-location-crosshairs me-1"></i> Detect My GPS (Optional)';
                gpsMsg.textContent = `GPS access denied or unavailable (${err.message}). Manual entry is active.`;
            },
            { enableHighAccuracy: true, timeout: 8000 }
        );
    });

    // ----------------------------------------------------
    // 4. Web Speech Recognition & Translation (Prompt 5)
    // ----------------------------------------------------
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    const btnStartVoice = document.getElementById('btnStartVoice');
    const btnStopVoice = document.getElementById('btnStopVoice');
    const btnRetryVoice = document.getElementById('btnRetryVoice');
    const voiceStatusBadge = document.getElementById('voiceStatusBadge');
    const voiceErrorAlert = document.getElementById('voiceErrorAlert');
    const voiceErrorMessage = document.getElementById('voiceErrorMessage');
    const voiceResultPanel = document.getElementById('voiceResultPanel');
    const origTranscriptText = document.getElementById('origTranscriptText');
    const transResultText = document.getElementById('transResultText');
    const translatedCol = document.getElementById('translatedCol');
    const translationProviderBadge = document.getElementById('translationProviderBadge');
    const voiceLangSelect = document.getElementById('voice_language');
    const voiceModeSelect = document.getElementById('voice_mode');
    const origLangLabel = document.getElementById('origLangLabel');
    const transLangLabel = document.getElementById('transLangLabel');
    const descriptionText = document.getElementById('description');
    const csrfToken = document.getElementById('csrfToken').value;

    // Hidden form inputs
    const voiceUsedInput = document.getElementById('voice_input_used');
    const origTranscriptInput = document.getElementById('original_transcript');
    const origLangInput = document.getElementById('original_language');
    const transTranscriptInput = document.getElementById('translated_transcript');
    const transLangInput = document.getElementById('translation_language');
    const selectedDescTypeInput = document.getElementById('selected_description_type');

    let recognition = null;
    let isRecording = false;

    // Feature Detection
    if (!SpeechRecognition) {
        voiceErrorMessage.innerHTML = '<strong>Speech Recognition Unsupported:</strong> Your browser does not support the Web Speech API. You can continue typing your description manually.';
        voiceErrorAlert.classList.remove('d-none');
        btnStartVoice.disabled = true;
    } else {
        recognition = new SpeechRecognition();
        recognition.continuous = false;
        recognition.interimResults = false;
        recognition.maxAlternatives = 1;

        recognition.onstart = () => {
            isRecording = true;
            voiceErrorAlert.classList.add('d-none');
            voiceStatusBadge.classList.remove('d-none');
            btnStartVoice.classList.add('d-none');
            btnStopVoice.classList.remove('d-none');
            btnRetryVoice.classList.add('d-none');
        };

        recognition.onresult = async (event) => {
            const transcript = event.results[0][0].transcript;
            origTranscriptText.value = transcript;
            voiceResultPanel.classList.remove('d-none');

            const spokenLocale = voiceLangSelect.value; // 'hi-IN' or 'en-IN'
            const isHindi = spokenLocale.startsWith('hi');
            const sourceLang = isHindi ? 'hi' : 'en';
            const targetLang = isHindi ? 'en' : 'hi';

            origLangLabel.innerText = isHindi ? 'Hindi' : 'English';
            transLangLabel.innerText = isHindi ? 'English' : 'Hindi';

            // Update hidden inputs
            voiceUsedInput.value = '1';
            origTranscriptInput.value = transcript;
            origLangInput.value = sourceLang;

            const mode = voiceModeSelect.value;
            if (mode === 'transcribe_and_translate') {
                translatedCol.classList.remove('d-none');
                transResultText.value = 'Translating transcript...';
                translationProviderBadge.innerText = 'Connecting...';

                try {
                    const res = await fetch('/neighbournet/api/incidents/translate.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            csrf_token: csrfToken,
                            text: transcript,
                            source_lang: sourceLang,
                            target_lang: targetLang
                        })
                    });

                    const data = await res.json();
                    if (res.ok && data.success) {
                        transResultText.value = data.translated_text;
                        translationProviderBadge.innerText = data.provider || 'Translated';
                        transTranscriptInput.value = data.translated_text;
                        transLangInput.value = targetLang;

                        // Default populate main description with translated text (or original if en)
                        descriptionText.value = data.translated_text;
                        selectedDescTypeInput.value = 'translated';
                    } else {
                        transResultText.value = '';
                        translationProviderBadge.innerText = 'Provider Error';
                        voiceErrorMessage.textContent = `Translation Notice: ${data.message || 'Translation unavailable. You can use your original transcript.'}`;
                        voiceErrorAlert.classList.remove('d-none');

                        // Fallback to original
                        descriptionText.value = transcript;
                        selectedDescTypeInput.value = 'original';
                    }
                } catch (err) {
                    transResultText.value = '';
                    translationProviderBadge.innerText = 'Offline';
                    voiceErrorMessage.innerHTML = '<strong>Translation Unreachable:</strong> Could not connect to translation endpoint. Using original transcript.';
                    voiceErrorAlert.classList.remove('d-none');
                    descriptionText.value = transcript;
                    selectedDescTypeInput.value = 'original';
                }
            } else {
                // Transcribe only
                translatedCol.classList.add('d-none');
                descriptionText.value = transcript;
                selectedDescTypeInput.value = 'original';
                transTranscriptInput.value = '';
                transLangInput.value = '';
            }
        };

        recognition.onerror = (event) => {
            isRecording = false;
            voiceStatusBadge.classList.add('d-none');
            btnStartVoice.classList.remove('d-none');
            btnStopVoice.classList.add('d-none');
            btnRetryVoice.classList.remove('d-none');

            let msg = `Microphone error: ${event.error}.`;
            if (event.error === 'not-allowed') {
                msg = '<strong>Microphone Access Denied:</strong> Please allow microphone access in your browser to use voice reporting, or enter description manually.';
            } else if (event.error === 'no-speech') {
                msg = 'No speech was detected. Please click Retry and speak clearly.';
            }
            voiceErrorMessage.textContent = msg.replace(/<[^>]*>/g, '');
            voiceErrorAlert.classList.remove('d-none');
        };

        recognition.onend = () => {
            isRecording = false;
            voiceStatusBadge.classList.add('d-none');
            btnStartVoice.classList.remove('d-none');
            btnStopVoice.classList.add('d-none');
            btnRetryVoice.classList.remove('d-none');
        };

        btnStartVoice.addEventListener('click', () => {
            const lang = voiceLangSelect.value;
            recognition.lang = lang;
            try {
                recognition.start();
            } catch (e) {
                console.warn('Recognition start caught:', e);
            }
        });

        btnStopVoice.addEventListener('click', () => {
            if (isRecording) {
                recognition.stop();
            }
        });

        btnRetryVoice.addEventListener('click', () => {
            const lang = voiceLangSelect.value;
            recognition.lang = lang;
            try {
                recognition.start();
            } catch (e) {
                console.warn('Recognition retry caught:', e);
            }
        });
    }

    // Buttons to choose which text to use
    document.getElementById('btnUseOriginal').addEventListener('click', () => {
        const text = origTranscriptText.value.trim();
        if (text) {
            descriptionText.value = text;
            origTranscriptInput.value = text;
            selectedDescTypeInput.value = 'original';
            NeighbourNet.showAlert('Applied Original Transcript to final description.', 'info');
        }
    });

    document.getElementById('btnUseTranslated').addEventListener('click', () => {
        const text = transResultText.value.trim();
        if (text) {
            descriptionText.value = text;
            transTranscriptInput.value = text;
            selectedDescTypeInput.value = 'translated';
            NeighbourNet.showAlert('Applied Translated Text to final description.', 'success');
        }
    });

    document.getElementById('btnUseCombined').addEventListener('click', () => {
        const orig = origTranscriptText.value.trim();
        const trans = transResultText.value.trim();
        if (orig || trans) {
            const combined = `${orig}\n\n[Translation / अनुवाद]:\n${trans}`;
            descriptionText.value = combined;
            origTranscriptInput.value = orig;
            transTranscriptInput.value = trans;
            selectedDescTypeInput.value = 'combined';
            NeighbourNet.showAlert('Applied Bilingual Combined description.', 'primary');
        }
    });

    // Sync manual edits in transcript boxes to hidden inputs
    origTranscriptText.addEventListener('input', () => {
        origTranscriptInput.value = origTranscriptText.value;
    });

    transResultText.addEventListener('input', () => {
        transTranscriptInput.value = transResultText.value;
    });

    // ----------------------------------------------------
    // 5. Anti-Duplicate Form Submission Handler
    // ----------------------------------------------------
    const form = document.getElementById('incidentForm');
    const submitBtn = document.getElementById('btnSubmitReport');
    const submitText = document.getElementById('submitText');
    const submitSpinner = document.getElementById('submitSpinner');
    const draftStatus = document.getElementById('draftStatus');
    const draftKey = 'neighbournet_report_draft_v1';
    const draftFields = { title: document.getElementById('title'), description: descriptionText };

    // Store only user-authored title and description. No location, identity, image,
    // coordinates, transcripts, CSRF token, or account data are persisted.
    const saveDraft = () => {
        try {
            const draft = { title: draftFields.title.value.slice(0, 160), description: draftFields.description.value.slice(0, 12000), savedAt: Date.now() };
            if (!draft.title && !draft.description) { localStorage.removeItem(draftKey); return; }
            localStorage.setItem(draftKey, JSON.stringify(draft));
            if (draftStatus) draftStatus.textContent = 'Text draft saved on this device.';
        } catch (error) {
            if (draftStatus) draftStatus.textContent = 'Local draft storage is unavailable in this browser.';
        }
    };
    let draftTimer;
    Object.values(draftFields).forEach(field => field.addEventListener('input', () => {
        clearTimeout(draftTimer); draftTimer = setTimeout(saveDraft, 350);
    }));
    document.getElementById('restoreReportDraft')?.addEventListener('click', () => {
        try {
            const draft = JSON.parse(localStorage.getItem(draftKey) || 'null');
            if (!draft) { if (draftStatus) draftStatus.textContent = 'No saved text draft found.'; return; }
            draftFields.title.value = String(draft.title || '').slice(0,160);
            draftFields.description.value = String(draft.description || '').slice(0,12000);
            if (draftStatus) draftStatus.textContent = 'Saved text restored. Review it before submitting.';
        } catch (error) { if (draftStatus) draftStatus.textContent = 'Could not restore the saved text.'; }
    });
    document.getElementById('clearReportDraft')?.addEventListener('click', () => {
        try { localStorage.removeItem(draftKey); if (draftStatus) draftStatus.textContent = 'Saved text cleared from this device.'; }
        catch (error) { if (draftStatus) draftStatus.textContent = 'Could not clear local draft storage.'; }
    });

    form.addEventListener('submit', (event) => {
        if (!form.checkValidity()) {
            event.preventDefault();
            form.reportValidity();
            return;
        }
        if (!navigator.onLine) {
            event.preventDefault();
            if (draftStatus) draftStatus.textContent = 'Not submitted: reconnect to the internet, then submit again.';
            NeighbourNet.showAlert('You are offline. Your report has NOT been submitted. Your title and description remain saved on this device.', 'warning');
            return;
        }
        // Do not show success here. The server response/redirect is authoritative.
        submitBtn.disabled = true;
        submitText.classList.add('d-none');
        submitSpinner.classList.remove('d-none');
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
