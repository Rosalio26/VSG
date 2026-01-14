<?php
/**
 * ================================================================================
 * VISIONGREEN DASHBOARD - PLATAFORMAS DO SISTEMA (COMPLETO)
 * Módulo: modules/dashboard/plataformas.php
 * Descrição: Gestão por tipo de usuário e empresa (8 plataformas)
 * Proteção: Admin NÃO VÊ plataforma SuperAdmin
 * Usa: dashboard-components.css
 * ================================================================================
 */

if (!defined('IS_ADMIN_PAGE')) {
    require_once '../../../../registration/includes/db.php';
    session_start();
}

$adminId = $_SESSION['auth']['user_id'] ?? 0;
$adminRole = $_SESSION['auth']['role'] ?? 'admin';
$isSuperAdmin = ($adminRole === 'superadmin');

/* ================= PLATAFORMAS DE USUÁRIOS ================= */
$plataformas_usuarios = [
    'person' => [
        'nome' => 'Person',
        'nome_completo' => 'Usuários Pessoa Física',
        'icone' => 'fa-users',
        'cor' => '#3fb950',
        'descricao' => 'Usuários individuais cadastrados na plataforma',
        'tipo_query' => 'type',
        'query_condicao' => "type = 'person'",
        'tabela' => 'users'
    ],
    'admin' => [
        'nome' => 'Admin',
        'nome_completo' => 'Administradores',
        'icone' => 'fa-user-shield',
        'cor' => '#f85149',
        'descricao' => 'Administradores com acesso ao painel de gestão',
        'tipo_query' => 'role',
        'query_condicao' => "role = 'admin'",
        'tabela' => 'users'
    ]
];

// 🔐 SuperAdmin Platform - ONLY visible to SuperAdmin
if ($isSuperAdmin) {
    $plataformas_usuarios['superadmin'] = [
        'nome' => 'SuperAdmin',
        'nome_completo' => 'Super Administradores',
        'icone' => 'fa-crown',
        'cor' => '#a371f7',
        'descricao' => 'Super administradores com controle total do sistema',
        'tipo_query' => 'role',
        'query_condicao' => "role = 'superadmin'",
        'tabela' => 'users'
    ];
}

/* ================= PLATAFORMAS DE EMPRESAS ================= */
$plataformas_empresas = [
    'MEI' => [
        'nome' => 'MEI',
        'nome_completo' => 'Microempreendedor Individual',
        'icone' => 'fa-user-tie',
        'cor' => '#238636',
        'descricao' => 'Pequenos empreendedores que faturam até R$ 81 mil por ano',
        'tipo_query' => 'business_type',
        'query_condicao' => "business_type = 'MEI'",
        'tabela' => 'businesses'
    ],
    'LTDA' => [
        'nome' => 'LTDA',
        'nome_completo' => 'Sociedade Limitada',
        'icone' => 'fa-building',
        'cor' => '#388bfd',
        'descricao' => 'Empresas de pequeno e médio porte com sócios',
        'tipo_query' => 'business_type',
        'query_condicao' => "business_type = 'LTDA'",
        'tabela' => 'businesses'
    ],
    'SA' => [
        'nome' => 'SA',
        'nome_completo' => 'Sociedade Anônima',
        'icone' => 'fa-landmark',
        'cor' => '#d29922',
        'descricao' => 'Grandes empresas de capital aberto ou fechado',
        'tipo_query' => 'business_type',
        'query_condicao' => "business_type = 'SA'",
        'tabela' => 'businesses'
    ],
    'EIRELI' => [
        'nome' => 'EIRELI',
        'nome_completo' => 'Empresa Individual de Responsabilidade Limitada',
        'icone' => 'fa-user-shield',
        'cor' => '#a371f7',
        'descricao' => 'Empresa individual com responsabilidade limitada',
        'tipo_query' => 'business_type',
        'query_condicao' => "business_type = 'EIRELI'",
        'tabela' => 'businesses'
    ],
    'SLU' => [
        'nome' => 'SLU',
        'nome_completo' => 'Sociedade Limitada Unipessoal',
        'icone' => 'fa-briefcase',
        'cor' => '#3fb950',
        'descricao' => 'Sociedade com um único sócio',
        'tipo_query' => 'business_type',
        'query_condicao' => "business_type = 'SLU'",
        'tabela' => 'businesses'
    ]
];

