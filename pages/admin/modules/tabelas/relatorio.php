<?php
if (!defined('IS_ADMIN_PAGE')) {
    require_once '../../../../registration/includes/db.php';
    session_start();
}

$adminId = $_SESSION['auth']['user_id'] ?? 0;
$adminRole = $_SESSION['auth']['role'] ?? 'admin';

/* ================= TEMPLATES DE RELATÓRIOS ================= */
$templates = [
    'executive' => [
        'name' => 'Relatório Executivo',
        'description' => 'Visão geral com métricas principais, gráficos e análise de performance',
        'icon' => 'fa-chart-pie',
        'sections' => ['summary', 'revenue', 'growth', 'companies', 'products']
    ],
    'financial' => [
        'name' => 'Relatório Financeiro',
        'description' => 'Análise completa de receitas, transações, MRR e projeções',
        'icon' => 'fa-dollar-sign',
        'sections' => ['revenue', 'transactions', 'mrr', 'forecast']
    ],
    'growth' => [
        'name' => 'Relatório de Crescimento',
        'description' => 'Métricas de crescimento, retenção, churn e saúde das empresas',
        'icon' => 'fa-chart-line',
        'sections' => ['growth', 'retention', 'churn', 'health']
    ],
    'operational' => [
        'name' => 'Relatório Operacional',
        'description' => 'Status de empresas, documentos, suporte e atividades operacionais',
        'icon' => 'fa-cog',
        'sections' => ['companies', 'documents', 'support', 'activities']
    ],
    'custom' => [
        'name' => 'Relatório Personalizado',
        'description' => 'Crie um relatório customizado escolhendo seções específicas',
        'icon' => 'fa-sliders',
        'sections' => []
    ]
];

/* ================= MÉTRICAS PARA PREVIEW ================= */
$dateFrom = date('Y-m-01');
$dateTo = date('Y-m-d');

// Resumo geral
$summary = [
    'total_companies' => $mysqli->query("SELECT COUNT(*) as total FROM users WHERE type = 'company'")->fetch_assoc()['total'],
    'active_subscriptions' => $mysqli->query("SELECT COUNT(*) as total FROM user_subscriptions WHERE status = 'active'")->fetch_assoc()['total'],
    'total_revenue' => $mysqli->query("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE status = 'completed' AND MONTH(transaction_date) = MONTH(CURDATE())")->fetch_assoc()['total'],
    'mrr' => $mysqli->query("SELECT COALESCE(SUM(mrr), 0) as total FROM user_subscriptions WHERE status = 'active'")->fetch_assoc()['total']
];
?>

<style>
:root {
    --bg-page: #0d1117;
    --bg-card: #161b22;
    --bg-elevated: #21262d;
    --bg-hover: #30363d;
    --text-primary: #c9d1d9;
    --text-secondary: #8b949e;
    --text-muted: #6e7681;
    --accent: #238636;
    --border: #30363d;
    --success: #238636;
    --warning: #9e6a03;
    --error: #da3633;
    --info: #388bfd;
}

* {
    box-sizing: border-box;
}

.relatorio-container {
    padding: 24px;
    background: var(--bg-page);
    min-height: 100vh;
}

/* ========== HEADER ========== */
.page-header {
    margin-bottom: 32px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border);
}

.header-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.header-title {
    font-size: 2rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.header-subtitle {
    color: var(--text-secondary);
    font-size: 0.938rem;
}

.btn-back {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    background: var(--bg-card);
    color: var(--text-primary);
    border: 1px solid var(--border);
    border-radius: 6px;
    text-decoration: none;
    font-size: 0.875rem;
    transition: all 0.2s;
}

.btn-back:hover {
    background: var(--bg-elevated);
    border-color: var(--accent);
}

/* ========== WIZARD STEPS ========== */
.wizard-steps {
    display: flex;
    justify-content: center;
    gap: 24px;
    margin-bottom: 40px;
    position: relative;
}

.wizard-steps::before {
    content: '';
    position: absolute;
    top: 20px;
    left: 10%;
    right: 10%;
    height: 2px;
    background: var(--border);
    z-index: 0;
}

.wizard-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    position: relative;
    z-index: 1;
}

