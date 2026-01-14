<!-- ============================================ -->
<!-- CONSOLE DE DEBUG AVANÇADO (SUPERADMIN ONLY) -->
<!-- Adicionar antes do </body> no index.php -->
<!-- ============================================ -->

<?php if ($isSuperAdmin): ?>
<style>
/* ========== CONSOLE DE DEBUG ========== */
.debug-console-container {
    position: fixed;
    bottom: 0;
    right: 0;
    width: 450px;
    max-height: 600px;
    background: #0d1117;
    border: 1px solid #30363d;
    border-radius: 12px 0 0 0;
    box-shadow: 0 -5px 30px rgba(0,0,0,0.5);
    z-index: 99999;
    transition: all 0.3s ease;
    font-family: 'Courier New', monospace;
    display: flex;
    flex-direction: column;
}

.debug-console-container.minimized {
    max-height: 45px;
    overflow: hidden;
}

.debug-console-container.hidden {
    transform: translateY(100%);
}

.debug-console-header {
    background: linear-gradient(135deg, #a371f7 0%, #8b5cf6 100%);
    padding: 12px 15px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
    user-select: none;
}

.debug-console-title {
    color: #fff;
    font-weight: 900;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.debug-console-badge {
    background: rgba(255,255,255,0.2);
    color: #fff;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 700;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.7; transform: scale(1.05); }
}

.debug-console-actions {
    display: flex;
    gap: 8px;
}

.debug-btn {
    background: rgba(255,255,255,0.1);
    border: none;
    color: #fff;
    width: 28px;
    height: 28px;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.debug-btn:hover {
    background: rgba(255,255,255,0.2);
    transform: scale(1.1);
}

.debug-console-filters {
    padding: 10px 15px;
    background: #161b22;
    border-bottom: 1px solid #30363d;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.filter-btn {
    background: rgba(163, 113, 247, 0.1);
    border: 1px solid rgba(163, 113, 247, 0.3);
    color: #a371f7;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.75rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.filter-btn:hover {
    background: rgba(163, 113, 247, 0.2);
}

.filter-btn.active {
    background: #a371f7;
    color: #000;
    font-weight: 700;
}

.debug-console-body {
    flex: 1;
    overflow-y: auto;
    padding: 10px;
    font-size: 0.8rem;
    max-height: 500px;
}

.debug-console-body::-webkit-scrollbar {
    width: 8px;
}

.debug-console-body::-webkit-scrollbar-track {
    background: #161b22;
}

.debug-console-body::-webkit-scrollbar-thumb {
    background: #30363d;
    border-radius: 4px;
}

.debug-log-entry {
    padding: 8px 10px;
    margin-bottom: 6px;
    background: #161b22;
    border-left: 3px solid;
    border-radius: 6px;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from { opacity: 0; transform: translateX(-10px); }
    to { opacity: 1; transform: translateX(0); }
}

.debug-log-entry.type-click { border-color: #3fb950; }
.debug-log-entry.type-navigation { border-color: #388bfd; }
.debug-log-entry.type-ajax { border-color: #a371f7; }
.debug-log-entry.type-error { border-color: #f85149; }
.debug-log-entry.type-database { border-color: #d29922; }
.debug-log-entry.type-security { border-color: #ff4d4d; }

.debug-log-time {
    color: #8b949e;
    font-size: 0.7rem;
}

.debug-log-type {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 700;
    margin-left: 8px;
}

.debug-log-type.click { background: rgba(63, 185, 80, 0.2); color: #3fb950; }
.debug-log-type.navigation { background: rgba(56, 139, 253, 0.2); color: #388bfd; }
.debug-log-type.ajax { background: rgba(163, 113, 247, 0.2); color: #a371f7; }
.debug-log-type.error { background: rgba(248, 81, 73, 0.2); color: #f85149; }
.debug-log-type.database { background: rgba(210, 153, 34, 0.2); color: #d29922; }
.debug-log-type.security { background: rgba(255, 77, 77, 0.2); color: #ff4d4d; }

.debug-log-message {
    color: #c9d1d9;
    margin-top: 4px;
    line-height: 1.4;
}

.debug-log-details {
    color: #6e7681;
    font-size: 0.7rem;
    margin-top: 4px;
    padding-left: 10px;
    border-left: 2px solid #30363d;
}

.debug-stats {
    padding: 10px 15px;
    background: #161b22;
    border-top: 1px solid #30363d;
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
    color: #8b949e;
}

.debug-stat-item {
    display: flex;
    align-items: center;
    gap: 5px;
}

.debug-stat-value {
    color: #a371f7;
    font-weight: 700;
}
</style>

<div class="debug-console-container" id="debugConsole">
    <!-- HEADER -->
    <div class="debug-console-header" onclick="toggleDebugConsole()">
        <div class="debug-console-title">
            <i class="fa-solid fa-terminal"></i>
            <span>SUPERADMIN DEBUG</span>
            <span class="debug-console-badge" id="logCount">0</span>
        </div>
        <div class="debug-console-actions">
            <button class="debug-btn" onclick="event.stopPropagation(); toggleDebugAutoScroll()" title="Auto-scroll" id="autoScrollBtn">
                <i class="fa-solid fa-arrow-down"></i>
            </button>
            <button class="debug-btn" onclick="event.stopPropagation(); clearDebugLogs()" title="Limpar logs">
                <i class="fa-solid fa-trash"></i>
            </button>
            <button class="debug-btn" onclick="event.stopPropagation(); exportDebugLogs()" title="Exportar logs">
                <i class="fa-solid fa-download"></i>
            </button>
            <button class="debug-btn" onclick="event.stopPropagation(); hideDebugConsole()" title="Ocultar">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="debug-console-filters">
        <button class="filter-btn active" data-filter="all" onclick="filterDebugLogs('all')">Todos</button>
        <button class="filter-btn" data-filter="click" onclick="filterDebugLogs('click')">Clicks</button>
        <button class="filter-btn" data-filter="navigation" onclick="filterDebugLogs('navigation')">Navegação</button>
        <button class="filter-btn" data-filter="ajax" onclick="filterDebugLogs('ajax')">AJAX</button>
        <button class="filter-btn" data-filter="database" onclick="filterDebugLogs('database')">Database</button>
        <button class="filter-btn" data-filter="error" onclick="filterDebugLogs('error')">Erros</button>
        <button class="filter-btn" data-filter="security" onclick="filterDebugLogs('security')">Security</button>
    </div>

    <!-- BODY (LOGS) -->
    <div class="debug-console-body" id="debugLogBody">
        <div class="debug-log-entry type-security">
            <div class="debug-log-time">⏰ <?= date('H:i:s') ?></div>
            <span class="debug-log-type security">SECURITY</span>
            <div class="debug-log-message">🔐 Console de Debug iniciado</div>
            <div class="debug-log-details">
                👑 SuperAdmin: <?= htmlspecialchars($admin_data['nome']) ?> (#<?= $adminId ?>)<br>
                🌐 IP: <?= $_SERVER['REMOTE_ADDR'] ?><br>
                🖥️ User-Agent: <?= substr($_SERVER['HTTP_USER_AGENT'], 0, 50) ?>...
            </div>
        </div>
    </div>

    <!-- FOOTER (STATS) -->
    <div class="debug-stats">
        <div class="debug-stat-item">
            <i class="fa-solid fa-clock"></i>
            <span id="debugUptime">00:00</span>
        </div>
        <div class="debug-stat-item">
            <i class="fa-solid fa-mouse"></i>
            Clicks: <span class="debug-stat-value" id="clickCount">0</span>
        </div>
        <div class="debug-stat-item">
            <i class="fa-solid fa-arrows-rotate"></i>
            AJAX: <span class="debug-stat-value" id="ajaxCount">0</span>
        </div>
        <div class="debug-stat-item">
            <i class="fa-solid fa-triangle-exclamation"></i>
            Erros: <span class="debug-stat-value" id="errorCount">0</span>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';
    
    // ========== VARIÁVEIS GLOBAIS ==========
    let debugLogs = [];
    let debugStartTime = Date.now();
    let debugAutoScroll = true;
    let debugCurrentFilter = 'all';
    let debugStats = {
        clicks: 0,
        ajax: 0,
        errors: 0,
        navigation: 0,
        database: 0,
        security: 0
    };

    // ========== FUNÇÕES DE LOG ==========
    window.debugLog = function(type, message, details = null) {
        const timestamp = new Date().toLocaleTimeString('pt-BR');
        const entry = {
            id: Date.now() + Math.random(),
            timestamp: timestamp,
            type: type,
            message: message,
            details: details
        };
        
        debugLogs.push(entry);
        debugStats[type] = (debugStats[type] || 0) + 1;
        
        // Atualiza UI
        updateDebugUI(entry);
        updateDebugStats();
    };

    function updateDebugUI(entry) {
        if (debugCurrentFilter !== 'all' && debugCurrentFilter !== entry.type) {
            return; // Não adiciona se não passar no filtro
        }
        
        const logBody = document.getElementById('debugLogBody');
        const logHTML = `
            <div class="debug-log-entry type-${entry.type}" data-type="${entry.type}">
                <div class="debug-log-time">⏰ ${entry.timestamp}</div>
                <span class="debug-log-type ${entry.type}">${entry.type.toUpperCase()}</span>
                <div class="debug-log-message">${escapeHtml(entry.message)}</div>
                ${entry.details ? `<div class="debug-log-details">${escapeHtml(entry.details)}</div>` : ''}
            </div>
        `;
        
        logBody.insertAdjacentHTML('beforeend', logHTML);
        
        // Auto-scroll
        if (debugAutoScroll) {
            logBody.scrollTop = logBody.scrollHeight;
        }
        
        // Limita a 500 logs visíveis
        const entries = logBody.querySelectorAll('.debug-log-entry');
        if (entries.length > 500) {
            entries[0].remove();
        }
    };

    function updateDebugStats() {
        document.getElementById('logCount').textContent = debugLogs.length;
        document.getElementById('clickCount').textContent = debugStats.clicks || 0;
        document.getElementById('ajaxCount').textContent = debugStats.ajax || 0;
        document.getElementById('errorCount').textContent = debugStats.errors || 0;
    };

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    };

    // ========== INTERCEPTADORES ==========
    
    // 1. INTERCEPTAR CLICKS
    document.addEventListener('click', function(e) {
        const target = e.target;
        const tagName = target.tagName;
        const className = target.className;
        const id = target.id;
        
        let elementInfo = `<${tagName}>`;
        if (id) elementInfo += ` #${id}`;
        if (className) elementInfo += ` .${className.split(' ').join('.')}`;
        
        const details = `
            Elemento: ${elementInfo}
            Texto: ${target.textContent.substring(0, 50)}${target.textContent.length > 50 ? '...' : ''}
            Path: ${getElementPath(target)}
        `;
        
        debugLog('click', `🖱️ Click em ${elementInfo}`, details);
    }, true);

    // 2. INTERCEPTAR LOADCONTENT (NAVEGAÇÃO)
    const originalLoadContent = window.loadContent;
    window.loadContent = function(pageName, element) {
        debugLog('navigation', `📄 Navegando para: ${pageName}`, `Elemento: ${element ? element.textContent : 'N/A'}`);
        return originalLoadContent(pageName, element);
    };

    // 3. INTERCEPTAR FETCH (AJAX)
    const originalFetch = window.fetch;
    window.fetch = function(...args) {
        const url = args[0];
        const options = args[1] || {};
        const method = options.method || 'GET';
        
        debugLog('ajax', `🔄 ${method} ${url}`, `Options: ${JSON.stringify(options, null, 2)}`);
        
        return originalFetch(...args)
            .then(response => {
                if (!response.ok) {
                    debugLog('error', `❌ Fetch Error: ${response.status} ${response.statusText}`, `URL: ${url}`);
                }
                return response;
            })
            .catch(error => {
                debugLog('error', `❌ Fetch Failed: ${error.message}`, `URL: ${url}\nStack: ${error.stack}`);
                throw error;
            });
    };

    // 4. INTERCEPTAR CONSOLE.ERROR
    const originalError = console.error;
    console.error = function(...args) {
        debugLog('error', `🐛 Console Error: ${args.join(' ')}`, `Stack: ${new Error().stack}`);
        originalError.apply(console, args);
    };

    // 5. INTERCEPTAR WINDOW.ONERROR
    window.addEventListener('error', function(e) {
        debugLog('error', `💥 Global Error: ${e.message}`, `
            File: ${e.filename}
            Line: ${e.lineno}:${e.colno}
            Stack: ${e.error ? e.error.stack : 'N/A'}
        `);
    });

    // 6. MONITOR DE MUDANÇAS DE URL
    let lastUrl = location.href;
    new MutationObserver(() => {
        const url = location.href;
        if (url !== lastUrl) {
            debugLog('navigation', `🔗 URL Changed: ${url}`, `From: ${lastUrl}`);
            lastUrl = url;
        }
    }).observe(document, { subtree: true, childList: true });

    // ========== FUNÇÕES DE CONTROLE ==========
    
    window.toggleDebugConsole = function() {
        const console = document.getElementById('debugConsole');
        console.classList.toggle('minimized');
    };

    window.hideDebugConsole = function() {
        const console = document.getElementById('debugConsole');
        console.classList.add('hidden');
        
        // Botão para reabrir
        if (!document.getElementById('reopenDebug')) {
            const btn = document.createElement('button');
            btn.id = 'reopenDebug';
            btn.innerHTML = '<i class="fa-solid fa-terminal"></i>';
            btn.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                width: 50px;
                height: 50px;
                background: #a371f7;
                border: none;
                border-radius: 50%;
                color: #fff;
                font-size: 1.2rem;
                cursor: pointer;
                box-shadow: 0 5px 20px rgba(163, 113, 247, 0.5);
                z-index: 99998;
            `;
            btn.onclick = function() {
                console.classList.remove('hidden');
                this.remove();
            };
            document.body.appendChild(btn);
        }
    };

    window.toggleDebugAutoScroll = function() {
        debugAutoScroll = !debugAutoScroll;
        const btn = document.getElementById('autoScrollBtn');
        btn.style.background = debugAutoScroll ? 'rgba(63, 185, 80, 0.3)' : 'rgba(255,255,255,0.1)';
    };

    window.clearDebugLogs = function() {
        if (!confirm('🗑️ Limpar todos os logs?')) return;
        
        debugLogs = [];
        debugStats = {
            clicks: 0,
            ajax: 0,
            errors: 0,
            navigation: 0,
            database: 0,
            security: 0
        };
        
        document.getElementById('debugLogBody').innerHTML = '';
        updateDebugStats();
        
        debugLog('security', '🧹 Logs limpos pelo SuperAdmin');
    };

    window.exportDebugLogs = function() {
        const dataStr = JSON.stringify(debugLogs, null, 2);
        const dataUri = 'data:application/json;charset=utf-8,'+ encodeURIComponent(dataStr);
        
        const exportFileDefaultName = `debug_log_${Date.now()}.json`;
        
        const linkElement = document.createElement('a');
        linkElement.setAttribute('href', dataUri);
        linkElement.setAttribute('download', exportFileDefaultName);
        linkElement.click();
        
        debugLog('security', `📥 Logs exportados: ${exportFileDefaultName}`);
    };

    window.filterDebugLogs = function(filter) {
        debugCurrentFilter = filter;
        
        // Atualiza botões
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        event.target.classList.add('active');
        
        // Filtra logs visíveis
        const entries = document.querySelectorAll('.debug-log-entry');
        entries.forEach(entry => {
            if (filter === 'all' || entry.dataset.type === filter) {
                entry.style.display = 'block';
            } else {
                entry.style.display = 'none';
            }
        });
        
        debugLog('navigation', `🔍 Filtro alterado: ${filter}`);
    };

    function getElementPath(element) {
        if (element.id) return `#${element.id}`;
        if (element === document.body) return 'body';
        
        let path = [];
        while (element.parentNode) {
            let tag = element.tagName.toLowerCase();
            if (element.className) tag += `.${element.className.split(' ')[0]}`;
            path.unshift(tag);
            element = element.parentNode;
            if (path.length > 5) break; // Limita profundidade
        }
        return path.join(' > ');
    }

    // ========== UPTIME COUNTER ==========
    setInterval(() => {
        const elapsed = Math.floor((Date.now() - debugStartTime) / 1000);
        const minutes = Math.floor(elapsed / 60);
        const seconds = elapsed % 60;
        document.getElementById('debugUptime').textContent = 
            `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    }, 1000);

    // ========== LOGS INICIAIS ==========
    debugLog('security', '✅ Debug Console ativo', 'Todos os eventos estão sendo monitorados');
    debugLog('navigation', `📍 Página inicial: ${window.location.pathname}`);
    
    console.log('🔍 DEBUG CONSOLE ATIVADO (SUPERADMIN ONLY)');
})();
</script>

<?php endif; ?>