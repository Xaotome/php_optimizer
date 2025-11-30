class PhpOptimizerApp {
    constructor() {
        this.files = [];
        this.currentResults = null;
        this.activeFilters = {
            error: true,
            warning: true,
            info: true,
            migration: true
        };

        this.basePath = this.detectBasePath();
        this.initializeEventListeners();
        this.setupFilters();
    }

    detectBasePath() {
        const path = window.location.pathname;
        if (path.includes('/php_optimizer/')) {
            return '/php_optimizer';
        }
        return '';
    }

    buildApiUrl(endpoint) {
        return this.basePath + endpoint;
    }

    initializeEventListeners() {
        const uploadZone = document.getElementById('uploadZone');
        const fileInput = document.getElementById('fileInput');
        const analyzeBtn = document.getElementById('analyzeBtn');

        uploadZone.addEventListener('click', () => fileInput.click());
        uploadZone.addEventListener('dragover', this.handleDragOver.bind(this));
        uploadZone.addEventListener('drop', this.handleDrop.bind(this));
        fileInput.addEventListener('change', this.handleFileSelect.bind(this));
        analyzeBtn.addEventListener('click', this.analyzeFiles.bind(this));

        // Admin cleanup buttons (si présents)
        const cleanUploadsBtn = document.getElementById('cleanUploadsBtn');
        const cleanReportsBtn = document.getElementById('cleanReportsBtn');
        const cancelCleanup = document.getElementById('cancelCleanup');
        const confirmCleanup = document.getElementById('confirmCleanup');

        if (cleanUploadsBtn) {
            cleanUploadsBtn.addEventListener('click', () => this.showCleanupModal('uploads'));
        }
        if (cleanReportsBtn) {
            cleanReportsBtn.addEventListener('click', () => this.showCleanupModal('reports'));
        }
        if (cancelCleanup) {
            cancelCleanup.addEventListener('click', this.hideCleanupModal.bind(this));
        }
        if (confirmCleanup) {
            confirmCleanup.addEventListener('click', this.performCleanup.bind(this));
        }

        document.addEventListener('keydown', (e) => {
            const modal = document.getElementById('cleanupModal');
            if (modal && e.key === 'Escape' && !modal.classList.contains('hidden')) {
                this.hideCleanupModal();
            }
        });
    }

    handleDragOver(e) {
        e.preventDefault();
        const zone = e.currentTarget;
        zone.style.borderColor = 'rgba(139, 92, 246, 0.8)';
        zone.style.background = 'rgba(139, 92, 246, 0.15)';
    }

    handleDrop(e) {
        e.preventDefault();
        const zone = e.currentTarget;
        zone.style.borderColor = 'rgba(139, 92, 246, 0.3)';
        zone.style.background = 'rgba(26, 31, 53, 0.3)';

        const files = Array.from(e.dataTransfer.files).filter(file =>
            file.name.endsWith('.php')
        );

        this.addFiles(files);
    }

    handleFileSelect(e) {
        const files = Array.from(e.target.files);
        this.addFiles(files);
    }

    addFiles(newFiles) {
        this.files = [...this.files, ...newFiles];
        this.updateFileList();
        this.updateAnalyzeButton();
    }

    updateFileList() {
        const fileList = document.getElementById('fileList');
        const filesContainer = document.getElementById('files');

        if (this.files.length === 0) {
            fileList.classList.add('hidden');
            return;
        }

        fileList.classList.remove('hidden');
        filesContainer.innerHTML = '';

        this.files.forEach((file, index) => {
            const fileItem = document.createElement('div');
            fileItem.className = 'file-item flex items-center justify-between p-3 rounded';
            fileItem.innerHTML = `
                <div class="flex items-center">
                    <i class="fab fa-php text-purple-400 text-xl mr-3"></i>
                    <span class="font-medium text-gray-200 mono">${file.name}</span>
                    <span class="text-sm text-gray-500 ml-2 mono">(${this.formatFileSize(file.size)})</span>
                </div>
                <button onclick="app.removeFile(${index})" class="text-red-400 hover:text-red-300 transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            `;
            filesContainer.appendChild(fileItem);
        });
    }

    removeFile(index) {
        this.files.splice(index, 1);
        this.updateFileList();
        this.updateAnalyzeButton();
    }

    updateAnalyzeButton() {
        const analyzeBtn = document.getElementById('analyzeBtn');
        analyzeBtn.disabled = this.files.length === 0;
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    async analyzeFiles() {
        const loading = document.getElementById('loading');
        const results = document.getElementById('results');

        loading.classList.remove('hidden');

        try {
            const formData = new FormData();
            this.files.forEach(file => {
                formData.append('files[]', file);
            });

            const response = await fetch(this.buildApiUrl('public/analyze'), {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const result = await response.json();

            if (result.success) {
                this.currentResults = result.data;
                this.displayResults(result.data);
                results.classList.remove('hidden');

                setTimeout(() => {
                    if (window.applyFilters) {
                        window.applyFilters();
                    }
                }, 100);
            } else {
                this.showError(result.message || 'Erreur inconnue');
            }
        } catch (error) {
            const debugBtn = document.getElementById('debugBtn');
            if (debugBtn) debugBtn.style.display = 'block';

            if (error.name === 'TypeError' && error.message.includes('fetch')) {
                this.showError('Unable to connect to server. Check your internet connection and try again.');
            } else if (error.message.includes('404')) {
                this.showError('Analysis service temporarily unavailable. URL used: ' + this.buildApiUrl('/analyze'));
            } else {
                this.showError('Analysis error: ' + error.message);
            }
        } finally {
            loading.classList.add('hidden');
        }
    }

    displayResults(data) {
        const resultsContainer = document.getElementById('analysisResults');
        const totalStats = this.calculateTotalStats(data.files);

        let html = '';

        // GDPR Message
        if (data.privacy && data.privacy.files_deleted) {
            html += `
                <div class="bg-emerald-900/20 border-2 border-emerald-500/50 rounded-lg p-6 mb-6">
                    <div class="flex items-start space-x-4">
                        <i class="fas fa-shield-alt text-emerald-400 text-3xl mt-1"></i>
                        <div>
                            <h3 class="text-xl font-bold text-emerald-300 mb-2 mono">
                                <i class="fas fa-check-circle"></i> Protection confirmed
                            </h3>
                            <p class="text-emerald-400 mb-2">
                                ${data.privacy.message}
                            </p>
                            <p class="text-sm text-emerald-400/70 mono">
                                <i class="fas fa-trash"></i> <strong>${data.privacy.files_count}</strong> file(s) automatically deleted
                            </p>
                        </div>
                    </div>
                </div>
            `;
        }

        // Stats cards
        html += `
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-8">
                <div class="glass-card border-emerald-500/30 rounded-lg p-4 text-center">
                    <i class="fas fa-check-circle text-3xl text-emerald-400 mb-2"></i>
                    <h3 class="text-sm font-semibold text-emerald-300 mono">Compliant</h3>
                    <p class="text-2xl font-bold text-emerald-400 mono">${data.summary.compliant}</p>
                </div>
                <div class="glass-card border-red-500/30 rounded-lg p-4 text-center">
                    <i class="fas fa-times-circle text-3xl text-red-400 mb-2"></i>
                    <h3 class="text-sm font-semibold text-red-300 mono">Errors</h3>
                    <p class="text-2xl font-bold text-red-400 mono" id="errorCount">${totalStats.errors}</p>
                </div>
                <div class="glass-card border-yellow-500/30 rounded-lg p-4 text-center">
                    <i class="fas fa-exclamation-triangle text-3xl text-yellow-400 mb-2"></i>
                    <h3 class="text-sm font-semibold text-yellow-300 mono">Warnings</h3>
                    <p class="text-2xl font-bold text-yellow-400 mono" id="warningCount">${totalStats.warnings}</p>
                </div>
                <div class="glass-card border-cyan-500/30 rounded-lg p-4 text-center">
                    <i class="fas fa-info-circle text-3xl text-cyan-400 mb-2"></i>
                    <h3 class="text-sm font-semibold text-cyan-300 mono">Info</h3>
                    <p class="text-2xl font-bold text-cyan-400 mono" id="infoCount">${totalStats.info}</p>
                </div>
                <div class="glass-card border-purple-500/30 rounded-lg p-4 text-center">
                    <i class="fas fa-rocket text-3xl text-purple-400 mb-2"></i>
                    <h3 class="text-sm font-semibold text-purple-300 mono">PHP 8.4</h3>
                    <p class="text-2xl font-bold text-purple-400 mono" id="migrationCount">${totalStats.migration}</p>
                </div>
            </div>
        `;

        // Filtres
        html += `
            <div class="glass-card rounded-lg p-4 mb-6 border-purple-500/30">
                <h3 class="text-lg font-semibold mb-4 text-white mono">
                    <i class="fas fa-filter text-cyan-400"></i> Filter by severity
                </h3>
                <div class="flex flex-wrap gap-3">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" id="filterError" class="severity-filter mr-2 accent-red-500" data-severity="error" checked>
                        <span class="px-3 py-1 bg-red-900/30 text-red-300 border border-red-500/30 rounded-full text-sm font-medium mono">
                            <i class="fas fa-times-circle mr-1"></i> Errors (<span id="filterErrorCount">${totalStats.errors}</span>)
                        </span>
                    </label>
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" id="filterWarning" class="severity-filter mr-2 accent-yellow-500" data-severity="warning" checked>
                        <span class="px-3 py-1 bg-yellow-900/30 text-yellow-300 border border-yellow-500/30 rounded-full text-sm font-medium mono">
                            <i class="fas fa-exclamation-triangle mr-1"></i> Warnings (<span id="filterWarningCount">${totalStats.warnings}</span>)
                        </span>
                    </label>
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" id="filterInfo" class="severity-filter mr-2 accent-cyan-500" data-severity="info" checked>
                        <span class="px-3 py-1 bg-cyan-900/30 text-cyan-300 border border-cyan-500/30 rounded-full text-sm font-medium mono">
                            <i class="fas fa-info-circle mr-1"></i> Info (<span id="filterInfoCount">${totalStats.info}</span>)
                        </span>
                    </label>
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" id="filterMigration" class="severity-filter mr-2 accent-purple-500" data-severity="migration" checked>
                        <span class="px-3 py-1 bg-purple-900/30 text-purple-300 border border-purple-500/30 rounded-full text-sm font-medium mono">
                            <i class="fas fa-rocket mr-1"></i> PHP 8.4 (<span id="filterMigrationCount">${totalStats.migration}</span>)
                        </span>
                    </label>
                    <button id="toggleAllFilters" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg text-sm mono transition-colors">
                        <i class="fas fa-eye"></i> Toggle All
                    </button>
                </div>
            </div>
        `;

        html += '<div id="fileReports" class="space-y-6">';

        data.files.forEach((file, index) => {
            html += this.generateFileReport(file, index);
        });

        html += '</div>';

        resultsContainer.innerHTML = html;
    }

    generateFileReport(file, fileIndex) {
        const statusIcon = file.status === 'success' ?
            '<i class="fas fa-check-circle text-emerald-400"></i>' :
            file.status === 'warning' ?
            '<i class="fas fa-exclamation-triangle text-yellow-400"></i>' :
            '<i class="fas fa-times-circle text-red-400"></i>';

        const escapeHtml = this.escapeHtml.bind(this);

        let html = `
            <div class="glass-card rounded-lg overflow-hidden border-purple-500/30" data-file-index="${fileIndex}">
                <div class="bg-gradient-to-r from-purple-900/30 to-cyan-900/30 px-6 py-4 flex items-center justify-between border-b border-purple-500/30">
                    <div class="flex items-center">
                        ${statusIcon}
                        <h3 class="text-lg font-semibold ml-2 text-dark mono">${file.name}</h3>
                    </div>
                    <div class="flex space-x-2">
                        ${file.psr_compliance.map(psr =>
                            `<span class="px-2 py-1 text-xs rounded mono ${psr.compliant ? 'bg-emerald-900/30 text-emerald-300 border border-emerald-500/30' : 'bg-red-900/30 text-red-300 border border-red-500/30'}">${psr.standard}</span>`
                        ).join('')}
                    </div>
                </div>
                <div class="p-6">
        `;

        // Migration summary
        if (file.migration_summary && file.migration_summary.total_suggestions > 0) {
            const complexity = file.migration_summary.complexity;
            const complexityColor = complexity === 'high' ? 'red' : complexity === 'medium' ? 'yellow' : 'emerald';
            const complexityIcon = complexity === 'high' ? 'exclamation-triangle' : complexity === 'medium' ? 'clock' : 'check-circle';

            html += `
                <div class="bg-purple-900/20 border border-purple-500/30 rounded-lg p-4 mb-6">
                    <h4 class="text-md font-semibold text-purple-300 mb-3 mono">
                        <i class="fas fa-rocket mr-2"></i>PHP 8.4 Migration
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="text-center">
                            <p class="text-2xl font-bold text-purple-400 mono">${file.migration_summary.total_suggestions}</p>
                            <p class="text-sm text-purple-300 mono">Suggestions</p>
                        </div>
                        <div class="text-center">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium mono bg-${complexityColor}-900/30 text-${complexityColor}-300 border border-${complexityColor}-500/30">
                                <i class="fas fa-${complexityIcon} mr-1"></i>
                                ${complexity}
                            </span>
                        </div>
                        <div class="text-center">
                            ${Object.entries(file.migration_summary.by_category || {}).map(([cat, count]) =>
                                `<span class="inline-block px-2 py-1 text-xs bg-purple-900/30 text-purple-300 border border-purple-500/30 rounded mr-1 mono">${cat}: ${count}</span>`
                            ).join('')}
                        </div>
                    </div>
                </div>
            `;
        }

        if (file.issues.length > 0) {
            html += '<h4 class="text-md font-semibold mb-4 text-cyan-400 mono"><i class="fas fa-bug"></i> Detected issues:</h4>';
            html += '<div class="space-y-3">';

            file.issues.forEach((issue, issueIndex) => {
                const isMigration = issue.category === 'migration';
                const severityClass = isMigration ? 'border-purple-500/30 bg-purple-900/20' :
                                     issue.severity === 'error' ? 'border-red-500/30 bg-red-900/20' :
                                     issue.severity === 'warning' ? 'border-yellow-500/30 bg-yellow-900/20' :
                                     'border-cyan-500/30 bg-cyan-900/20';

                const severityBadge = isMigration ? 'bg-purple-900/40 text-purple-300 border border-purple-500/30' :
                                     issue.severity === 'error' ? 'bg-red-900/40 text-red-300 border border-red-500/30' :
                                     issue.severity === 'warning' ? 'bg-yellow-900/40 text-yellow-300 border border-yellow-500/30' :
                                     'bg-cyan-900/40 text-cyan-300 border border-cyan-500/30';

                const severityText = isMigration ? 'PHP 8.4' : issue.severity.toUpperCase();
                const severityIcon = isMigration ? 'fas fa-rocket' :
                                    issue.severity === 'error' ? 'fas fa-times-circle' :
                                    issue.severity === 'warning' ? 'fas fa-exclamation-triangle' :
                                    'fas fa-info-circle';

                const dataAttr = isMigration ? 'migration' : issue.severity;

                html += `
                    <div class="issue-item border ${severityClass} rounded-lg p-4" data-severity="${dataAttr}" data-file-index="${fileIndex}" data-issue-index="${issueIndex}">
                        <div class="flex items-start">
                            <span class="px-2 py-1 text-xs rounded font-semibold mr-3 ${severityBadge} mono">
                                <i class="${severityIcon} mr-1"></i>${severityText}
                            </span>
                            <div class="flex-1">
                                <p class="font-medium text-gray-200">${issue.message}</p>
                                <p class="text-sm text-gray-400 mono">Line ${issue.line} - ${issue.rule}</p>
                                ${issue.suggestion ? `<p class="text-sm text-emerald-400 mt-2 mono"><i class="fas fa-lightbulb"></i> ${issue.suggestion}</p>` : ''}
                                ${issue.php_version ? `<p class="text-xs text-purple-400 mt-1 mono"><i class="fas fa-code"></i> Compatible PHP ${issue.php_version}</p>` : ''}
                                ${issue.explanation ? `<p class="text-sm text-cyan-300 mt-2 bg-cyan-900/20 p-3 rounded-lg border-l-4 border-cyan-500/50 mono"><i class="fas fa-info-circle mr-1"></i> ${issue.explanation}</p>` : ''}
                                ${issue.before_code && issue.after_code ? `
                                    <div class="mt-4 rounded-lg overflow-hidden border border-purple-500/30">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-0">
                                            <div class="bg-red-900/20 border-r border-purple-500/30">
                                                <div class="bg-red-900/30 px-3 py-2 text-xs font-medium text-red-300 border-b border-red-500/30 mono">
                                                    <i class="fas fa-minus-circle mr-1"></i>Before (old code)
                                                </div>
                                                <pre class="text-xs p-3 overflow-x-auto text-gray-300 mono"><code>${escapeHtml(issue.before_code)}</code></pre>
                                            </div>
                                            <div class="bg-emerald-900/20">
                                                <div class="bg-emerald-900/30 px-3 py-2 text-xs font-medium text-emerald-300 border-b border-emerald-500/30 mono">
                                                    <i class="fas fa-plus-circle mr-1"></i>After (PHP 8.4)
                                                </div>
                                                <pre class="text-xs p-3 overflow-x-auto text-gray-300 mono"><code>${escapeHtml(issue.after_code)}</code></pre>
                                            </div>
                                        </div>
                                    </div>
                                ` : ''}
                                ${issue.diff ? `<pre class="text-xs bg-gray-900/50 p-3 mt-2 rounded-lg overflow-x-auto border border-purple-500/30 text-gray-300 mono"><code>${issue.diff}</code></pre>` : ''}
                            </div>
                        </div>
                    </div>
                `;
            });

            html += '</div>';
        } else {
            html += '<p class="text-emerald-400 mono"><i class="fas fa-check"></i> No issues detected - Code compliant!</p>';
        }

        html += '</div></div>';
        return html;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    showError(message) {
        const resultsContainer = document.getElementById('analysisResults');
        resultsContainer.innerHTML = `
            <div class="bg-red-900/20 border-2 border-red-500/50 rounded-lg p-6 text-center">
                <i class="fas fa-exclamation-circle text-4xl text-red-400 mb-4"></i>
                <h3 class="text-lg font-semibold text-red-300 mb-2 mono">Error</h3>
                <p class="text-red-400">${message}</p>
            </div>
        `;

        document.getElementById('results').classList.remove('hidden');
    }

    showDebugInfo() {
        const debugInfo = `
            <div class="glass-card border-purple-500/30 rounded-lg p-4 mb-4">
                <h4 class="font-semibold mb-2 text-cyan-400 mono">Debug Info:</h4>
                <ul class="text-sm space-y-1 text-gray-300 mono">
                    <li><strong>URL:</strong> ${window.location.href}</li>
                    <li><strong>Base path:</strong> ${this.basePath || '(root)'}</li>
                    <li><strong>API URL:</strong> ${this.buildApiUrl('/analyze')}</li>
                    <li><strong>User Agent:</strong> ${navigator.userAgent.substring(0, 50)}...</li>
                </ul>
            </div>
        `;

        const resultsContainer = document.getElementById('analysisResults');
        resultsContainer.innerHTML = debugInfo + resultsContainer.innerHTML;
    }

    calculateTotalStats(files) {
        const stats = { errors: 0, warnings: 0, info: 0, migration: 0 };

        files.forEach(file => {
            file.issues.forEach(issue => {
                if (issue.category === 'migration') {
                    stats.migration++;
                } else if (issue.severity === 'error') {
                    stats.errors++;
                } else if (issue.severity === 'warning') {
                    stats.warnings++;
                } else if (issue.severity === 'info') {
                    stats.info++;
                }
            });
        });

        return stats;
    }

    setupFilters() {
        window.applyFilters = () => {
            const errorChecked = document.getElementById('filterError')?.checked;
            const warningChecked = document.getElementById('filterWarning')?.checked;
            const infoChecked = document.getElementById('filterInfo')?.checked;
            const migrationChecked = document.getElementById('filterMigration')?.checked;

            const items = document.querySelectorAll('.issue-item');

            items.forEach((item) => {
                const severity = item.dataset.severity;
                let shouldShow = false;

                if (severity === 'error' && errorChecked) shouldShow = true;
                if (severity === 'warning' && warningChecked) shouldShow = true;
                if (severity === 'info' && infoChecked) shouldShow = true;
                if (severity === 'migration' && migrationChecked) shouldShow = true;

                if (shouldShow) {
                    item.style.display = 'block';
                    item.style.visibility = 'visible';
                    item.style.opacity = '1';
                } else {
                    item.style.display = 'none';
                    item.style.visibility = 'hidden';
                    item.style.opacity = '0';
                }
            });
        };

        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('severity-filter')) {
                window.applyFilters();
            }
        });

        document.addEventListener('click', (e) => {
            if (e.target.id === 'toggleAllFilters' || e.target.closest('#toggleAllFilters')) {
                const checkboxes = document.querySelectorAll('.severity-filter');
                const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                const newState = !allChecked;

                checkboxes.forEach(cb => {
                    cb.checked = newState;
                });

                window.applyFilters();
            }
        });
    }

    showCleanupModal(target) {
        this.currentCleanupTarget = target;
        const targetFolder = document.getElementById('targetFolder');
        const adminPassword = document.getElementById('adminPassword');
        const cleanupModal = document.getElementById('cleanupModal');

        if (targetFolder) targetFolder.textContent = `storage/${target}`;
        if (adminPassword) adminPassword.value = '';
        if (cleanupModal) cleanupModal.classList.remove('hidden');
    }

    hideCleanupModal() {
        const cleanupModal = document.getElementById('cleanupModal');
        if (cleanupModal) cleanupModal.classList.add('hidden');
        this.currentCleanupTarget = null;
    }

    async performCleanup() {
        const password = document.getElementById('adminPassword').value;

        if (!password) {
            alert('Please enter the administrator password');
            return;
        }

        try {
            const response = await fetch(this.buildApiUrl('/admin_cleanup.php'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    password: password,
                    target: this.currentCleanupTarget
                })
            });

            const data = await response.json();

            if (data.success) {
                alert(`Cleanup successful! ${data.data.deleted_files} file(s) deleted from ${data.data.target} folder.`);
                this.hideCleanupModal();
            } else {
                switch (data.error_code) {
                    case 'INVALID_PASSWORD':
                        alert('Incorrect password!');
                        break;
                    case 'INVALID_TARGET':
                        alert('Unauthorized target!');
                        break;
                    case 'DIRECTORY_NOT_FOUND':
                        alert('Directory not found!');
                        break;
                    default:
                        alert(`Error: ${data.message}`);
                }
            }
        } catch (error) {
            alert(`Connection error: ${error.message}`);
        }
    }
}

const app = new PhpOptimizerApp();
