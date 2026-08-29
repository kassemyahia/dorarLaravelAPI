<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Hadith Enrichment Tool</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            direction: rtl;
            text-align: right;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            padding: 40px;
        }

        h1 {
            color: #333;
            margin-bottom: 30px;
            text-align: center;
            font-size: 28px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 600;
            font-size: 14px;
        }

        input[type="file"],
        input[type="number"],
        select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        input[type="file"]:focus,
        input[type="number"]:focus,
        select:focus {
            outline: none;
            border-color: #667eea;
        }

        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }

        .file-input-label {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: #f5f5f5;
            border: 2px dashed #667eea;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .file-input-label:hover {
            background: #efefff;
            border-color: #764ba2;
        }

        #file-name {
            margin-top: 8px;
            color: #667eea;
            font-size: 13px;
            font-weight: 500;
        }

        .settings-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .button-group {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 30px;
        }

        button {
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        #startBtn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            min-width: 150px;
        }

        #startBtn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        #startBtn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .pause-resume-cancel {
            display: none;
            gap: 10px;
        }

        .pause-resume-cancel button {
            min-width: 100px;
            font-size: 13px;
            padding: 10px 20px;
        }

        #pauseBtn { background: #ff9800; color: white; }
        #pauseBtn:hover { background: #f57c00; }

        #resumeBtn { background: #4caf50; color: white; }
        #resumeBtn:hover { background: #45a049; }

        #cancelBtn { background: #f44336; color: white; }
        #cancelBtn:hover { background: #da190b; }

        #downloadGroup {
            display: none;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
        }

        #downloadGroup a,
        #downloadGroup button {
            padding: 10px 20px;
            background: #2196F3;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: background 0.3s;
        }

        #downloadGroup a:hover,
        #downloadGroup button:hover {
            background: #0b7dda;
        }

        /* Progress section */
        .progress-section {
            display: none;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px solid #e0e0e0;
        }

        .progress-section.visible {
            display: block;
        }

        .progress-bar-container {
            background: #f0f0f0;
            border-radius: 10px;
            height: 30px;
            overflow: hidden;
            margin-bottom: 15px;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            width: 0%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 12px;
            transition: width 0.3s ease;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 20px;
        }

        .stat-card {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 6px;
            text-align: center;
            border-left: 4px solid #667eea;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
        }

        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 12px;
            border-radius: 6px;
            margin-top: 15px;
            display: none;
            border-left: 4px solid #c62828;
        }

        .error-message.visible {
            display: block;
        }

        .success-message {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 12px;
            border-radius: 6px;
            margin-top: 15px;
            display: none;
            border-left: 4px solid #2e7d32;
        }

        .success-message.visible {
            display: block;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 10px;
            text-transform: uppercase;
        }

        .badge-pending { background: #e3f2fd; color: #1565c0; }
        .badge-processing { background: #fff3e0; color: #e65100; }
        .badge-completed { background: #e8f5e9; color: #2e7d32; }
        .badge-paused { background: #f3e5f5; color: #6a1b9a; }
        .badge-failed { background: #ffebee; color: #c62828; }
        .badge-cancelled { background: #f1f1f1; color: #666; }

        .loading-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 8px;
            vertical-align: middle;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .info-text {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }

        @media (max-width: 600px) {
            .container {
                padding: 20px;
            }

            .settings-row {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .button-group {
                flex-direction: column;
            }

            button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>أداة إثراء الأحاديث</h1>
        <h2 style="text-align: center; color: #999; font-size: 14px; margin-bottom: 30px;">Hadith Enrichment Tool</h2>

        <form id="uploadForm">
            <div class="form-group">
                <label for="file">اختر ملف JSON</label>
                <div class="file-input-wrapper">
                    <label for="file" class="file-input-label">
                        📁 اضغط لاختيار ملف أو اسحب ملف هنا
                    </label>
                    <input type="file" id="file" name="file" accept=".json" style="display: none;">
                </div>
                <div id="file-name"></div>
            </div>

            <div class="form-group">
                <label>الإعدادات</label>
                <div class="settings-row">
                    <div>
                        <label for="delay">تأخير بين الطلبات (ms)</label>
                        <input type="number" id="delay" name="delay" value="5000" min="1000" max="30000">
                        <div class="info-text">الافتراضي: 5000ms (5 ثوان)</div>
                    </div>
                    <div>
                        <label for="confidence">حد الثقة الأدنى</label>
                        <input type="number" id="confidence" name="confidence" value="0.80" min="0" max="1" step="0.01">
                        <div class="info-text">الافتراضي: 0.80</div>
                    </div>
                </div>
            </div>

            <div class="button-group">
                <button type="submit" id="startBtn">ابدأ المعالجة</button>
                <div class="pause-resume-cancel">
                    <button type="button" id="pauseBtn">إيقاف مؤقت</button>
                    <button type="button" id="resumeBtn">استئناف</button>
                    <button type="button" id="cancelBtn">إلغاء</button>
                </div>
            </div>

            <div class="error-message" id="errorMsg"></div>
            <div class="success-message" id="successMsg"></div>
        </form>

        <!-- Progress Section -->
        <div class="progress-section" id="progressSection">
            <h3 style="margin-bottom: 15px; color: #333;">التقدم</h3>
            <div class="progress-bar-container">
                <div class="progress-bar" id="progressBar" style="width: 0%">0%</div>
            </div>

            <div style="text-align: center; margin-bottom: 15px;">
                <span id="statusText"></span>
                <div id="statusBadge" class="status-badge badge-pending">جاهز</div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value" id="totalStat">0</div>
                    <div class="stat-label">المجموع</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="processedStat">0</div>
                    <div class="stat-label">تمت معالجته</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="matchedStat">0</div>
                    <div class="stat-label">موجودة</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="notFoundStat">0</div>
                    <div class="stat-label">لم يتم العثور عليها</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="failedStat">0</div>
                    <div class="stat-label">فشلت</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="reviewStat">0</div>
                    <div class="stat-label">بحاجة للمراجعة</div>
                </div>
            </div>

            <div id="downloadGroup">
                <a id="downloadJsonBtn" href="#" download>تحميل JSON</a>
                <a id="downloadCsvBtn" href="#" download>تحميل CSV</a>
            </div>
        </div>
    </div>

    <script>
        const form = document.getElementById('uploadForm');
        const fileInput = document.getElementById('file');
        const startBtn = document.getElementById('startBtn');
        const pauseResumeCancel = document.querySelector('.pause-resume-cancel');
        const progressSection = document.getElementById('progressSection');
        const errorMsg = document.getElementById('errorMsg');
        const successMsg = document.getElementById('successMsg');
        const fileNameDisplay = document.getElementById('file-name');

        let currentJobId = null;
        let pollInterval = null;
        let pendingPolls = 0;
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        // File selection
        fileInput.addEventListener('change', (e) => {
            const fileName = e.target.files[0]?.name;
            fileNameDisplay.textContent = fileName ? `✓ ${fileName}` : '';
        });

        // Drag and drop
        const fileLabel = document.querySelector('.file-input-label');
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            fileLabel.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            fileLabel.addEventListener(eventName, () => {
                fileLabel.style.background = '#efefff';
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            fileLabel.addEventListener(eventName, () => {
                fileLabel.style.background = '#f5f5f5';
            });
        });

        fileLabel.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            fileInput.files = files;
            fileNameDisplay.textContent = files[0]?.name ? `✓ ${files[0].name}` : '';
        });

        // Form submission
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            if (!fileInput.files.length) {
                showError('يرجى اختيار ملف JSON');
                return;
            }

            const formData = new FormData();
            formData.append('file', fileInput.files[0]);
            formData.append('delay_ms', document.getElementById('delay').value);
            formData.append('confidence_threshold_low', document.getElementById('confidence').value);

            startBtn.disabled = true;
            showError('');

            try {
                const response = await fetch('/v1/api/enrichment/import', {
                    method: 'POST',
                    headers: {'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json'},
                    body: formData,
                });
                const data = await parseResponse(response);

                if (!response.ok) {
                    showError(data.message || 'خطأ في تحميل الملف');
                    startBtn.disabled = false;
                    return;
                }

                currentJobId = data.data.jobId;
                showSuccess(`تمت إضافة المهمة #${currentJobId} إلى قائمة الانتظار (${data.data.total} حديث).`);
                progressSection.classList.add('visible');
                pauseResumeCancel.style.display = 'flex';
                fileInput.disabled = true;
                document.getElementById('delay').disabled = true;
                document.getElementById('confidence').disabled = true;

                startPolling();
            } catch (error) {
                showError('خطأ في الاتصال: ' + error.message);
                startBtn.disabled = false;
            }
        });

        // Polling
        function startPolling() {
            if (pollInterval) return;
            pollStatus();
            pollInterval = setInterval(pollStatus, 2000);
        }

        async function pollStatus() {
            if (!currentJobId) return;

            try {
                const response = await fetch(`/v1/api/enrichment/jobs/${currentJobId}`);
                const data = await parseResponse(response);

                if (!response.ok) {
                    stopPolling();
                    showError('فشل في جلب حالة المهمة');
                    return;
                }

                updateUI(data.data);

                if (['completed', 'failed', 'cancelled'].includes(data.data.status)) {
                    stopPolling();
                }
            } catch (error) {
                console.error('Polling error:', error);
            }
        }

        function updateUI(job) {
            const progress = job.progress;

            pendingPolls = job.status === 'pending' ? pendingPolls + 1 : 0;
            if (pendingPolls >= 3) {
                showError('The job is waiting for a queue worker. Run: php artisan queue:work --tries=1 --timeout=600');
            } else if (!job.errorMessage && job.status !== 'failed') {
                showError('');
            }

            // Update progress bar
            const percentage = Math.round(progress.percentage);
            document.getElementById('progressBar').style.width = percentage + '%';
            document.getElementById('progressBar').textContent = percentage + '%';

            // Update stats
            document.getElementById('totalStat').textContent = progress.total;
            document.getElementById('processedStat').textContent = progress.processed;
            document.getElementById('matchedStat').textContent = progress.matched;
            document.getElementById('notFoundStat').textContent = progress.notFound;
            document.getElementById('failedStat').textContent = progress.failed;
            document.getElementById('reviewStat').textContent = progress.needsReview;

            // Update status
            const statusText = `الحالة: ${getStatusText(job.status)}`;
            document.getElementById('statusText').textContent = statusText;
            document.getElementById('statusBadge').className = 'status-badge badge-' + job.status;
            document.getElementById('statusBadge').textContent = getStatusText(job.status);

            document.getElementById('pauseBtn').style.display = ['pending','processing'].includes(job.status) ? '' : 'none';
            document.getElementById('resumeBtn').style.display = job.status === 'paused' ? '' : 'none';
            document.getElementById('cancelBtn').style.display = ['pending','processing','paused'].includes(job.status) ? '' : 'none';
            if (job.errorMessage) showError(job.errorMessage);
            if (job.exportable) {
                document.getElementById('downloadGroup').style.display = 'flex';
                document.getElementById('downloadJsonBtn').href = `/v1/api/enrichment/jobs/${currentJobId}/download/json`;
                document.getElementById('downloadCsvBtn').href = `/v1/api/enrichment/jobs/${currentJobId}/download/csv`;
            }
        }

        function getStatusText(status) {
            const map = {
                'pending': 'جاهز',
                'processing': 'قيد المعالجة',
                'paused': 'موقوف مؤقتًا',
                'completed': 'مكتمل',
                'failed': 'فشل',
                'cancelled': 'تم الإلغاء',
            };
            return map[status] || status;
        }

        // Control buttons
        document.getElementById('pauseBtn').addEventListener('click', async () => {
            if (!currentJobId) return;
            await controlJob('pause');
        });

        document.getElementById('resumeBtn').addEventListener('click', async () => {
            if (!currentJobId) return;
            await controlJob('resume');
        });

        document.getElementById('cancelBtn').addEventListener('click', async () => {
            if (!currentJobId) return;
            await controlJob('cancel');
        });

        async function controlJob(action) {
            try {
                const response = await fetch(`/v1/api/enrichment/jobs/${currentJobId}/${action}`, {
                    method: 'POST',
                    headers: {'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json'},
                });

                if (!response.ok) {
                    showError(`خطأ في ${action}`);
                    return;
                }

                if (action === 'cancel') {
                    stopPolling();
                    showSuccess('تم إلغاء المهمة');
                } else {
                    startPolling();
                }
            } catch (error) {
                showError('خطأ في الاتصال');
            }
        }

        function stopPolling() {
            if (pollInterval) {
                clearInterval(pollInterval);
                pollInterval = null;
            }
        }

        function showError(msg) {
            errorMsg.textContent = msg;
            errorMsg.classList.toggle('visible', !!msg);
            if (msg) successMsg.classList.remove('visible');
        }

        function showSuccess(msg) {
            successMsg.textContent = msg;
            successMsg.classList.toggle('visible', !!msg);
            if (msg) errorMsg.classList.remove('visible');
        }

        async function parseResponse(response) {
            const text = await response.text();
            try { return JSON.parse(text); } catch (_) { return {message: text || `HTTP ${response.status}`}; }
        }

        const savedJobId = new URLSearchParams(location.search).get('job') || localStorage.getItem('enrichmentJobId');
        if (savedJobId) {
            currentJobId = savedJobId;
            progressSection.classList.add('visible');
            pauseResumeCancel.style.display = 'flex';
            startPolling();
        }
        window.addEventListener('beforeunload', () => currentJobId && localStorage.setItem('enrichmentJobId', currentJobId));
    </script>
</body>
</html>
