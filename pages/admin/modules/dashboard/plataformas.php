<?php
    if (!defined('IS_ADMIN_PAGE')) {
        require_once '../../../../registration/includes/db.php';
        session_start();
    }

    $adminId = $_SESSION['auth']['user_id'] ?? 0;
    $adminRole = $_SESSION['auth']['role'] ?? 'admin';
    $isSuperAdmin = ($adminRole === 'superadmin');

    /* ================= BUSCAR ESTATÍSTICAS POR TIPO DE EMPRESA ================= */

    // Tipos de empresas disponíveis
    $tipos_empresa = [
        'MEI' => [
            'nome' => 'MEI',
            'nome_completo' => 'Microempreendedor Individual',
            'icone' => 'fa-user-tie',
            'cor' => '#00ff88',
            'descricao' => 'Pequenos empreendedores que faturam até R$ 81 mil por ano'
        ],
        'LTDA' => [
            'nome' => 'LTDA',
            'nome_completo' => 'Sociedade Limitada',
            'icone' => 'fa-building',
            'cor' => '#4da3ff',
            'descricao' => 'Empresas de pequeno e médio porte com sócios'
        ],
        'SA' => [
            'nome' => 'SA',
            'nome_completo' => 'Sociedade Anônima',
            'icone' => 'fa-landmark',
            'cor' => '#ff9500',
            'descricao' => 'Grandes empresas de capital aberto ou fechado'
        ],
        'EIRELI' => [
            'nome' => 'EIRELI',
            'nome_completo' => 'Empresa Individual de Responsabilidade Limitada',
            'icone' => 'fa-user-shield',
            'cor' => '#9d4edd',
            'descricao' => 'Empresa individual com responsabilidade limitada'
        ],
        'SLU' => [
            'nome' => 'SLU',
            'nome_completo' => 'Sociedade Limitada Unipessoal',
            'icone' => 'fa-briefcase',
            'cor' => '#06ffa5',
            'descricao' => 'Sociedade com um único sócio'
        ]
    ];

    // Buscar estatísticas para cada tipo
    foreach ($tipos_empresa as $tipo => &$dados) {
        $tipo_escaped = $mysqli->real_escape_string($tipo);
        
        $sql_stats = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status_documentos = 'pendente' THEN 1 ELSE 0 END) as pendentes,
                SUM(CASE WHEN status_documentos = 'aprovado' THEN 1 ELSE 0 END) as aprovados,
                SUM(CASE WHEN status_documentos = 'rejeitado' THEN 1 ELSE 0 END) as rejeitados
            FROM businesses 
            WHERE business_type = '$tipo_escaped'
        ";
        
        $result = $mysqli->query($sql_stats);
        $stats = $result->fetch_assoc();
        
        $dados['stats'] = $stats;
        $dados['taxa_aprovacao'] = $stats['total'] > 0 ? round(($stats['aprovados'] / $stats['total']) * 100, 1) : 0;
    }

    // Estatísticas gerais
    $total_empresas = $mysqli->query("SELECT COUNT(*) as total FROM businesses")->fetch_assoc()['total'];
    $total_ativas = $mysqli->query("SELECT COUNT(*) as total FROM businesses WHERE status_documentos = 'aprovado'")->fetch_assoc()['total'];
?>

