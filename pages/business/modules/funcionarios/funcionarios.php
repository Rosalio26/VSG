<?php
/**
 * ================================================================================
 * VISIONGREEN - MÓDULO DE FUNCIONÁRIOS
 * Arquivo: pages/business/modules/funcionarios/funcionarios.php
 * Descrição: Gestão completa de funcionários da empresa
 * ================================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['auth']['user_id'])) {
    echo '<div style="padding: 40px; text-align: center; color: #ff4d4d;">Acesso Negado</div>';
    exit;
}

$userId = (int)$_SESSION['auth']['user_id'];
?>

<style>
/* ==================== GitHub Dark Theme ==================== */
:root {
    --gh-bg-primary: #0d1117;
    --gh-bg-secondary: #161b22;
    --gh-bg-tertiary: #21262d;
    --gh-border: #30363d;
    --gh-border-hover: #8b949e;
    --gh-text: #c9d1d9;
    --gh-text-secondary: #8b949e;
    --gh-text-muted: #6e7681;
    --gh-accent-green: #238636;
    --gh-accent-green-bright: #2ea043;
    --gh-accent-blue: #1f6feb;
    --gh-accent-yellow: #d29922;
    --gh-accent-red: #da3633;
}

.funcionarios-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 16px;
}

/* Header */
.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--gh-border);
}

.page-title {
    font-size: 24px;
    font-weight: 600;
    color: var(--gh-text);
    display: flex;
    align-items: center;
    gap: 10px;
}

.page-title i {
    color: var(--gh-accent-green-bright);
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.stat-card {
    background: var(--gh-bg-secondary);
    border: 1px solid var(--gh-border);
    border-radius: 6px;
    padding: 16px;
    transition: all 0.2s ease;
}

.stat-card:hover {
    border-color: var(--gh-border-hover);
    transform: translateY(-2px);
}

.stat-label {
    font-size: 12px;
    font-weight: 600;
    color: var(--gh-text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--gh-text);
}

/* Filters */
.filters-bar {
    background: var(--gh-bg-secondary);
    border: 1px solid var(--gh-border);
    border-radius: 6px;
    padding: 16px;
    margin-bottom: 16px;
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.filter-label {
    font-size: 12px;
    font-weight: 600;
    color: var(--gh-text-secondary);
}

.filter-input, .filter-select {
    padding: 5px 12px;
    min-height: 32px;
    background: var(--gh-bg-primary);
    border: 1px solid var(--gh-border);
    border-radius: 6px;
    color: var(--gh-text);
    font-size: 14px;
}

.filter-input:focus, .filter-select:focus {
    outline: none;
    border-color: var(--gh-accent-blue);
}

.btn {
    padding: 5px 16px;
    height: 32px;
    border: 1px solid var(--gh-border);
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
    background: transparent;
}

.btn-primary {
    background: var(--gh-accent-green);
    border-color: rgba(240, 246, 252, 0.1);
    color: #ffffff;
}

.btn-primary:hover {
    background: var(--gh-accent-green-bright);
}

.btn-secondary {
    background: var(--gh-bg-tertiary);
    border-color: var(--gh-border);
    color: var(--gh-text);
}

.btn-secondary:hover {
    background: var(--gh-bg-primary);
    border-color: var(--gh-border-hover);
}

.btn-danger {
    background: rgba(218, 54, 51, 0.1);
    border-color: var(--gh-accent-red);
    color: var(--gh-accent-red);
}

.btn-danger:hover {
    background: var(--gh-accent-red);
    color: #ffffff;
}

.btn-sm {
    padding: 2px 8px;
    height: 28px;
    font-size: 12px;
}

/* Table */
.table-card {
    background: var(--gh-bg-secondary);
    border: 1px solid var(--gh-border);
    border-radius: 6px;
    overflow: hidden;
}

.table-header {
    padding: 16px;
    border-bottom: 1px solid var(--gh-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.table-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--gh-text);
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

thead {
    background: var(--gh-bg-primary);
}

th {
    padding: 12px 16px;
    text-align: left;
    font-size: 12px;
    font-weight: 600;
    color: var(--gh-text-secondary);
    text-transform: uppercase;
    border-bottom: 1px solid var(--gh-border);
}

td {
    padding: 12px 16px;
    font-size: 14px;
    color: var(--gh-text);
    border-bottom: 1px solid var(--gh-border);
}

tr:last-child td {
    border-bottom: none;
}

tbody tr {
    transition: background 0.2s ease;
}

tbody tr:hover {
    background: var(--gh-bg-primary);
}

/* Avatar */
.avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--gh-accent-blue);
    color: #ffffff;
    font-weight: 600;
    font-size: 16px;
}

/* Badges */
.badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.badge-success {
    background: rgba(46, 160, 67, 0.15);
    color: var(--gh-accent-green-bright);
}

.badge-warning {
    background: rgba(210, 153, 34, 0.15);
    color: var(--gh-accent-yellow);
}

.badge-danger {
    background: rgba(218, 54, 51, 0.15);
    color: #ff7b72;
}

.badge-info {
    background: rgba(31, 111, 235, 0.15);
    color: var(--gh-accent-blue);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-icon {
    font-size: 64px;
    color: var(--gh-text-muted);
    margin-bottom: 16px;
}

.empty-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--gh-text);
    margin-bottom: 8px;
}