/* ================= BUSCAR ESTATÍSTICAS PARA USUÁRIOS ================= */
foreach ($plataformas_usuarios as $tipo => &$dados) {
    if ($isSuperAdmin || $tipo !== 'superadmin') {
        $query_condicao = $dados['query_condicao'];
        
        $sql = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as ativos,
                SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inativos,
                SUM(CASE WHEN is_in_lockdown = 1 THEN 1 ELSE 0 END) as bloqueados,
                SUM(CASE WHEN last_activity > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 15 MINUTE)) THEN 1 ELSE 0 END) as online,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as novos_30d
            FROM users 
            WHERE $query_condicao
            AND deleted_at IS NULL
        ";
        
        $stats = $mysqli->query($sql)->fetch_assoc();
        
        $dados['stats'] = $stats;
        $dados['taxa_ativacao'] = $stats['total'] > 0 ? round(($stats['ativos'] / $stats['total']) * 100, 1) : 0;
    }
}

/* ================= BUSCAR ESTATÍSTICAS PARA EMPRESAS ================= */
foreach ($plataformas_empresas as $tipo => &$dados) {
    $query_condicao = $dados['query_condicao'];
    
    $sql = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status_documentos = 'pendente' THEN 1 ELSE 0 END) as pendentes,
            SUM(CASE WHEN status_documentos = 'aprovado' THEN 1 ELSE 0 END) as aprovados,
            SUM(CASE WHEN status_documentos = 'rejeitado' THEN 1 ELSE 0 END) as rejeitados
        FROM businesses b
        INNER JOIN users u ON b.user_id = u.id
        WHERE $query_condicao
        AND u.deleted_at IS NULL
    ";
    
    $stats = $mysqli->query($sql)->fetch_assoc();
    
    $dados['stats'] = $stats;
    $dados['taxa_aprovacao'] = $stats['total'] > 0 ? round(($stats['aprovados'] / $stats['total']) * 100, 1) : 0;
}

/* ================= ESTATÍSTICAS GERAIS ================= */
if ($isSuperAdmin) {
    $total_usuarios = $mysqli->query("SELECT COUNT(*) as total FROM users WHERE deleted_at IS NULL")->fetch_assoc()['total'];
} else {
    $total_usuarios = $mysqli->query("SELECT COUNT(*) as total FROM users WHERE deleted_at IS NULL AND role != 'superadmin'")->fetch_assoc()['total'];
}

$total_empresas = $mysqli->query("SELECT COUNT(*) as total FROM businesses b INNER JOIN users u ON b.user_id = u.id WHERE u.deleted_at IS NULL")->fetch_assoc()['total'];
$total_plataformas = count($plataformas_usuarios) + count($plataformas_empresas);
?>

<!-- HEADER -->
<div style="margin-bottom: 32px;">
    <h1 style="color: var(--text-title); font-size: 2rem; font-weight: 800; margin: 0 0 8px 0;">
        <i class="fa-solid fa-layer-group" style="color: var(--accent);"></i>
        Plataformas do Sistema
        <?php if (!$isSuperAdmin): ?>
            <span class="badge info" style="margin-left: 12px; font-size: 0.8rem;">
                <i class="fa-solid fa-info-circle"></i>
                Visualização Limitada
            </span>
        <?php endif; ?>
    </h1>
    <p style="color: var(--text-secondary); font-size: 0.938rem; display: flex; align-items: center; gap: 8px;">
        <i class="fa-solid fa-circle" style="color: var(--accent); font-size: 0.5rem;"></i>
        Gestão completa por tipo de usuário e empresa
    </p>
</div>

<!-- OVERVIEW KPIs -->
<div class="stats-grid mb-3">
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="stat-label">Total de Usuários</div>
        <div class="stat-value"><?= number_format($total_usuarios, 0, ',', '.') ?></div>
        <div class="stat-change neutral">
            <i class="fa-solid fa-user-group"></i>
            <?= count($plataformas_usuarios) ?> categorias
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fa-solid fa-building"></i>
        </div>
        <div class="stat-label">Total de Empresas</div>
        <div class="stat-value"><?= number_format($total_empresas, 0, ',', '.') ?></div>
        <div class="stat-change neutral">
            <i class="fa-solid fa-briefcase"></i>
            5 tipos
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fa-solid fa-shapes"></i>
        </div>
        <div class="stat-label">Plataformas Ativas</div>
        <div class="stat-value"><?= $total_plataformas ?></div>
        <div class="stat-change positive">
            <i class="fa-solid fa-chart-pie"></i>
            Todas
        </div>
    </div>
</div>

<!-- TABS -->
<div class="tabs mb-3">
    <button class="tab-btn active" onclick="switchTab('usuarios')">
        <i class="fa-solid fa-users"></i>
        Usuários (<?= count($plataformas_usuarios) ?>)
    </button>
    <button class="tab-btn" onclick="switchTab('empresas')">
        <i class="fa-solid fa-building"></i>
        Empresas (5)
    </button>