.step-number {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--bg-card);
    border: 2px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: var(--text-muted);
    transition: all 0.3s;
}

.wizard-step.active .step-number {
    background: var(--accent);
    border-color: var(--accent);
    color: #fff;
}

.wizard-step.completed .step-number {
    background: var(--success);
    border-color: var(--success);
    color: #fff;
}

.step-label {
    font-size: 0.813rem;
    color: var(--text-muted);
    font-weight: 600;
}

.wizard-step.active .step-label {
    color: var(--accent);
}

/* ========== STEP CONTENT ========== */
.step-content {
    display: none;
}

.step-content.active {
    display: block;
}

/* ========== TEMPLATES GRID ========== */
.templates-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
}

.template-card {
    background: var(--bg-card);
    border: 2px solid var(--border);
    border-radius: 12px;
    padding: 28px;
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
}

.template-card:hover {
    border-color: var(--accent);
    transform: translateY(-2px);
}

.template-card.selected {
    border-color: var(--accent);
    background: var(--bg-elevated);
}

.template-card.selected::before {
    content: '✓';
    position: absolute;
    top: 12px;
    right: 12px;
    width: 28px;
    height: 28px;
    background: var(--accent);
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
}

.template-icon {
    width: 64px;
    height: 64px;
    border-radius: 12px;
    background: rgba(35, 134, 54, 0.15);
    color: var(--accent);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    margin-bottom: 20px;
}

.template-name {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 8px;
}

.template-description {
    font-size: 0.875rem;
    color: var(--text-secondary);
    line-height: 1.5;
}

/* ========== CONFIG CARD ========== */
.config-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 28px;
    margin-bottom: 32px;
}

.config-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 24px;
}

.config-group {
    margin-bottom: 24px;
}

.config-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 8px;
    display: block;
}

.config-input,
.config-select {
    width: 100%;
    padding: 12px 16px;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text-primary);
    font-size: 0.875rem;
}

.config-input:focus,
.config-select:focus {
    outline: none;
    border-color: var(--accent);
}

.date-range-inputs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

/* ========== SECTIONS CHECKLIST ========== */
.sections-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 12px;
}

.section-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.section-item:hover {
    background: var(--bg-hover);
}

.section-item input[type="checkbox"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
    accent-color: var(--accent);
}

.section-info {
    flex: 1;
}

.section-name {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.875rem;
}

.section-description {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 2px;
}

/* ========== PREVIEW ========== */
.preview-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 32px;
    margin-bottom: 32px;
}

.preview-header {
    text-align: center;
    padding-bottom: 24px;
    border-bottom: 2px solid var(--border);
    margin-bottom: 32px;
}

.preview-logo {
    font-size: 2.5rem;
    color: var(--accent);
    margin-bottom: 16px;
}

.preview-title {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 8px;
}

.preview-subtitle {
    font-size: 1rem;
    color: var(--text-secondary);
}

.preview-info {
    display: flex;
    justify-content: space-between;
    padding: 20px 0;
    border-bottom: 1px solid var(--border);
    margin-bottom: 32px;
    font-size: 0.875rem;
}

.preview-metrics {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 32px;
}

.preview-metric {
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 20px;
    text-align: center;
}

.preview-metric-label {
    font-size: 0.813rem;
    color: var(--text-secondary);
    margin-bottom: 8px;
}

.preview-metric-value {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--accent);
}

.preview-section {
    margin-bottom: 32px;
}

.preview-section-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border);
}

.preview-placeholder {
    background: var(--bg-elevated);
    border: 2px dashed var(--border);
    border-radius: 8px;
    padding: 40px;
    text-align: center;
    color: var(--text-muted);
}

/* ========== WIZARD ACTIONS ========== */
.wizard-actions {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    margin-top: 32px;
}