<style>
    :root {
        --bg-sidebar: #0b0f0a;
        --bg-body: #050705;
        --bg-card: #121812;
        --text-main: #a0ac9f;
        --text-title: #ffffff;
        --accent-green: #00ff88;
        --accent-emerald: #00a63e;
        --accent-glow: rgba(0, 255, 136, 0.3);
        --border-color: rgba(0, 255, 136, 0.08);
    }

    .plataformas-container {
        padding: 30px;
        background: #0005078a;
        border-radius: 10px;
        min-height: 100vh;
    }

    .plataformas-header {
        margin-bottom: 35px;
    }

    .plataformas-header h1 {
        color: var(--text-title);
        font-size: 2.2rem;
        font-weight: 800;
        margin: 0 0 10px 0;
        text-shadow: 0 0 30px var(--accent-glow);
    }

    .plataformas-subtitle {
        color: var(--text-main);
        font-size: 1rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .overview-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 40px;
    }

    .overview-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 25px;
        text-align: center;
        transition: 0.3s;
    }

    .overview-card:hover {
        border-color: var(--accent-glow);
        box-shadow: 0 5px 20px var(--accent-glow);
    }

    .overview-value {
        color: var(--accent-green);
        font-size: 2.5rem;
        font-weight: 800;
        margin-bottom: 10px;
        text-shadow: 0 0 20px var(--accent-glow);
    }

    .overview-label {
        color: var(--text-main);
        font-size: 0.9rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .apps-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
        gap: 25px;
    }

    .app-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 20px;
        overflow: hidden;
        transition: 0.3s;
        position: relative;
    }

    .app-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 5px;
        background: linear-gradient(90deg, var(--app-color), transparent);
        opacity: 0;
        transition: 0.3s;
    }

    .app-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 50px rgba(0, 255, 136, 0.15);
    }

    .app-card:hover::before {
        opacity: 1;
    }

    .app-header {
        padding: 30px;
        background: linear-gradient(135deg, rgba(0,0,0,0.3), rgba(0,0,0,0.1));
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .app-icon {
        width: 70px;
        height: 70px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        background: rgba(0, 255, 136, 0.1);
        border: 2px solid var(--border-color);
        box-shadow: 0 0 30px rgba(0, 255, 136, 0.2);
        transition: 0.3s;
    }

    .app-card:hover .app-icon {
        transform: scale(1.1) rotate(5deg);
        box-shadow: 0 0 50px var(--app-color);
    }

    .app-info {
        flex: 1;
    }

    .app-name {
        color: var(--text-title);
        font-size: 1.5rem;
        font-weight: 800;
        margin: 0 0 5px 0;
        text-shadow: 0 0 20px var(--accent-glow);
    }

    .app-name-full {
        color: var(--text-main);
        font-size: 0.85rem;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .app-description {
        color: var(--text-main);
        font-size: 0.8rem;
        line-height: 1.4;
    }

    .app-stats {
        padding: 25px 30px;
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 15px;
    }

    .stat-mini {
        text-align: center;
    }

    .stat-mini-value {
        color: var(--text-title);
        font-size: 1.8rem;
        font-weight: 800;
        margin-bottom: 5px;
    }

    .stat-mini-label {
        color: var(--text-main);
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .stat-mini.pendente .stat-mini-value { color: #ff9500; }
    .stat-mini.aprovado .stat-mini-value { color: var(--accent-green); }
    .stat-mini.rejeitado .stat-mini-value { color: #ff4d4d; }

    .app-actions {
        padding: 20px 30px;
        background: rgba(0, 255, 136, 0.02);
        border-top: 1px solid var(--border-color);
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .app-btn {
        flex: 1;
        min-width: 140px;
        padding: 12px 20px;
        border-radius: 12px;
        font-weight: 700;
        font-size: 0.85rem;
        cursor: pointer;
        transition: 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        border: none;
        text-decoration: none;
    }

    .app-btn-primary {
        background: var(--accent-green);
        color: #000;
    }

    .app-btn-primary:hover {
        box-shadow: 0 0 30px var(--accent-glow);
        transform: translateY(-2px);
    }

    .app-btn-secondary {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid var(--border-color);
        color: var(--text-main);
    }

    .app-btn-secondary:hover {
        background: rgba(255, 255, 255, 0.1);
        border-color: var(--accent-green);
        color: var(--accent-green);
    }

    .progress-ring {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: conic-gradient(
            var(--app-color) calc(var(--progress) * 1%), 
            rgba(255,255,255,0.05) 0
        );
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
    }

    .progress-ring::before {
        content: '';
        position: absolute;
        width: 45px;
        height: 45px;
        background: var(--bg-card);
        border-radius: 50%;
    }

    .progress-value {
        position: relative;
        z-index: 1;
        color: var(--text-title);
        font-size: 0.9rem;
        font-weight: 800;
    }

    .empty-state {
        text-align: center;
        padding: 80px 20px;
        color: var(--text-main);
    }

    .empty-state i {
        font-size: 5rem;
        margin-bottom: 20px;
        opacity: 0.2;
        color: var(--accent-green);
    }

    @keyframes float {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-10px); }
    }

    .app-icon {
        animation: float 3s ease-in-out infinite;
    }
</style>

<div class="plataformas-container">
    <!-- HEADER -->
    <div class="plataformas-header">
        <h1>Plataformas Empresariais</h1>
        <div class="plataformas-subtitle">
            <i class="fa-solid fa-circle" style="color: var(--accent-green); font-size: 0.6rem;"></i>
            Gestão por tipo de empresa
        </div>
    </div>

    <!-- OVERVIEW CARDS -->
    <div class="overview-cards">
        <div class="overview-card">
            <div class="overview-value"><?= $total_empresas ?></div>
            <div class="overview-label">Total de Empresas</div>
        </div>
        
        <div class="overview-card">
            <div class="overview-value" style="color: var(--accent-green);"><?= $total_ativas ?></div>
            <div class="overview-label">Empresas Ativas</div>
        </div>
        
        <div class="overview-card">
            <div class="overview-value" style="color: #4da3ff;"><?= count($tipos_empresa) ?></div>
            <div class="overview-label">Tipos Disponíveis</div>
        </div>
    </div>

    <!-- APPS GRID -->
    <div class="apps-grid">
        <?php foreach ($tipos_empresa as $tipo => $dados): ?>
            <div class="app-card" style="--app-color: <?= $dados['cor'] ?>;">
                <!-- HEADER DO APP -->
                <div class="app-header">
                    <div class="app-icon" style="color: <?= $dados['cor'] ?>; border-color: <?= $dados['cor'] ?>;">
                        <i class="fa-solid <?= $dados['icone'] ?>"></i>
                    </div>
                    
                    <div class="app-info">
                        <h2 class="app-name"><?= $dados['nome'] ?></h2>
                        <div class="app-name-full"><?= $dados['nome_completo'] ?></div>
                        <p class="app-description"><?= $dados['descricao'] ?></p>
                    </div>
                    
                    <div class="progress-ring" style="--progress: <?= $dados['taxa_aprovacao'] ?>;">
                        <span class="progress-value"><?= $dados['taxa_aprovacao'] ?>%</span>
                    </div>
                </div>

                <!-- ESTATÍSTICAS -->
                <div class="app-stats">
                    <div class="stat-mini">
                        <div class="stat-mini-value"><?= $dados['stats']['total'] ?></div>
                        <div class="stat-mini-label">Total</div>
                    </div>
                    
                    <div class="stat-mini pendente">
                        <div class="stat-mini-value"><?= $dados['stats']['pendentes'] ?></div>
                        <div class="stat-mini-label">Pendentes</div>
                    </div>
                    
                    <div class="stat-mini aprovado">
                        <div class="stat-mini-value"><?= $dados['stats']['aprovados'] ?></div>
                        <div class="stat-mini-label">Aprovados</div>
                    </div>
                    
                    <div class="stat-mini rejeitado">
                        <div class="stat-mini-value"><?= $dados['stats']['rejeitados'] ?></div>
                        <div class="stat-mini-label">Rejeitados</div>
                    </div>
                </div>

                <!-- AÇÕES -->
                <div class="app-actions">
                    <button class="app-btn app-btn-primary" onclick="verEmpresas('<?= $tipo ?>')">
                        <i class="fa-solid fa-eye"></i>
                        Ver Empresas
                    </button>
                    
                    <button class="app-btn app-btn-secondary" onclick="filtrarPendentes('<?= $tipo ?>')">
                        <i class="fa-solid fa-filter"></i>
                        Filtrar Pendentes
                    </button>
                    
                    <button class="app-btn app-btn-secondary" onclick="gerarRelatorio('<?= $tipo ?>')">
                        <i class="fa-solid fa-file-download"></i>
                        Relatório
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- EMPTY STATE (se não houver empresas) -->
    <?php if ($total_empresas === 0): ?>
        <div class="empty-state">
            <i class="fa-solid fa-building-circle-xmark"></i>
            <h2 style="color: var(--text-title); margin-bottom: 10px;">Nenhuma empresa cadastrada</h2>
            <p>Aguardando primeiros cadastros no sistema</p>
        </div>
    <?php endif; ?>
</div>

<script>
    function verEmpresas(tipo) {
        // Carrega lista de empresas filtrada por tipo
        loadContent('modules/tabelas/lista-empresas?tipo=' + tipo);
    }

    function filtrarPendentes(tipo) {
        // Carrega pendências filtradas por tipo
        loadContent('modules/dashboard/pendencias?tipo=' + tipo);
    }

    function gerarRelatorio(tipo) {
        // Abre relatório em nova janela
        window.open('modules/tabelas/relatorio.php?tipo=' + tipo, '_blank');
    }
</script>