</div>

<!-- TAB CONTENT: USUÁRIOS -->
<div id="tab-usuarios" class="tab-content active">
    <div style="margin-bottom: 16px;">
        <h2 style="color: var(--text-title); font-size: 1.25rem; font-weight: 700; margin: 0;">
            <i class="fa-solid fa-user-group" style="color: var(--accent);"></i>
            Plataformas de Usuários
        </h2>
        <p style="color: var(--text-muted); font-size: 0.875rem; margin: 8px 0 0 0;">
            Person, Admin <?= $isSuperAdmin ? 'e SuperAdmin' : '' ?>
        </p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); gap: 24px;">
        
        <?php foreach ($plataformas_usuarios as $tipo => $dados): ?>
        <div class="card" style="position: relative; overflow: hidden;">
            
            <!-- Barra colorida no topo -->
            <div style="position: absolute; top: 0; left: 0; right: 0; height: 4px; background: <?= $dados['cor'] ?>;"></div>
            
            <!-- Badge SuperAdmin (se for plataforma SuperAdmin) -->
            <?php if ($tipo === 'superadmin'): ?>
                <div style="position: absolute; top: 16px; right: 16px; z-index: 10;">
                    <span class="badge error" style="animation: pulse 2s infinite; font-size: 0.7rem;">
                        <i class="fa-solid fa-crown"></i>
                        SUPERADMIN ONLY
                    </span>
                </div>
            <?php endif; ?>
            
            <!-- HEADER -->
            <div class="card-header" style="display: flex; align-items: center; gap: 20px; padding: 24px;">
                <!-- Ícone -->
                <div style="width: 70px; height: 70px; border-radius: 12px; background: <?= $dados['cor'] ?>20; border: 2px solid <?= $dados['cor'] ?>; display: flex; align-items: center; justify-content: center; font-size: 2rem; color: <?= $dados['cor'] ?>; flex-shrink: 0;">
                    <i class="fa-solid <?= $dados['icone'] ?>"></i>
                </div>
                
                <!-- Info -->
                <div style="flex: 1;">
                    <h2 style="color: var(--text-title); font-size: 1.5rem; font-weight: 800; margin: 0 0 4px 0;">
                        <?= $dados['nome'] ?>
                    </h2>
                    <div style="color: var(--text-secondary); font-size: 0.813rem; font-weight: 600; margin-bottom: 6px;">
                        <?= $dados['nome_completo'] ?>
                    </div>
                    <p style="color: var(--text-muted); font-size: 0.75rem; line-height: 1.4; margin: 0;">
                        <?= $dados['descricao'] ?>
                    </p>
                </div>
                
                <!-- Progress Ring -->
                <div style="width: 60px; height: 60px; border-radius: 50%; background: conic-gradient(<?= $dados['cor'] ?> <?= $dados['taxa_ativacao'] ?>%, var(--bg-elevated) 0); display: flex; align-items: center; justify-content: center; position: relative; flex-shrink: 0;">
                    <div style="position: absolute; width: 45px; height: 45px; background: var(--bg-card); border-radius: 50%;"></div>
                    <span style="position: relative; z-index: 1; color: var(--text-title); font-size: 0.875rem; font-weight: 800;">
                        <?= $dados['taxa_ativacao'] ?>%
                    </span>
                </div>
            </div>

            <!-- ESTATÍSTICAS -->
            <div class="card-body" style="padding: 0; border-top: 1px solid var(--border);">
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0;">
                    
                    <div style="text-align: center; padding: 20px; border-right: 1px solid var(--border);">
                        <div style="color: var(--text-primary); font-size: 1.5rem; font-weight: 800; margin-bottom: 4px;">
                            <?= $dados['stats']['total'] ?>
                        </div>
                        <div style="color: var(--text-muted); font-size: 0.688rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                            Total
                        </div>
                    </div>
                    
                    <div style="text-align: center; padding: 20px; border-right: 1px solid var(--border);">
                        <div style="color: #3fb950; font-size: 1.5rem; font-weight: 800; margin-bottom: 4px;">
                            <?= $dados['stats']['ativos'] ?>
                        </div>
                        <div style="color: var(--text-muted); font-size: 0.688rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                            Ativos
                        </div>
                    </div>
                    
                    <div style="text-align: center; padding: 20px;">
                        <div style="color: #238636; font-size: 1.5rem; font-weight: 800; margin-bottom: 4px; animation: <?= $dados['stats']['online'] > 0 ? 'pulse 2s infinite' : 'none' ?>;">
                            <?= $dados['stats']['online'] ?>
                        </div>
                        <div style="color: var(--text-muted); font-size: 0.688rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                            Online
                        </div>
                    </div>
                    
                </div>
                
                <!-- Linha adicional: Bloqueados + Novos -->
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0; border-top: 1px solid var(--border);">
                    
                    <div style="text-align: center; padding: 16px; border-right: 1px solid var(--border);">
                        <div style="color: #f85149; font-size: 1.2rem; font-weight: 800; margin-bottom: 4px;">
                            <?= $dados['stats']['bloqueados'] ?>
                        </div>
                        <div style="color: var(--text-muted); font-size: 0.688rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                            Bloqueados
                        </div>
                    </div>
                    
                    <div style="text-align: center; padding: 16px;">
                        <div style="color: #388bfd; font-size: 1.2rem; font-weight: 800; margin-bottom: 4px;">
                            <?= $dados['stats']['novos_30d'] ?>
                        </div>
                        <div style="color: var(--text-muted); font-size: 0.688rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                            Novos (30d)
                        </div>
                    </div>
                    
                </div>
            </div>

            <!-- AÇÕES -->
            <div class="card-footer" style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button class="btn btn-primary" style="flex: 1; min-width: 130px;" onclick="verUsuarios('<?= $tipo ?>')">
                    <i class="fa-solid fa-eye"></i>
                    Ver Usuários
                </button>
                
                <button class="btn btn-secondary" style="flex: 1; min-width: 130px;" onclick="filtrarOnline('<?= $tipo ?>')">
                    <i class="fa-solid fa-wifi"></i>
                    Online
                </button>
                
                <button class="btn btn-ghost" style="flex: 1; min-width: 130px;" onclick="gerarRelatorioUsuarios('<?= $tipo ?>')">
                    <i class="fa-solid fa-file-download"></i>
                    Relatório
                </button>
            </div>
        </div>
        <?php endforeach; ?>

    </div>
