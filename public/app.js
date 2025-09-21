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
        
        // Détecter le chemin de base automatiquement
        this.basePath = this.detectBasePath();
        
        this.initializeEventListeners();
        // Setup des filtres dès le début
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
        
        // Écouteurs pour les boutons de nettoyage
        document.getElementById('cleanUploadsBtn').addEventListener('click', () => this.showCleanupModal('uploads'));
        document.getElementById('cleanReportsBtn').addEventListener('click', () => this.showCleanupModal('reports'));
        document.getElementById('cancelCleanup').addEventListener('click', this.hideCleanupModal.bind(this));
        document.getElementById('confirmCleanup').addEventListener('click', this.performCleanup.bind(this));
        
        // Fermer le modal avec Échap
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !document.getElementById('cleanupModal').classList.contains('hidden')) {
                this.hideCleanupModal();
            }
        });
    }

    handleDragOver(e) {
        e.preventDefault();
        e.currentTarget.classList.add('border-indigo-400', 'bg-indigo-50');
    }

    handleDrop(e) {
        e.preventDefault();
        e.currentTarget.classList.remove('border-indigo-400', 'bg-indigo-50');
        
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
            fileItem.className = 'flex items-center justify-between bg-gray-50 p-3 rounded';
            fileItem.innerHTML = `
                <div class="flex items-center">
                    <i class="fab fa-php text-indigo-600 text-xl mr-3"></i>
                    <span class="font-medium">${file.name}</span>
                    <span class="text-sm text-gray-500 ml-2">(${this.formatFileSize(file.size)})</span>
                </div>
                <button onclick="app.removeFile(${index})" class="text-red-500 hover:text-red-700">
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
            
            const response = await fetch(this.buildApiUrl('/analyze'), {
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
                
                // Appliquer les filtres après affichage des résultats
                setTimeout(() => {
                    if (window.applyFilters) {
                        window.applyFilters();
                    }
                }, 100);
            } else {
                this.showError(result.message || 'Erreur inconnue');
            }
        } catch (error) {
            // Afficher le bouton de débogage en cas d'erreur
            document.getElementById('debugBtn').style.display = 'block';
            
            if (error.name === 'TypeError' && error.message.includes('fetch')) {
                this.showError('Impossible de se connecter au serveur. Vérifiez votre connexion internet et réessayez.');
            } else if (error.message.includes('404')) {
                this.showError('Service d\'analyse temporairement indisponible. URL utilisée: ' + this.buildApiUrl('/analyze'));
            } else {
                this.showError('Erreur lors de l\'analyse: ' + error.message);
            }
        } finally {
            loading.classList.add('hidden');
        }
    }

    displayResults(data) {
        const resultsContainer = document.getElementById('analysisResults');
        
        // Calculer les statistiques totales par sévérité
        const totalStats = this.calculateTotalStats(data.files);
        
        let html = `
            <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-center">
                    <i class="fas fa-check-circle text-3xl text-green-600 mb-2"></i>
                    <h3 class="text-lg font-semibold text-green-800">Fichiers conformes</h3>
                    <p class="text-2xl font-bold text-green-600">${data.summary.compliant}</p>
                </div>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-center">
                    <i class="fas fa-times-circle text-3xl text-red-600 mb-2"></i>
                    <h3 class="text-lg font-semibold text-red-800">Erreurs</h3>
                    <p class="text-2xl font-bold text-red-600" id="errorCount">${totalStats.errors}</p>
                </div>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-center">
                    <i class="fas fa-exclamation-triangle text-3xl text-yellow-600 mb-2"></i>
                    <h3 class="text-lg font-semibold text-yellow-800">Avertissements</h3>
                    <p class="text-2xl font-bold text-yellow-600" id="warningCount">${totalStats.warnings}</p>
                </div>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-center">
                    <i class="fas fa-info-circle text-3xl text-blue-600 mb-2"></i>
                    <h3 class="text-lg font-semibold text-blue-800">Informations</h3>
                    <p class="text-2xl font-bold text-blue-600" id="infoCount">${totalStats.info}</p>
                </div>
                <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 text-center">
                    <i class="fas fa-rocket text-3xl text-purple-600 mb-2"></i>
                    <h3 class="text-lg font-semibold text-purple-800">Migration PHP 8.4</h3>
                    <p class="text-2xl font-bold text-purple-600" id="migrationCount">${totalStats.migration}</p>
                </div>
            </div>
        `;

        // Ajouter les filtres
        html += `
            <div class="bg-gray-50 rounded-lg p-4 mb-6">
                <h3 class="text-lg font-semibold mb-4 text-gray-800">
                    <i class="fas fa-filter"></i> Filtrer par sévérité
                </h3>
                <div class="flex flex-wrap gap-3">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" id="filterError" class="severity-filter mr-2" data-severity="error" checked>
                        <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm font-medium">
                            <i class="fas fa-times-circle mr-1"></i> Erreurs (<span id="filterErrorCount">${totalStats.errors}</span>)
                        </span>
                    </label>
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" id="filterWarning" class="severity-filter mr-2" data-severity="warning" checked>
                        <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm font-medium">
                            <i class="fas fa-exclamation-triangle mr-1"></i> Avertissements (<span id="filterWarningCount">${totalStats.warnings}</span>)
                        </span>
                    </label>
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" id="filterInfo" class="severity-filter mr-2" data-severity="info" checked>
                        <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">
                            <i class="fas fa-info-circle mr-1"></i> Informations (<span id="filterInfoCount">${totalStats.info}</span>)
                        </span>
                    </label>
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" id="filterMigration" class="severity-filter mr-2" data-severity="migration" checked>
                        <span class="px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-sm font-medium">
                            <i class="fas fa-rocket mr-1"></i> Migration PHP 8.4 (<span id="filterMigrationCount">${totalStats.migration}</span>)
                        </span>
                    </label>
                    <button id="toggleAllFilters" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 text-sm">
                        <i class="fas fa-eye"></i> Tout masquer/afficher
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
            '<i class="fas fa-check-circle text-green-500"></i>' :
            file.status === 'warning' ? 
            '<i class="fas fa-exclamation-triangle text-yellow-500"></i>' :
            '<i class="fas fa-times-circle text-red-500"></i>';

        let html = `
            <div class="border rounded-lg overflow-hidden" data-file-index="${fileIndex}">
                <div class="bg-gray-50 px-6 py-4 flex items-center justify-between">
                    <div class="flex items-center">
                        ${statusIcon}
                        <h3 class="text-lg font-semibold ml-2">${file.name}</h3>
                    </div>
                    <div class="flex space-x-2">
                        ${file.psr_compliance.map(psr => 
                            `<span class="px-2 py-1 text-xs rounded ${psr.compliant ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">${psr.standard}</span>`
                        ).join('')}
                    </div>
                </div>
                <div class="p-6">
        `;

        // Afficher le résumé de migration s'il existe
        if (file.migration_summary && file.migration_summary.total_suggestions > 0) {
            const complexity = file.migration_summary.complexity;
            const complexityColor = complexity === 'high' ? 'red' : complexity === 'medium' ? 'yellow' : 'green';
            const complexityIcon = complexity === 'high' ? 'exclamation-triangle' : complexity === 'medium' ? 'clock' : 'check-circle';

            html += `
                <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-6">
                    <h4 class="text-md font-semibold text-purple-800 mb-3">
                        <i class="fas fa-rocket mr-2"></i>Résumé de migration PHP 8.4
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="text-center">
                            <p class="text-2xl font-bold text-purple-600">${file.migration_summary.total_suggestions}</p>
                            <p class="text-sm text-purple-700">Suggestions</p>
                        </div>
                        <div class="text-center">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-${complexityColor}-100 text-${complexityColor}-800">
                                <i class="fas fa-${complexityIcon} mr-1"></i>
                                Complexité ${complexity}
                            </span>
                        </div>
                        <div class="text-center">
                            ${Object.entries(file.migration_summary.by_category || {}).map(([cat, count]) =>
                                `<span class="inline-block px-2 py-1 text-xs bg-purple-100 text-purple-700 rounded mr-1">${cat}: ${count}</span>`
                            ).join('')}
                        </div>
                    </div>
                </div>
            `;
        }

        if (file.issues.length > 0) {
            html += '<h4 class="text-md font-semibold mb-4">Problèmes détectés:</h4>';
            html += '<div class="space-y-2">';
            
            file.issues.forEach((issue, issueIndex) => {
                const isMigration = issue.category === 'migration';
                const severityClass = isMigration ? 'border-purple-200 bg-purple-50' :
                                     issue.severity === 'error' ? 'border-red-200 bg-red-50' :
                                     issue.severity === 'warning' ? 'border-yellow-200 bg-yellow-50' :
                                     'border-blue-200 bg-blue-50';

                const severityBadge = isMigration ? 'bg-purple-200 text-purple-800' :
                                     issue.severity === 'error' ? 'bg-red-200 text-red-800' :
                                     issue.severity === 'warning' ? 'bg-yellow-200 text-yellow-800' :
                                     'bg-blue-200 text-blue-800';

                const severityText = isMigration ? 'PHP 8.4' : issue.severity.toUpperCase();
                const severityIcon = isMigration ? 'fas fa-rocket' :
                                    issue.severity === 'error' ? 'fas fa-times-circle' :
                                    issue.severity === 'warning' ? 'fas fa-exclamation-triangle' :
                                    'fas fa-info-circle';

                const dataAttr = isMigration ? 'migration' : issue.severity;

                html += `
                    <div class="issue-item border ${severityClass} rounded p-3" data-severity="${dataAttr}" data-file-index="${fileIndex}" data-issue-index="${issueIndex}">
                        <div class="flex items-start">
                            <span class="px-2 py-1 text-xs rounded font-semibold mr-3 ${severityBadge}">
                                <i class="${severityIcon} mr-1"></i>${severityText}
                            </span>
                            <div class="flex-1">
                                <p class="font-medium">${issue.message}</p>
                                <p class="text-sm text-gray-600">Ligne ${issue.line} - ${issue.rule}</p>
                                ${issue.suggestion ? `<p class="text-sm text-green-600 mt-1"><i class="fas fa-lightbulb"></i> ${issue.suggestion}</p>` : ''}
                                ${issue.php_version ? `<p class="text-xs text-purple-600 mt-1"><i class="fas fa-code"></i> Compatible PHP ${issue.php_version}</p>` : ''}
                                ${issue.diff ? `<pre class="text-xs bg-gray-100 p-2 mt-2 rounded overflow-x-auto"><code>${issue.diff}</code></pre>` : ''}
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
        } else {
            html += '<p class="text-green-600"><i class="fas fa-check"></i> Aucun problème détecté - Code conforme!</p>';
        }

        html += '</div></div>';
        return html;
    }

    showError(message) {
        const resultsContainer = document.getElementById('analysisResults');
        resultsContainer.innerHTML = `
            <div class="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
                <i class="fas fa-exclamation-circle text-4xl text-red-600 mb-4"></i>
                <h3 class="text-lg font-semibold text-red-800 mb-2">Erreur</h3>
                <p class="text-red-600">${message}</p>
            </div>
        `;
        
        document.getElementById('results').classList.remove('hidden');
    }

    showDebugInfo() {
        const debugInfo = `
            <div class="bg-gray-100 border border-gray-300 rounded-lg p-4 mb-4">
                <h4 class="font-semibold mb-2">Informations de débogage :</h4>
                <ul class="text-sm space-y-1">
                    <li><strong>URL actuelle :</strong> ${window.location.href}</li>
                    <li><strong>Chemin de base détecté :</strong> ${this.basePath || '(racine)'}</li>
                    <li><strong>URL d'API :</strong> ${this.buildApiUrl('/analyze')}</li>
                    <li><strong>User Agent :</strong> ${navigator.userAgent.substring(0, 50)}...</li>
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
        // Fonction globale pour appliquer les filtres
        window.applyFilters = () => {
            // Récupérer l'état des checkboxes
            const errorChecked = document.getElementById('filterError')?.checked;
            const warningChecked = document.getElementById('filterWarning')?.checked;
            const infoChecked = document.getElementById('filterInfo')?.checked;
            
            // Trouver tous les éléments et appliquer le filtrage
            const items = document.querySelectorAll('.issue-item');
            
            items.forEach((item) => {
                const severity = item.dataset.severity;
                let shouldShow = false;
                
                if (severity === 'error' && errorChecked) shouldShow = true;
                if (severity === 'warning' && warningChecked) shouldShow = true;
                if (severity === 'info' && infoChecked) shouldShow = true;
                
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
        
        // Délégation d'événements sur le document
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('severity-filter')) {
                window.applyFilters();
            }
        });
        
        // Délégation pour le bouton toggle
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
        document.getElementById('targetFolder').textContent = `storage/${target}`;
        document.getElementById('adminPassword').value = '';
        document.getElementById('cleanupModal').classList.remove('hidden');
    }

    hideCleanupModal() {
        document.getElementById('cleanupModal').classList.add('hidden');
        this.currentCleanupTarget = null;
    }

    async performCleanup() {
        const password = document.getElementById('adminPassword').value;
        
        if (!password) {
            alert('Veuillez entrer le mot de passe administrateur');
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
                alert(`Nettoyage réussi ! ${data.data.deleted_files} fichier(s) supprimé(s) du dossier ${data.data.target}.`);
                this.hideCleanupModal();
            } else {
                switch (data.error_code) {
                    case 'INVALID_PASSWORD':
                        alert('Mot de passe incorrect !');
                        break;
                    case 'INVALID_TARGET':
                        alert('Cible non autorisée !');
                        break;
                    case 'DIRECTORY_NOT_FOUND':
                        alert('Répertoire non trouvé !');
                        break;
                    default:
                        alert(`Erreur : ${data.message}`);
                }
            }
        } catch (error) {
            alert(`Erreur de connexion : ${error.message}`);
        }
    }

}

const app = new PhpOptimizerApp();