.empty-text {
    font-size: 14px;
    color: var(--gh-text-secondary);
    margin-bottom: 16px;
}

/* Loading */
.loading {
    text-align: center;
    padding: 40px;
    color: var(--gh-text-secondary);
}

.spinner {
    width: 40px;
    height: 40px;
    border: 3px solid rgba(46, 160, 67, 0.1);
    border-top-color: var(--gh-accent-green-bright);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin: 0 auto 16px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Action buttons group */
.actions-group {
    display: flex;
    gap: 6px;
}

/* MODAL SYSTEM */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(1, 4, 9, 0.85);
    backdrop-filter: blur(4px);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    animation: fadeIn 0.2s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-container {
    background: var(--gh-bg-secondary);
    border: 1px solid var(--gh-border);
    border-radius: 8px;
    max-width: 500px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    animation: slideUp 0.3s ease;
}

@keyframes slideUp {
    from {
        transform: translateY(20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid var(--gh-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-title {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: var(--gh-text);
    display: flex;
    align-items: center;
    gap: 8px;
}

.modal-close {
    background: none;
    border: none;
    color: var(--gh-text-secondary);
    font-size: 24px;
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    transition: all 0.2s;
}

.modal-close:hover {
    background: var(--gh-bg-tertiary);
    color: var(--gh-text);
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    padding: 16px 20px;
    border-top: 1px solid var(--gh-border);
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

.modal-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
    font-size: 28px;
}

.modal-icon.success {
    background: rgba(46, 160, 67, 0.15);
    color: var(--gh-accent-green-bright);
}

.modal-icon.error {
    background: rgba(218, 54, 51, 0.15);
    color: var(--gh-accent-red);
}

.modal-icon.warning {
    background: rgba(210, 153, 34, 0.15);
    color: var(--gh-accent-yellow);
}

.modal-icon.info {
    background: rgba(31, 111, 235, 0.15);
    color: var(--gh-accent-blue);
}

.modal-message {
    text-align: center;
    color: var(--gh-text);
    font-size: 15px;
    line-height: 1.6;
    margin-bottom: 16px;
}

.modal-details {
    background: var(--gh-bg-tertiary);
    border: 1px solid var(--gh-border);
    border-radius: 6px;
    padding: 12px;
    margin-top: 12px;
    font-size: 13px;
    color: var(--gh-text-secondary);
    line-height: 1.6;
}

.modal-link {
    background: var(--gh-bg-primary);
    border: 1px solid var(--gh-border);
    border-radius: 6px;
    padding: 12px;
    margin-top: 12px;
    word-break: break-all;
    font-family: monospace;
    font-size: 12px;
    color: var(--gh-accent-blue);
}

/* Responsive */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .filters-bar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .actions-group {
        flex-direction: column;
    }
    
    .modal-container {
        max-width: 100%;
    }
}
</style>

<div class="funcionarios-container">
    <!-- Header -->
    <div class="page-header">
        <h1 class="page-title">
            <i class="fa-solid fa-users"></i>
            Funcionários
        </h1>
        <button class="btn btn-primary" onclick="abrirModalCadastro()">
            <i class="fa-solid fa-plus"></i>
            Novo Funcionário
        </button>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid" id="statsGrid">
        <div class="stat-card">
            <div class="stat-label">Total Funcionários</div>
            <div class="stat-value" id="totalFuncionarios">--</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Ativos</div>
            <div class="stat-value" id="totalAtivos">--</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Inativos</div>
            <div class="stat-value" id="totalInativos">--</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Departamentos</div>
            <div class="stat-value" id="totalDepartamentos">--</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-bar">
        <div class="filter-group">
            <label class="filter-label">Status</label>
            <select class="filter-select" id="filterStatus">
                <option value="">Todos</option>
                <option value="ativo">Ativo</option>
                <option value="inativo">Inativo</option>
                <option value="ferias">Férias</option>
                <option value="afastado">Afastado</option>
            </select>
        </div>

        <div class="filter-group">
            <label class="filter-label">Departamento</label>
            <select class="filter-select" id="filterDepartamento">
                <option value="">Todos</option>
            </select>
        </div>

        <div class="filter-group">
            <label class="filter-label">Buscar</label>
            <input type="text" class="filter-input" id="searchInput" placeholder="Nome, cargo, email...">
        </div>
    </div>

    <!-- Table -->
    <div class="table-card">
        <div class="table-header">
            <div class="table-title">Lista de Funcionários</div>
            <span class="badge badge-info" id="totalRecords">0 registros</span>
        </div>

        <div class="table-wrapper">
            <table id="funcionariosTable">
                <thead>
                    <tr>
                        <th>Funcionário</th>
                        <th>Cargo</th>
                        <th>Departamento</th>
                        <th>Telefone</th>
                        <th>Admissão</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="funcionariosBody">
                    <tr>
                        <td colspan="7">
                            <div class="loading">
                                <div class="spinner"></div>
                                <div>Carregando funcionários...</div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    const userId = <?= $userId ?>;
    let funcionarios = [];
    let departamentos = [];

    // ========== SISTEMA DE MODAIS ==========
    
    function mostrarModal({ tipo = 'info', titulo, mensagem, detalhes, link, onConfirm, onCancel, showCancel = true }) {
        const iconMap = {
            success: 'fa-check-circle',
            error: 'fa-times-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };

        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay';
        overlay.innerHTML = `
            <div class="modal-container">
                <div class="modal-header">
                    <h3 class="modal-title">
                        <i class="fa-solid ${iconMap[tipo] || iconMap.info}"></i>
                        ${titulo}
                    </h3>
                    <button class="modal-close" onclick="fecharModal(this)">
                        <i class="fa-solid fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="modal-icon ${tipo}">
                        <i class="fa-solid ${iconMap[tipo] || iconMap.info}"></i>
                    </div>
                    <div class="modal-message">${mensagem}</div>
                    ${detalhes ? `<div class="modal-details">${detalhes}</div>` : ''}
                    ${link ? `<div class="modal-link">${link}</div>` : ''}
                </div>
                <div class="modal-footer">
                    ${showCancel ? `<button class="btn btn-secondary" onclick="fecharModal(this)">${onCancel ? 'Cancelar' : 'Fechar'}</button>` : ''}
                    ${onConfirm ? `<button class="btn btn-primary" data-action="confirm">Confirmar</button>` : ''}
                </div>
            </div>
        `;

        document.body.appendChild(overlay);

        // Event listeners
        if (onConfirm) {
            overlay.querySelector('[data-action="confirm"]').onclick = () => {
                onConfirm();
                overlay.remove();
            };
        }

        // Fechar com ESC
        const handleEsc = (e) => {
            if (e.key === 'Escape') {
                overlay.remove();
                document.removeEventListener('keydown', handleEsc);
            }
        };
        document.addEventListener('keydown', handleEsc);

        // Fechar clicando fora
        overlay.onclick = (e) => {
            if (e.target === overlay) {
                overlay.remove();
            }
        };
    }

    window.fecharModal = function(el) {
        const overlay = el.closest('.modal-overlay');
        if (overlay) overlay.remove();
    };

    // ========== FUNÇÕES PRINCIPAIS ==========

    // Carregar dados ao iniciar
    init();

    async function init() {
        await Promise.all([
            carregarFuncionarios(),
            carregarStats()
        ]);
        setupAutoBusca();
    }

    // Carregar funcionários
    async function carregarFuncionarios() {
        try {
            const status = document.getElementById('filterStatus').value;
            const departamento = document.getElementById('filterDepartamento').value;
            const search = document.getElementById('searchInput').value;

            const params = new URLSearchParams({
                user_id: userId,
                ...(status && { status }),
                ...(departamento && { departamento }),
                ...(search && { search })
            });

            const response = await fetch(`modules/funcionarios/actions/listar_funcionarios.php?${params}`);
            const data = await response.json();

            if (data.success) {
                funcionarios = data.funcionarios;
                departamentos = data.departamentos;
                renderFuncionarios();
                renderDepartamentosFilter();
                document.getElementById('totalRecords').textContent = `${funcionarios.length} registros`;
            } else {
                mostrarModal({
                    tipo: 'error',
                    titulo: 'Erro ao Carregar',
                    mensagem: 'Não foi possível carregar os funcionários',
                    detalhes: data.message,
                    showCancel: false
                });
            }
        } catch (error) {
            console.error('Erro:', error);
            mostrarModal({
                tipo: 'error',
                titulo: 'Erro ao Carregar',
                mensagem: 'Erro ao carregar funcionários',
                detalhes: error.message,
                showCancel: false
            });
        }
    }

    // Carregar estatísticas
    async function carregarStats() {
        try {
            const response = await fetch(`modules/funcionarios/actions/stats_funcionarios.php?user_id=${userId}`);
            const data = await response.json();

            if (data.success) {
                document.getElementById('totalFuncionarios').textContent = data.total;
                document.getElementById('totalAtivos').textContent = data.ativos;
                document.getElementById('totalInativos').textContent = data.inativos;
                document.getElementById('totalDepartamentos').textContent = data.departamentos;
            }
        } catch (error) {
            console.error('Erro:', error);
        }
    }

    // Setup busca automática
    function setupAutoBusca() {
        let timeout = null;
        
        document.getElementById('searchInput').addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => carregarFuncionarios(), 500);
        });
        
        document.getElementById('filterStatus').addEventListener('change', carregarFuncionarios);
        document.getElementById('filterDepartamento').addEventListener('change', carregarFuncionarios);
    }

    // Render funcionários
    function renderFuncionarios() {
        const tbody = document.getElementById('funcionariosBody');

        if (funcionarios.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="fa-solid fa-users"></i>
                            </div>
                            <div class="empty-title">Nenhum funcionário encontrado</div>
                            <div class="empty-text">Comece adicionando seu primeiro funcionário</div>
                            <button class="btn btn-primary" onclick="abrirModalCadastro()" style="margin-top: 16px;">
                                <i class="fa-solid fa-plus"></i>
                                Adicionar Funcionário
                            </button>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = funcionarios.map(func => `
            <tr>
                <td>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div class="avatar">${getInitials(func.nome)}</div>
                        <div>
                            <div style="font-weight: 600;">${func.nome}</div>
                            <div style="font-size: 12px; color: var(--gh-text-secondary);">${func.email || '-'}</div>
                            ${func.email_company ? `<div style="font-size: 11px; color: var(--gh-accent-blue); margin-top: 2px;"><i class="fa-solid fa-building"></i> ${func.email_company}</div>` : ''}
                            ${func.pode_acessar_sistema ? '<span style="font-size: 10px; background: rgba(0,255,136,0.1); color: #00ff88; padding: 2px 6px; border-radius: 4px; margin-top: 4px; display: inline-block;"><i class="fa-solid fa-key"></i> Acesso Concedido</span>' : ''}
                        </div>
                    </div>
                </td>
                <td>${func.cargo}</td>
                <td>${func.departamento || '-'}</td>
                <td>${func.telefone}</td>
                <td>${formatDate(func.data_admissao)}</td>
                <td>${getStatusBadge(func.status)}</td>
                <td>
                    <div class="actions-group">
                        <button class="btn btn-secondary btn-sm" onclick="verDetalhes(${func.id})" title="Ver detalhes">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                        <button class="btn btn-secondary btn-sm" onclick="editarFuncionario(${func.id})" title="Editar">
                            <i class="fa-solid fa-edit"></i>
                        </button>
                        ${!func.pode_acessar_sistema ? `
                        <button class="btn btn-primary btn-sm" onclick="concederAcesso(${func.id})" title="Conceder Acesso ao Sistema">
                            <i class="fa-solid fa-key"></i>
                        </button>
                        ` : `
                        <button class="btn btn-secondary btn-sm" onclick="gerenciarPermissoes(${func.id})" title="Gerenciar Permissões">
                            <i class="fa-solid fa-sliders"></i>
                        </button>
                        `}
                        <button class="btn btn-danger btn-sm" onclick="confirmarExclusao(${func.id}, '${func.nome}')" title="Excluir">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    // Render departamentos filter
    function renderDepartamentosFilter() {
        const select = document.getElementById('filterDepartamento');
        select.innerHTML = '<option value="">Todos</option>' +
            departamentos.map(d => `<option value="${d}">${d}</option>`).join('');
    }

    // Get initials
    function getInitials(nome) {
        return nome.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase();
    }

    // Status badge
    function getStatusBadge(status) {
        const badges = {
            'ativo': '<span class="badge badge-success"><i class="fa-solid fa-check"></i> Ativo</span>',
            'inativo': '<span class="badge badge-danger"><i class="fa-solid fa-times"></i> Inativo</span>',
            'ferias': '<span class="badge badge-info"><i class="fa-solid fa-umbrella-beach"></i> Férias</span>',
            'afastado': '<span class="badge badge-warning"><i class="fa-solid fa-exclamation"></i> Afastado</span>'
        };
        return badges[status] || status;
    }

    // Format date
    function formatDate(date) {
        return new Date(date).toLocaleDateString('pt-BR');
    }

    // Abrir modal cadastro
    window.abrirModalCadastro = function() {
        mostrarModalCadastro();
    };

    // Mostrar modal de cadastro
    function mostrarModalCadastro(funcionario = null) {
        const isEdit = funcionario !== null;
        const modal = document.createElement('div');
        modal.id = 'modalCadastro';
        modal.innerHTML = `
            <div style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(1, 4, 9, 0.8); backdrop-filter: blur(4px); z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 20px;">
                <div style="background: var(--gh-bg-secondary); border: 1px solid var(--gh-border); border-radius: 8px; max-width: 700px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);">
                    <!-- Header -->
                    <div style="padding: 20px; border-bottom: 1px solid var(--gh-border); display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="margin: 0; font-size: 18px; font-weight: 600; color: var(--gh-text);">
                            <i class="fa-solid fa-${isEdit ? 'edit' : 'plus'}"></i>
                            ${isEdit ? 'Editar' : 'Novo'} Funcionário
                        </h3>
                        <button onclick="fecharModalCadastro()" style="background: none; border: none; color: var(--gh-text-secondary); font-size: 24px; cursor: pointer; padding: 0; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 6px;">
                            <i class="fa-solid fa-times"></i>
                        </button>
                    </div>

                    <!-- Form -->
                    <form id="formFuncionario" onsubmit="salvarFuncionario(event)">
                        <div style="padding: 20px;">
                            <!-- Dados Pessoais -->
                            <div style="margin-bottom: 24px;">
                                <h4 style="margin: 0 0 16px 0; font-size: 14px; font-weight: 600; color: var(--gh-text); text-transform: uppercase; letter-spacing: 0.5px; padding-bottom: 8px; border-bottom: 1px solid var(--gh-border);">
                                    <i class="fa-solid fa-user"></i> Dados Pessoais
                                </h4>
                                
                                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                                    <div style="grid-column: 1 / -1;">
                                        <label style="display: block; font-size: 12px; font-weight: 600; color: var(--gh-text-secondary); margin-bottom: 6px;">
                                            Nome Completo *
                                        </label>
                                        <input type="text" name="nome" required class="filter-input" style="width: 100%;" value="${funcionario?.nome || ''}" placeholder="Ex: João Silva">
                                    </div>
                                    
                                    <div>
                                        <label style="display: block; font-size: 12px; font-weight: 600; color: var(--gh-text-secondary); margin-bottom: 6px;">
                                            Email *
                                        </label>
                                        <input type="email" name="email" required class="filter-input" style="width: 100%;" value="${funcionario?.email || ''}" placeholder="joao@example.com">
                                    </div>
                                    
                                    <div>
                                        <label style="display: block; font-size: 12px; font-weight: 600; color: var(--gh-text-secondary); margin-bottom: 6px;">
                                            Telefone *
                                        </label>
                                        <input type="tel" name="telefone" required class="filter-input" style="width: 100%;" value="${funcionario?.telefone || ''}" placeholder="+258 84 123 4567">
                                    </div>
                                    
                                    <div>
                                        <label style="display: block; font-size: 12px; font-weight: 600; color: var(--gh-text-secondary); margin-bottom: 6px;">
                                            Tipo Documento
                                        </label>
                                        <select name="tipo_documento" class="filter-select" style="width: 100%;">
                                            <option value="bi" ${funcionario?.tipo_documento === 'bi' ? 'selected' : ''}>BI</option>
                                            <option value="passaporte" ${funcionario?.tipo_documento === 'passaporte' ? 'selected' : ''}>Passaporte</option>
                                            <option value="dire" ${funcionario?.tipo_documento === 'dire' ? 'selected' : ''}>DIRE</option>
                                            <option value="nuit" ${funcionario?.tipo_documento === 'nuit' ? 'selected' : ''}>NUIT</option>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label style="display: block; font-size: 12px; font-weight: 600; color: var(--gh-text-secondary); margin-bottom: 6px;">
                                            Nº Documento
                                        </label>
                                        <input type="text" name="documento" class="filter-input" style="width: 100%;" value="${funcionario?.documento || ''}" placeholder="123456789">
                                    </div>
                                </div>
                            </div>

                            <!-- Dados Profissionais -->
                            <div style="margin-bottom: 24px;">
                                <h4 style="margin: 0 0 16px 0; font-size: 14px; font-weight: 600; color: var(--gh-text); text-transform: uppercase; letter-spacing: 0.5px; padding-bottom: 8px; border-bottom: 1px solid var(--gh-border);">
                                    <i class="fa-solid fa-briefcase"></i> Dados Profissionais
                                </h4>
                                
                                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                                    <div>
                                        <label style="display: block; font-size: 12px; font-weight: 600; color: var(--gh-text-secondary); margin-bottom: 6px;">
                                            Cargo *
                                        </label>
                                        <input type="text" name="cargo" required class="filter-input" style="width: 100%;" value="${funcionario?.cargo || ''}" placeholder="Ex: Gerente de Vendas">
                                    </div>
                                    
                                    <div>
                                        <label style="display: block; font-size: 12px; font-weight: 600; color: var(--gh-text-secondary); margin-bottom: 6px;">
                                            Departamento
                                        </label>
                                        <input type="text" name="departamento" class="filter-input" style="width: 100%;" value="${funcionario?.departamento || ''}" placeholder="Ex: Vendas">
                                    </div>
                                    
                                    <div>
                                        <label style="display: block; font-size: 12px; font-weight: 600; color: var(--gh-text-secondary); margin-bottom: 6px;">
                                            Data Admissão *
                                        </label>
                                        <input type="date" name="data_admissao" required class="filter-input" style="width: 100%;" value="${funcionario?.data_admissao || ''}">
                                    </div>
                                    
                                    <div>
                                        <label style="display: block; font-size: 12px; font-weight: 600; color: var(--gh-text-secondary); margin-bottom: 6px;">
                                            Salário (MZN)
                                        </label>
                                        <input type="number" step="0.01" name="salario" class="filter-input" style="width: 100%;" value="${funcionario?.salario || ''}" placeholder="25000.00">
                                    </div>
                                    
                                    <div style="grid-column: 1 / -1;">
                                        <label style="display: block; font-size: 12px; font-weight: 600; color: var(--gh-text-secondary); margin-bottom: 6px;">
                                            Status *
                                        </label>
                                        <select name="status" required class="filter-select" style="width: 100%;">
                                            <option value="ativo" ${!funcionario || funcionario?.status === 'ativo' ? 'selected' : ''}>Ativo</option>
                                            <option value="inativo" ${funcionario?.status === 'inativo' ? 'selected' : ''}>Inativo</option>
                                            <option value="ferias" ${funcionario?.status === 'ferias' ? 'selected' : ''}>Férias</option>
                                            <option value="afastado" ${funcionario?.status === 'afastado' ? 'selected' : ''}>Afastado</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Endereço e Observações -->
                            <div>
                                <h4 style="margin: 0 0 16px 0; font-size: 14px; font-weight: 600; color: var(--gh-text); text-transform: uppercase; letter-spacing: 0.5px; padding-bottom: 8px; border-bottom: 1px solid var(--gh-border);">
                                    <i class="fa-solid fa-info-circle"></i> Informações Adicionais
                                </h4>
                                
                                <div style="display: grid; gap: 16px;">
                                    <div>
                                        <label style="display: block; font-size: 12px; font-weight: 600; color: var(--gh-text-secondary); margin-bottom: 6px;">
                                            Endereço
                                        </label>
                                        <textarea name="endereco" class="filter-input" style="width: 100%; min-height: 60px; resize: vertical;" placeholder="Endereço completo">${funcionario?.endereco || ''}</textarea>
                                    </div>
                                    
                                    <div>
                                        <label style="display: block; font-size: 12px; font-weight: 600; color: var(--gh-text-secondary); margin-bottom: 6px;">
                                            Observações
                                        </label>
                                        <textarea name="observacoes" class="filter-input" style="width: 100%; min-height: 60px; resize: vertical;" placeholder="Observações adicionais">${funcionario?.observacoes || ''}</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div style="padding: 16px 20px; border-top: 1px solid var(--gh-border); display: flex; gap: 12px; justify-content: flex-end;">
                            <button type="button" onclick="fecharModalCadastro()" class="btn btn-secondary">
                                <i class="fa-solid fa-times"></i> Cancelar
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-save"></i> ${isEdit ? 'Atualizar' : 'Cadastrar'}
                            </button>
                        </div>
                        
                        <input type="hidden" name="id" value="${funcionario?.id || ''}">
                    </form>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        
        // Fechar com ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') fecharModalCadastro();
        });
    }

    // Salvar funcionário
    window.salvarFuncionario = async function(event) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);
        
        const isEdit = data.id !== '';
        
        // Não enviar user_id - será pego da sessão no backend
        delete data.user_id;
        
        try {
            const response = await fetch(`modules/funcionarios/actions/${isEdit ? 'editar' : 'cadastrar'}_funcionario.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                // Montar detalhes se for cadastro
                let detalhesMsg = '';
                if (!isEdit && result.email_company) {
                    detalhesMsg = `
                        <strong>✅ Email Corporativo Gerado:</strong><br>
                        <code style="background: #f0f0f0; padding: 4px 8px; border-radius: 3px; color: #0066cc; font-size: 14px;">${result.email_company}</code><br>
                        <small style="color: var(--gh-text-muted);">Este email será usado para login no sistema</small>
                    `;
                }
                
                mostrarModal({
                    tipo: 'success',
                    titulo: isEdit ? 'Funcionário Atualizado' : 'Funcionário Cadastrado',
                    mensagem: result.message,
                    detalhes: detalhesMsg,
                    showCancel: false,
                    onConfirm: () => {
                        fecharModalCadastro();
                        carregarFuncionarios();
                        carregarStats();
                    }
                });
            } else {
                mostrarModal({
                    tipo: 'error',
                    titulo: 'Erro ao Salvar',
                    mensagem: 'Não foi possível salvar o funcionário',
                    detalhes: result.message,
                    showCancel: false
                });
            }
        } catch (error) {
            console.error('Erro:', error);
            mostrarModal({
                tipo: 'error',
                titulo: 'Erro ao Salvar',
                mensagem: 'Erro ao salvar funcionário',
                detalhes: error.message,
                showCancel: false
            });
        }
    };

    // Fechar modal cadastro
    window.fecharModalCadastro = function() {
        const modal = document.getElementById('modalCadastro');
        if (modal) {
            modal.remove();
        }
    };

    // Ver detalhes
    window.verDetalhes = function(id) {
        const funcionario = funcionarios.find(f => f.id === id);
        if (!funcionario) return;

        const modal = document.createElement('div');
        modal.id = 'modalDetalhes';
        modal.innerHTML = `
            <div style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(1, 4, 9, 0.8); backdrop-filter: blur(4px); z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 20px;">
                <div style="background: var(--gh-bg-secondary); border: 1px solid var(--gh-border); border-radius: 8px; max-width: 700px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);">
                    <!-- Header -->
                    <div style="padding: 20px; border-bottom: 1px solid var(--gh-border); display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="margin: 0; font-size: 18px; font-weight: 600; color: var(--gh-text);">
                            <i class="fa-solid fa-id-card"></i>
                            Detalhes do Funcionário
                        </h3>
                        <button onclick="fecharModalDetalhes()" style="background: none; border: none; color: var(--gh-text-secondary); font-size: 24px; cursor: pointer; padding: 0; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 6px;">
                            <i class="fa-solid fa-times"></i>
                        </button>
                    </div>

                    <!-- Body -->
                    <div style="padding: 20px;">
                        <!-- Avatar e Info Principal -->
                        <div style="text-align: center; padding: 24px; background: var(--gh-bg-tertiary); border: 1px solid var(--gh-border); border-radius: 6px; margin-bottom: 16px;">
                            <div class="avatar" style="width: 80px; height: 80px; font-size: 32px; margin: 0 auto 16px;">
                                ${getInitials(funcionario.nome)}
                            </div>
                            <h3 style="margin: 0 0 4px 0; font-size: 20px; font-weight: 600; color: var(--gh-text);">
                                ${funcionario.nome}
                            </h3>
                            <p style="margin: 0 0 12px 0; font-size: 14px; color: var(--gh-text-secondary);">
                                ${funcionario.cargo}
                            </p>
                            ${getStatusBadge(funcionario.status)}
                        </div>

                        <!-- Dados Pessoais -->
                        <div style="background: var(--gh-bg-tertiary); border: 1px solid var(--gh-border); border-radius: 6px; padding: 16px; margin-bottom: 16px;">
                            <h4 style="margin: 0 0 12px 0; font-size: 14px; font-weight: 600; color: var(--gh-text); text-transform: uppercase; letter-spacing: 0.5px;">
                                <i class="fa-solid fa-user"></i> Dados Pessoais
                            </h4>
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
                                <div>
                                    <div style="font-size: 11px; color: var(--gh-text-secondary); margin-bottom: 4px;">Email</div>
                                    <div style="font-size: 14px; color: var(--gh-text); font-weight: 500;">${funcionario.email}</div>
                                </div>
                                <div>
                                    <div style="font-size: 11px; color: var(--gh-text-secondary); margin-bottom: 4px;">Telefone</div>
                                    <div style="font-size: 14px; color: var(--gh-text); font-weight: 500;">${funcionario.telefone}</div>
                                </div>
                                <div>
                                    <div style="font-size: 11px; color: var(--gh-text-secondary); margin-bottom: 4px;">Tipo Documento</div>
                                    <div style="font-size: 14px; color: var(--gh-text); font-weight: 500;">${funcionario.tipo_documento ? funcionario.tipo_documento.toUpperCase() : '-'}</div>
                                </div>
                                <div>
                                    <div style="font-size: 11px; color: var(--gh-text-secondary); margin-bottom: 4px;">Nº Documento</div>
                                    <div style="font-size: 14px; color: var(--gh-text); font-weight: 500;">${funcionario.documento || '-'}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Dados Profissionais -->
                        <div style="background: var(--gh-bg-tertiary); border: 1px solid var(--gh-border); border-radius: 6px; padding: 16px; margin-bottom: 16px;">
                            <h4 style="margin: 0 0 12px 0; font-size: 14px; font-weight: 600; color: var(--gh-text); text-transform: uppercase; letter-spacing: 0.5px;">
                                <i class="fa-solid fa-briefcase"></i> Dados Profissionais
                            </h4>
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
                                <div>
                                    <div style="font-size: 11px; color: var(--gh-text-secondary); margin-bottom: 4px;">Cargo</div>
                                    <div style="font-size: 14px; color: var(--gh-text); font-weight: 500;">${funcionario.cargo}</div>
                                </div>
                                <div>
                                    <div style="font-size: 11px; color: var(--gh-text-secondary); margin-bottom: 4px;">Departamento</div>
                                    <div style="font-size: 14px; color: var(--gh-text); font-weight: 500;">${funcionario.departamento || '-'}</div>
                                </div>
                                <div>
                                    <div style="font-size: 11px; color: var(--gh-text-secondary); margin-bottom: 4px;">Data Admissão</div>
                                    <div style="font-size: 14px; color: var(--gh-text); font-weight: 500;">${formatDate(funcionario.data_admissao)}</div>
                                </div>
                                <div>
                                    <div style="font-size: 11px; color: var(--gh-text-secondary); margin-bottom: 4px;">Tempo de Empresa</div>
                                    <div style="font-size: 14px; color: var(--gh-text); font-weight: 500;">${calcularTempoEmpresa(funcionario.data_admissao)}</div>
                                </div>
                                <div style="grid-column: 1 / -1;">
                                    <div style="font-size: 11px; color: var(--gh-text-secondary); margin-bottom: 4px;">Salário</div>
                                    <div style="font-size: 16px; color: var(--gh-accent-green-bright); font-weight: 700;">
                                        ${funcionario.salario ? formatMoney(funcionario.salario) : 'Não informado'}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Informações Adicionais -->
                        ${funcionario.endereco || funcionario.observacoes ? `
                        <div style="background: var(--gh-bg-tertiary); border: 1px solid var(--gh-border); border-radius: 6px; padding: 16px;">
                            <h4 style="margin: 0 0 12px 0; font-size: 14px; font-weight: 600; color: var(--gh-text); text-transform: uppercase; letter-spacing: 0.5px;">
                                <i class="fa-solid fa-info-circle"></i> Informações Adicionais
                            </h4>
                            ${funcionario.endereco ? `
                            <div style="margin-bottom: ${funcionario.observacoes ? '12px' : '0'};">
                                <div style="font-size: 11px; color: var(--gh-text-secondary); margin-bottom: 4px;">Endereço</div>
                                <div style="font-size: 14px; color: var(--gh-text);">${funcionario.endereco}</div>
                            </div>
                            ` : ''}
                            ${funcionario.observacoes ? `
                            <div>
                                <div style="font-size: 11px; color: var(--gh-text-secondary); margin-bottom: 4px;">Observações</div>
                                <div style="font-size: 14px; color: var(--gh-text);">${funcionario.observacoes}</div>
                            </div>
                            ` : ''}
                        </div>
                        ` : ''}
                    </div>

                    <!-- Footer -->
                    <div style="padding: 16px 20px; border-top: 1px solid var(--gh-border); display: flex; gap: 12px; justify-content: flex-end;">
                        <button onclick="fecharModalDetalhes()" class="btn btn-secondary">
                            <i class="fa-solid fa-times"></i> Fechar
                        </button>
                        <button onclick="fecharModalDetalhes(); editarFuncionario(${id})" class="btn btn-primary">
                            <i class="fa-solid fa-edit"></i> Editar
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        
        // Fechar com ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') fecharModalDetalhes();
        });
    };

    // Fechar modal detalhes
    window.fecharModalDetalhes = function() {
        const modal = document.getElementById('modalDetalhes');
        if (modal) {
            modal.remove();
        }
    };

    // Calcular tempo empresa
    function calcularTempoEmpresa(dataAdmissao) {
        const hoje = new Date();
        const admissao = new Date(dataAdmissao);
        const diff = hoje - admissao;
        
        const anos = Math.floor(diff / (365 * 24 * 60 * 60 * 1000));
        const meses = Math.floor((diff % (365 * 24 * 60 * 60 * 1000)) / (30 * 24 * 60 * 60 * 1000));
        
        if (anos > 0) {
            return `${anos} ano${anos > 1 ? 's' : ''} e ${meses} mês${meses > 1 ? 'es' : ''}`;
        } else {
            return `${meses} mês${meses > 1 ? 'es' : ''}`;
        }
    }

    // Format money
    function formatMoney(value) {
        return new Intl.NumberFormat('pt-MZ', {
            style: 'currency',
            currency: 'MZN'
        }).format(value);
    }

    // Editar funcionário
    window.editarFuncionario = function(id) {
        const funcionario = funcionarios.find(f => f.id === id);
        if (!funcionario) return;
        
        mostrarModalCadastro(funcionario);
    };

    // Confirmar exclusão
    window.confirmarExclusao = async function(id, nome) {
        mostrarModal({
            tipo: 'warning',
            titulo: 'Confirmar Exclusão',
            mensagem: `Deseja realmente excluir o funcionário <strong>"${nome}"</strong>?`,
            detalhes: 'Esta ação não pode ser desfeita.',
            onConfirm: async () => {
                try {
                    const response = await fetch('modules/funcionarios/actions/excluir_funcionario.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: id })
                    });

                    const data = await response.json();

                    if (data.success) {
                        mostrarModal({
                            tipo: 'success',
                            titulo: 'Funcionário Excluído',
                            mensagem: 'Funcionário excluído com sucesso!',
                            showCancel: false,
                            onConfirm: () => {
                                carregarFuncionarios();
                                carregarStats();
                            }
                        });
                    } else {
                        mostrarModal({
                            tipo: 'error',
                            titulo: 'Erro ao Excluir',
                            mensagem: 'Não foi possível excluir o funcionário',
                            detalhes: data.message,
                            showCancel: false
                        });
                    }
                } catch (error) {
                    console.error('Erro:', error);
                    mostrarModal({
                        tipo: 'error',
                        titulo: 'Erro ao Excluir',
                        mensagem: 'Erro ao excluir funcionário',
                        detalhes: error.message,
                        showCancel: false
                    });
                }
            }
        });
    };

    // Conceder acesso ao sistema
    window.concederAcesso = async function(funcionarioId) {
        const funcionario = funcionarios.find(f => f.id === funcionarioId);
        if (!funcionario) return;

        mostrarModal({
            tipo: 'warning',
            titulo: 'Conceder Acesso ao Sistema',
            mensagem: `Deseja conceder acesso ao sistema para <strong>${funcionario.nome}</strong>?`,
            detalhes: 'Um email será enviado para o email pessoal do funcionário com instruções para definir senha.',
            onConfirm: async () => {
                try {
                    const response = await fetch('modules/funcionarios/actions/conceder_acesso.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            employee_id: funcionarioId,
                            permissions: ['mensagens', 'produtos', 'vendas', 'relatorios']
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        // Montar mensagem com emails CORRETOS
                        let emailInfo = '';
                        if (result.email_pessoal && result.email_company) {
                            emailInfo = `
                                <strong>📧 Email Pessoal (recebeu o email):</strong><br>
                                <span style="color: var(--gh-text);">${result.email_pessoal}</span><br><br>
                                <strong>🏢 Email para Login no Sistema:</strong><br>
                                <code style="background: #f0f0f0; padding: 4px 8px; border-radius: 3px; color: #0066cc; font-size: 14px;">${result.email_company}</code>
                            `;
                        } else if (result.email_company) {
                            emailInfo = `
                                <strong>🏢 Email para Login:</strong><br>
                                <code style="background: #f0f0f0; padding: 4px 8px; border-radius: 3px; color: #0066cc;">${result.email_company}</code>
                            `;
                        }

                        const avisoEmail = !result.email_enviado 
                            ? '<br><br><strong>⚠️ AVISO:</strong> Falha no envio do email, mas o acesso foi concedido. Compartilhe o link manualmente.' 
                            : '';

                        mostrarModal({
                            tipo: 'success',
                            titulo: 'Acesso Concedido!',
                            mensagem: `Acesso concedido com sucesso para <strong>${funcionario.nome}</strong>`,
                            detalhes: emailInfo + avisoEmail,
                            link: result.link_acesso,
                            showCancel: false,
                            onConfirm: () => {
                                carregarFuncionarios();
                            }
                        });
                    } else {
                        mostrarModal({
                            tipo: 'error',
                            titulo: 'Erro ao Conceder Acesso',
                            mensagem: 'Não foi possível conceder acesso ao sistema',
                            detalhes: result.message,
                            showCancel: false
                        });
                    }
                } catch (error) {
                    console.error('Erro:', error);
                    mostrarModal({
                        tipo: 'error',
                        titulo: 'Erro ao Conceder Acesso',
                        mensagem: 'Erro ao conceder acesso',
                        detalhes: error.message,
                        showCancel: false
                    });
                }
            }
        });
    };

    // Gerenciar permissões
    window.gerenciarPermissoes = function(funcionarioId) {
        mostrarModal({
            tipo: 'info',
            titulo: 'Gerenciar Permissões',
            mensagem: 'Modal de gerenciamento de permissões será implementado em breve.',
            detalhes: `ID do funcionário: ${funcionarioId}`,
            showCancel: false
        });
    };

    console.log('✅ Módulo de Funcionários carregado com sistema de modais');
})();
</script>