</div>

<!-- TAB CONTENT: EMPRESAS -->
<div id="tab-empresas" class="tab-content" style="display: none;">
    <div style="margin-bottom: 16px;">
        <h2 style="color: var(--text-title); font-size: 1.25rem; font-weight: 700; margin: 0;">
            <i class="fa-solid fa-building" style="color: var(--accent);"></i>
            Plataformas Empresariais
        </h2>
        <p style="color: var(--text-muted); font-size: 0.875rem; margin: 8px 0 0 0;">
            MEI, LTDA, SA, EIRELI e SLU
        </p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); gap: 24px;">
        
        <?php foreach ($plataformas_empresas as $tipo => $dados): ?>
        <div class="card" style="position: relative; overflow: hidden;">
            
            <!-- Barra colorida no topo -->
            <div style="position: absolute; top: 0; left: 0; right: 0; height: 4px; background: <?= $dados['cor'] ?>;"></div>
            
            <!-- HEADER -->
            <div class="card-header" style="display: flex; align-items: center; gap: 20px; padding: 24px;">
                <!-- Ícone -->
                <div style="width: 70px; height: 70px; border-radius: 12px; background: <?= $dados['cor'] ?>20; border: 2px solid <?= $dados['cor'] ?>; display: flex; align-items: center; justify-content: center; font-size: 2rem; color: <?= $dados['cor'] ?>; flex-shrink: 0;">
                    <i class="fa-solid <?= $dados['icone'] ?>"></i>
                </div>
                
                <!-- Info -->
                <div style="flex: 1;">
                    <h2 style="color: var(--text-title); font-size: 1.5rem; font-weight: 800; margin: 0 0 4px 0;">
                        <?= $dados['nome'] ?>
                    </h2>
                    <div style="color: var(--text-secondary); font-size: 0.813rem; font-weight: 600; margin-bottom: 6px;">
                        <?= $dados['nome_completo'] ?>
                    </div>
                    <p style="color: var(--text-muted); font-size: 0.75rem; line-height: 1.4; margin: 0;">
                        <?= $dados['descricao'] ?>
                    </p>
                </div>
                
                <!-- Progress Ring -->
                <div style="width: 60px; height: 60px; border-radius: 50%; background: conic-gradient(<?= $dados['cor'] ?> <?= $dados['taxa_aprovacao'] ?>%, var(--bg-elevated) 0); display: flex; align-items: center; justify-content: center; position: relative; flex-shrink: 0;">
                    <div style="position: absolute; width: 45px; height: 45px; background: var(--bg-card); border-radius: 50%;"></div>
                    <span style="position: relative; z-index: 1; color: var(--text-title); font-size: 0.875rem; font-weight: 800;">
                        <?= $dados['taxa_aprovacao'] ?>%
                    </span>
                </div>
            </div>

            <!-- ESTATÍSTICAS -->
            <div class="card-body" style="padding: 0; border-top: 1px solid var(--border);">
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0;">
                    
                    <div style="text-align: center; padding: 20px; border-right: 1px solid var(--border);">
                        <div style="color: var(--text-primary); font-size: 1.5rem; font-weight: 800; margin-bottom: 4px;">
                            <?= $dados['stats']['total'] ?>
                        </div>
                        <div style="color: var(--text-muted); font-size: 0.688rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                            Total
                        </div>
                    </div>
                    
                    <div style="text-align: center; padding: 20px; border-right: 1px solid var(--border);">
                        <div style="color: #d29922; font-size: 1.5rem; font-weight: 800; margin-bottom: 4px;">
                            <?= $dados['stats']['pendentes'] ?>
                        </div>
                        <div style="color: var(--text-muted); font-size: 0.688rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                            Pendentes
                        </div>
                    </div>
                    
                    <div style="text-align: center; padding: 20px; border-right: 1px solid var(--border);">
                        <div style="color: #238636; font-size: 1.5rem; font-weight: 800; margin-bottom: 4px;">
                            <?= $dados['stats']['aprovados'] ?>
                        </div>
                        <div style="color: var(--text-muted); font-size: 0.688rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                            Aprovados
                        </div>
                    </div>
                    
                    <div style="text-align: center; padding: 20px;">
                        <div style="color: #f85149; font-size: 1.5rem; font-weight: 800; margin-bottom: 4px;">
                            <?= $dados['stats']['rejeitados'] ?>
                        </div>
                        <div style="color: var(--text-muted); font-size: 0.688rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                            Rejeitados
                        </div>
                    </div>
                    
                </div>
            </div>

            <!-- AÇÕES -->
            <div class="card-footer" style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button class="btn btn-primary" style="flex: 1; min-width: 130px;" onclick="verEmpresas('<?= $tipo ?>')">
                    <i class="fa-solid fa-eye"></i>
                    Ver Empresas
                </button>
                
                <button class="btn btn-secondary" style="flex: 1; min-width: 130px;" onclick="filtrarPendentes('<?= $tipo ?>')">
                    <i class="fa-solid fa-filter"></i>
                    Pendentes
                </button>
                
                <button class="btn btn-ghost" style="flex: 1; min-width: 130px;" onclick="gerarRelatorioEmpresas('<?= $tipo ?>')">
                    <i class="fa-solid fa-file-download"></i>
                    Relatório
                </button>
            </div>
        </div>
        <?php endforeach; ?>

    </div>
