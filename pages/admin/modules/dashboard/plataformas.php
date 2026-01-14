<?php
/**
 * ================================================================================
 * VISIONGREEN DASHBOARD - PLATAFORMAS EMPRESARIAIS
 * Módulo: modules/dashboard/plataformas.php
 * Descrição: Gestão por tipo de empresa (MEI, LTDA, SA, EIRELI, SLU)
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

/* ================= TIPOS DE EMPRESAS DISPONÍVEIS ================= */
$tipos_empresa = [
    'MEI' => [
        'nome' => 'MEI',
        'nome_completo' => 'Microempreendedor Individual',
        'icone' => 'fa-user-tie',
        'cor' => '#238636',
        'descricao' => 'Pequenos empreendedores que faturam até R$ 81 mil por ano'
    ],
    'LTDA' => [
        'nome' => 'LTDA',
        'nome_completo' => 'Sociedade Limitada',
        'icone' => 'fa-building',
        'cor' => '#388bfd',
        'descricao' => 'Empresas de pequeno e médio porte com sócios'
    ],
    'SA' => [
        'nome' => 'SA',
        'nome_completo' => 'Sociedade Anônima',
        'icone' => 'fa-landmark',
        'cor' => '#d29922',
        'descricao' => 'Grandes empresas de capital aberto ou fechado'
    ],
    'EIRELI' => [
        'nome' => 'EIRELI',
        'nome_completo' => 'Empresa Individual de Responsabilidade Limitada',
        'icone' => 'fa-user-shield',
        'cor' => '#a371f7',
        'descricao' => 'Empresa individual com responsabilidade limitada'
    ],
    'SLU' => [
        'nome' => 'SLU',
        'nome_completo' => 'Sociedade Limitada Unipessoal',
        'icone' => 'fa-briefcase',
        'cor' => '#3fb950',
        'descricao' => 'Sociedade com um único sócio'
    ]
];

/* ================= BUSCAR ESTATÍSTICAS PARA CADA TIPO ================= */
foreach ($tipos_empresa as $tipo => &$dados) {
    $stmt = $mysqli->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status_documentos = 'pendente' THEN 1 ELSE 0 END) as pendentes,
            SUM(CASE WHEN status_documentos = 'aprovado' THEN 1 ELSE 0 END) as aprovados,
            SUM(CASE WHEN status_documentos = 'rejeitado' THEN 1 ELSE 0 END) as rejeitados
        FROM businesses 
        WHERE business_type = ?
    ");
    
    $stmt->bind_param("s", $tipo);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    
    $dados['stats'] = $stats;
    $dados['taxa_aprovacao'] = $stats['total'] > 0 ? round(($stats['aprovados'] / $stats['total']) * 100, 1) : 0;
}

/* ================= ESTATÍSTICAS GERAIS ================= */
$total_empresas = $mysqli->query("SELECT COUNT(*) as total FROM businesses")->fetch_assoc()['total'];
$total_ativas = $mysqli->query("SELECT COUNT(*) as total FROM businesses WHERE status_documentos = 'aprovado'")->fetch_assoc()['total'];
$total_tipos = count($tipos_empresa);
?>

<!-- HEADER -->
<div style="margin-bottom: 32px;">
    <h1 style="color: var(--text-title); font-size: 2rem; font-weight: 800; margin: 0 0 8px 0;">
        <i class="fa-solid fa-layer-group" style="color: var(--accent);"></i>
        Plataformas Empresariais
    </h1>
    <p style="color: var(--text-secondary); font-size: 0.938rem; display: flex; align-items: center; gap: 8px;">
        <i class="fa-solid fa-circle" style="color: var(--accent); font-size: 0.5rem;"></i>
        Gestão por tipo de empresa
    </p>
</div>

<!-- OVERVIEW KPIs -->
<div class="stats-grid mb-3">
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fa-solid fa-building"></i>
        </div>
        <div class="stat-label">Total de Empresas</div>
        <div class="stat-value"><?= number_format($total_empresas, 0, ',', '.') ?></div>
        <div class="stat-change neutral">
            <i class="fa-solid fa-layer-group"></i>
            Todos os tipos
        </div>
    </div>
    
    <div class="stat-card" onclick="loadContent('modules/tabelas/lista-empresas?status=aprovado')">
        <div class="stat-icon">
            <i class="fa-solid fa-check-circle"></i>
        </div>
        <div class="stat-label">Empresas Ativas</div>
        <div class="stat-value"><?= number_format($total_ativas, 0, ',', '.') ?></div>
        <div class="stat-change positive">
            <i class="fa-solid fa-arrow-up"></i>
            Aprovadas
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fa-solid fa-shapes"></i>
        </div>
        <div class="stat-label">Tipos Disponíveis</div>
        <div class="stat-value"><?= $total_tipos ?></div>
        <div class="stat-change neutral">
            <i class="fa-solid fa-chart-pie"></i>
            Categorias
        </div>
    </div>
</div>

<!-- GRID DE PLATAFORMAS -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); gap: 24px;">
    
    <?php foreach ($tipos_empresa as $tipo => $dados): ?>
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
            
            <button class="btn btn-ghost" style="flex: 1; min-width: 130px;" onclick="gerarRelatorio('<?= $tipo ?>')">
                <i class="fa-solid fa-file-download"></i>
                Relatório
            </button>
        </div>
    </div>
    <?php endforeach; ?>

</div>

<!-- EMPTY STATE (se não houver empresas) -->
<?php if ($total_empresas === 0): ?>
<div class="empty-state" style="margin-top: 40px;">
    <div class="empty-icon">
        <i class="fa-solid fa-building-circle-xmark"></i>
    </div>
    <div class="empty-title">Nenhuma empresa cadastrada</div>
    <div class="empty-description">Aguardando primeiros cadastros no sistema</div>
</div>
<?php endif; ?>

<script>
(function() {
    'use strict';
    
    window.verEmpresas = function(tipo) {
        loadContent('modules/tabelas/lista-empresas?tipo=' + tipo);
    };

    window.filtrarPendentes = function(tipo) {
        loadContent('modules/dashboard/pendencias?tipo=' + tipo);
    };

    window.gerarRelatorio = function(tipo) {
        window.open('modules/tabelas/relatorio.php?tipo=' + tipo, '_blank');
    };
    
    console.log('✅ Plataformas carregadas com sucesso');
})();
</script>