.btn {
    padding: 12px 24px;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-secondary {
    background: var(--bg-elevated);
    color: var(--text-primary);
    border: 1px solid var(--border);
}

.btn-secondary:hover {
    background: var(--bg-hover);
}

.btn-primary {
    background: var(--accent);
    color: #fff;
}

.btn-primary:hover {
    background: #2ea043;
}

.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* ========== LOADING ========== */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 10000;
}

.loading-overlay.active {
    display: flex;
}

.loading-content {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 40px;
    text-align: center;
    min-width: 350px;
}

.loading-spinner {
    font-size: 3rem;
    color: var(--accent);
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.loading-text {
    margin-top: 20px;
    font-size: 1.125rem;
    color: var(--text-primary);
    font-weight: 600;
}

.loading-subtext {
    margin-top: 8px;
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.loading-progress {
    margin-top: 20px;
    height: 4px;
    background: var(--bg-elevated);
    border-radius: 2px;
    overflow: hidden;
}

.loading-progress-bar {
    height: 100%;
    background: var(--accent);
    width: 0%;
    transition: width 0.3s ease;
}

/* ========== RESPONSIVE ========== */
@media (max-width: 768px) {
    .templates-grid {
        grid-template-columns: 1fr;
    }
    
    .wizard-steps {
        flex-direction: column;
        gap: 16px;
    }
    
    .wizard-steps::before {
        display: none;
    }
    
    .date-range-inputs {
        grid-template-columns: 1fr;
    }
    
    .sections-list {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="relatorio-container">
    <div class="page-header">
        <div class="header-top">
            <h1 class="header-title">📑 Gerador de Relatórios</h1>
            <a href="javascript:loadContent('modules/tabelas/tabelas')" class="btn-back">
                <i class="fa-solid fa-arrow-left"></i> Voltar
            </a>
        </div>
        <p class="header-subtitle">Crie relatórios profissionais personalizados com dados e gráficos</p>
    </div>

    <!-- WIZARD STEPS -->
    <div class="wizard-steps">
        <div class="wizard-step active" data-step="1">
            <div class="step-number">1</div>
            <div class="step-label">Template</div>
        </div>
        <div class="wizard-step" data-step="2">
            <div class="step-number">2</div>
            <div class="step-label">Configuração</div>
        </div>
        <div class="wizard-step" data-step="3">
            <div class="step-number">3</div>
            <div class="step-label">Seções</div>
        </div>
        <div class="wizard-step" data-step="4">
            <div class="step-number">4</div>
            <div class="step-label">Preview & Gerar</div>
        </div>
    </div>

    <!-- STEP 1: TEMPLATE SELECTION -->
    <div class="step-content active" id="step1">
        <div class="templates-grid">
            <?php foreach ($templates as $key => $template): ?>
                <div class="template-card" onclick="selectTemplate('<?= $key ?>')">
                    <div class="template-icon">
                        <i class="fa-solid <?= $template['icon'] ?>"></i>
                    </div>
                    <div class="template-name"><?= $template['name'] ?></div>
                    <div class="template-description"><?= $template['description'] ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="wizard-actions">
            <div></div>
            <button class="btn btn-primary" id="btnStep1" onclick="nextStep(2)" disabled>
                Próximo <i class="fa-solid fa-arrow-right"></i>
            </button>
        </div>
    </div>

    <!-- STEP 2: CONFIGURATION -->
    <div class="step-content" id="step2">
        <div class="config-card">
            <h3 class="config-title">Configurações do Relatório</h3>
            
            <div class="config-group">
                <label class="config-label">Título do Relatório</label>
                <input type="text" class="config-input" id="reportTitle" 
                       placeholder="Ex: Relatório Mensal de Performance">
            </div>
            
            <div class="config-group">
                <label class="config-label">Período</label>
                <div class="date-range-inputs">
                    <input type="date" class="config-input" id="dateFrom" value="<?= date('Y-m-01') ?>">
                    <input type="date" class="config-input" id="dateTo" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            
            <div class="config-group">
                <label class="config-label">Formato de Saída</label>
                <select class="config-select" id="outputFormat">
                    <option value="pdf">PDF (Recomendado)</option>
                    <option value="excel">Excel (XLSX)</option>
                    <option value="web">Visualização Web</option>
                </select>
            </div>
            
            <div class="config-group">
                <label class="config-label">Incluir Gráficos</label>
                <select class="config-select" id="includeCharts">
                    <option value="yes">Sim, incluir todos os gráficos</option>
                    <option value="summary">Apenas gráficos resumidos</option>
                    <option value="no">Não incluir gráficos</option>
                </select>
            </div>
        </div>

        <div class="wizard-actions">
            <button class="btn btn-secondary" onclick="prevStep(1)">
                <i class="fa-solid fa-arrow-left"></i> Voltar
            </button>
            <button class="btn btn-primary" onclick="nextStep(3)">
                Próximo <i class="fa-solid fa-arrow-right"></i>
            </button>
        </div>
    </div>

    <!-- STEP 3: SECTIONS -->
    <div class="step-content" id="step3">
        <div class="config-card">
            <h3 class="config-title">Escolha as Seções do Relatório</h3>
            
            <div class="sections-list" id="sectionsList">
                <!-- Será preenchido via JavaScript -->
            </div>
        </div>

        <div class="wizard-actions">
            <button class="btn btn-secondary" onclick="prevStep(2)">
                <i class="fa-solid fa-arrow-left"></i> Voltar
            </button>
            <button class="btn btn-primary" onclick="nextStep(4)">
                Próximo <i class="fa-solid fa-arrow-right"></i>
            </button>
        </div>
    </div>

    <!-- STEP 4: PREVIEW -->
    <div class="step-content" id="step4">
        <div class="preview-card">
            <div class="preview-header">
                <div class="preview-logo">📊</div>
                <h2 class="preview-title" id="previewTitle">Relatório Executivo</h2>
                <p class="preview-subtitle">VisionGreen Analytics Platform</p>
            </div>
            
            <div class="preview-info">
                <div>
                    <strong>Período:</strong> <span id="previewPeriod">01/01/2026 - 13/01/2026</span>
                </div>
                <div>
                    <strong>Gerado em:</strong> <?= date('d/m/Y H:i') ?>
                </div>
            </div>
            
            <div class="preview-section">
                <h3 class="preview-section-title">Resumo Executivo</h3>
                <div class="preview-metrics">
                    <div class="preview-metric">
                        <div class="preview-metric-label">Total de Empresas</div>
                        <div class="preview-metric-value"><?= number_format($summary['total_companies'], 0) ?></div>
                    </div>
                    <div class="preview-metric">
                        <div class="preview-metric-label">Assinaturas Ativas</div>
                        <div class="preview-metric-value"><?= number_format($summary['active_subscriptions'], 0) ?></div>
                    </div>
                    <div class="preview-metric">
                        <div class="preview-metric-label">Receita do Mês</div>
                        <div class="preview-metric-value"><?= number_format($summary['total_revenue'], 0) ?> MT</div>
                    </div>
                    <div class="preview-metric">
                        <div class="preview-metric-label">MRR Total</div>
                        <div class="preview-metric-value"><?= number_format($summary['mrr'], 0) ?> MT</div>
                    </div>
                </div>
            </div>
            
            <div class="preview-section" id="previewSections">
                <!-- Seções selecionadas serão mostradas aqui -->
            </div>
        </div>

        <div class="wizard-actions">
            <button class="btn btn-secondary" onclick="prevStep(3)">
                <i class="fa-solid fa-arrow-left"></i> Voltar
            </button>
            <button class="btn btn-primary" onclick="generateReport()">
                <i class="fa-solid fa-file-arrow-down"></i> Gerar Relatório
            </button>
        </div>
    </div>
</div>

<!-- LOADING OVERLAY -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-content">
        <div class="loading-spinner">
            <i class="fa-solid fa-spinner"></i>
        </div>
        <div class="loading-text">Gerando relatório...</div>
        <div class="loading-subtext">Processando dados e criando visualizações</div>
        <div class="loading-progress">
            <div class="loading-progress-bar" id="progressBar"></div>
        </div>
    </div>
</div>

<script>
// Estado do wizard
let wizardState = {
    currentStep: 1,
    selectedTemplate: null,
    config: {},
    sections: []
};

// Templates disponíveis
const templates = <?= json_encode($templates) ?>;

// Seções disponíveis
const availableSections = {
    summary: { name: 'Resumo Executivo', description: 'Métricas principais e KPIs' },
    revenue: { name: 'Análise de Receita', description: 'Receita total, MRR e tendências' },
    growth: { name: 'Crescimento', description: 'Novos clientes e expansão' },
    companies: { name: 'Empresas', description: 'Lista e status das empresas' },
    products: { name: 'Produtos', description: 'Vendas e performance de produtos' },
    transactions: { name: 'Transações', description: 'Histórico detalhado de transações' },
    mrr: { name: 'MRR Breakdown', description: 'Análise detalhada do MRR' },
    forecast: { name: 'Projeções', description: 'Previsões financeiras' },
    retention: { name: 'Retenção', description: 'Taxa de retenção e cohorts' },
    churn: { name: 'Churn', description: 'Análise de cancelamentos' },
    health: { name: 'Saúde das Contas', description: 'Health scores e riscos' },
    documents: { name: 'Documentação', description: 'Status de documentos' },
    support: { name: 'Suporte', description: 'Tickets e atendimento' },
    activities: { name: 'Atividades', description: 'Log de atividades operacionais' }
};

// Selecionar template
function selectTemplate(key) {
    wizardState.selectedTemplate = key;
    
    // Atualizar visual
    document.querySelectorAll('.template-card').forEach(card => {
        card.classList.remove('selected');
    });
    event.currentTarget.classList.add('selected');
    
    // Habilitar botão próximo
    document.getElementById('btnStep1').disabled = false;
    
    console.log('📋 Template selecionado:', key);
}

// Navegar entre steps
function nextStep(step) {
    if (step === 3) {
        // Carregar seções do template selecionado
        loadSections();
    }
    
    if (step === 4) {
        // Coletar configurações
        wizardState.config = {
            title: document.getElementById('reportTitle').value || templates[wizardState.selectedTemplate].name,
            dateFrom: document.getElementById('dateFrom').value,
            dateTo: document.getElementById('dateTo').value,
            format: document.getElementById('outputFormat').value,
            charts: document.getElementById('includeCharts').value
        };
        
        // Atualizar preview
        updatePreview();
    }
    
    changeStep(step);
}

function prevStep(step) {
    changeStep(step);
}

function changeStep(step) {
    // Atualizar step visual
    document.querySelectorAll('.wizard-step').forEach((s, index) => {
        s.classList.remove('active', 'completed');
        if (index + 1 < step) {
            s.classList.add('completed');
        } else if (index + 1 === step) {
            s.classList.add('active');
        }
    });
    
    // Atualizar conteúdo
    document.querySelectorAll('.step-content').forEach(content => {
        content.classList.remove('active');
    });
    document.getElementById('step' + step).classList.add('active');
    
    wizardState.currentStep = step;
    
    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Carregar seções
function loadSections() {
    const template = templates[wizardState.selectedTemplate];
    const container = document.getElementById('sectionsList');
    container.innerHTML = '';
    
    const sectionsToShow = template.sections.length > 0 
        ? template.sections 
        : Object.keys(availableSections);
    
    sectionsToShow.forEach(key => {
        const section = availableSections[key];
        if (!section) return;
        
        const checked = template.sections.includes(key) ? 'checked' : '';
        
        container.innerHTML += `
            <div class="section-item" onclick="toggleSection(this, '${key}')">
                <input type="checkbox" id="section_${key}" ${checked} onclick="event.stopPropagation()">
                <div class="section-info">
                    <div class="section-name">${section.name}</div>
                    <div class="section-description">${section.description}</div>
                </div>
            </div>
        `;
    });
    
    // Inicializar seções selecionadas
    wizardState.sections = template.sections;
}

// Toggle seção
function toggleSection(element, key) {
    const checkbox = element.querySelector('input[type="checkbox"]');
    checkbox.checked = !checkbox.checked;
    
    if (checkbox.checked) {
        if (!wizardState.sections.includes(key)) {
            wizardState.sections.push(key);
        }
    } else {
        wizardState.sections = wizardState.sections.filter(s => s !== key);
    }
    
    console.log('📑 Seções selecionadas:', wizardState.sections);
}

// Atualizar preview
function updatePreview() {
    const config = wizardState.config;
    
    // Atualizar título
    document.getElementById('previewTitle').textContent = config.title;
    
    // Atualizar período
    const dateFrom = new Date(config.dateFrom).toLocaleDateString('pt-BR');
    const dateTo = new Date(config.dateTo).toLocaleDateString('pt-BR');
    document.getElementById('previewPeriod').textContent = `${dateFrom} - ${dateTo}`;
    
    // Mostrar seções selecionadas
    const container = document.getElementById('previewSections');
    container.innerHTML = '';
    
    wizardState.sections.forEach(key => {
        const section = availableSections[key];
        if (!section) return;
        
        container.innerHTML += `
            <div class="preview-section">
                <h3 class="preview-section-title">${section.name}</h3>
                <div class="preview-placeholder">
                    <i class="fa-solid fa-chart-bar" style="font-size: 2rem; margin-bottom: 12px; opacity: 0.3;"></i>
                    <p>${section.description}</p>
                    <small>Conteúdo será gerado no relatório final</small>
                </div>
            </div>
        `;
    });
}

// Gerar relatório
async function generateReport() {
    console.log('📊 Gerando relatório:', wizardState);
    
    // Mostrar loading
    const overlay = document.getElementById('loadingOverlay');
    overlay.classList.add('active');
    
    // Simular progresso
    simulateProgress();
    
    try {
        // Fazer requisição AJAX
        const response = await fetch('modules/tabelas/ajax/generate-report.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(wizardState)
        });
        
        if (!response.ok) {
            throw new Error('Erro ao gerar relatório');
        }
        
        const contentType = response.headers.get('content-type');
        
        // Se for JSON, houve erro
        if (contentType && contentType.includes('application/json')) {
            const result = await response.json();
            throw new Error(result.message || 'Erro desconhecido');
        }
        
        // Baixar arquivo
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        
        const date = new Date().toISOString().split('T')[0];
        const ext = wizardState.config.format === 'excel' ? 'xlsx' : (wizardState.config.format === 'pdf' ? 'pdf' : 'html');
        a.download = `relatorio_${date}.${ext}`;
        
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        
        console.log('✅ Relatório gerado com sucesso!');
        
        // Voltar ao início após 2 segundos
        setTimeout(() => {
            overlay.classList.remove('active');
            resetWizard();
        }, 2000);
        
    } catch (error) {
        console.error('❌ Erro:', error);
        overlay.classList.remove('active');
        alert('Erro ao gerar relatório: ' + error.message);
    }
}

// Simular progresso
function simulateProgress() {
    const bar = document.getElementById('progressBar');
    let progress = 0;
    
    const interval = setInterval(() => {
        progress += Math.random() * 30;
        if (progress > 90) progress = 90;
        bar.style.width = progress + '%';
    }, 500);
    
    // Limpar intervalo após 10 segundos
    setTimeout(() => {
        clearInterval(interval);
        bar.style.width = '100%';
    }, 10000);
}

// Reset wizard
function resetWizard() {
    wizardState = {
        currentStep: 1,
        selectedTemplate: null,
        config: {},
        sections: []
    };
    
    changeStep(1);
    
    document.querySelectorAll('.template-card').forEach(card => {
        card.classList.remove('selected');
    });
    
    document.getElementById('btnStep1').disabled = true;
}

console.log('✅ Relatorio.php carregado!');
</script>