</div>

<style>
@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.7; transform: scale(1.05); }
}
</style>

<script>
(function() {
    'use strict';
    
    // Tab switching
    window.switchTab = function(tab) {
        // Remove active from all tabs
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.style.display = 'none');
        
        // Add active to clicked tab
        event.target.closest('.tab-btn').classList.add('active');
        document.getElementById('tab-' + tab).style.display = 'block';
    };

    // Usuários
    window.verUsuarios = function(tipo) {
        if (tipo === 'person') {
            loadContent('modules/usuarios/usuarios?tipo=person');
        } else if (tipo === 'admin') {
            loadContent('modules/usuarios/usuarios?tipo=admin');
        } else if (tipo === 'superadmin') {
            loadContent('modules/usuarios/usuarios?tipo=superadmin');
        }
    };

    window.filtrarOnline = function(tipo) {
        loadContent('modules/usuarios/usuarios?tipo=' + tipo + '&sessao=online');
    };

    window.gerarRelatorioUsuarios = function(tipo) {
        alert('🚧 Relatório de ' + tipo.toUpperCase() + ' em desenvolvimento');
    };

    // Empresas
    window.verEmpresas = function(tipo) {
        loadContent('modules/tabelas/lista-empresas?tipo=' + tipo);
    };

    window.filtrarPendentes = function(tipo) {
        loadContent('modules/dashboard/pendencias?tipo=' + tipo);
    };

    window.gerarRelatorioEmpresas = function(tipo) {
        alert('🚧 Relatório de ' + tipo + ' em desenvolvimento');
    };
    
    console.log('✅ Plataformas carregadas com sucesso');
    <?php if (!$isSuperAdmin): ?>
    console.log('ℹ️ Modo Admin: Plataforma SuperAdmin oculta');
    <?php else: ?>
    console.log('👑 Modo SuperAdmin: Todas as plataformas visíveis');
    <?php endif; ?>
})();
</script>