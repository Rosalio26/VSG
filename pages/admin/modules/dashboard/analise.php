<?php
/**
 * ================================================================================
 * VISIONGREEN DASHBOARD - CENTRO DE ANÁLISES (VERSÃO FINAL CORRIGIDA)
 * Módulo: modules/dashboard/analise.php
 * 
 * CORREÇÃO PRINCIPAL: Usar URLs web ao invés de caminhos de arquivo
 * ================================================================================
 */

if (!defined('IS_ADMIN_PAGE')) {
    require_once '../../../../registration/includes/db.php';
    session_start();
}

$adminId = $_SESSION['auth']['user_id'] ?? 0;
$adminRole = $_SESSION['auth']['role'] ?? 'admin';
$isSuperAdmin = ($adminRole === 'superadmin');

/* ================= PROCESSAR AÇÕES VIA AJAX ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    $userId = (int)$_POST['user_id'];
    $action = $_POST['ajax_action'];
    
    $mysqli->begin_transaction();
    try {
        if ($action === 'aprovar') {
            $stmt = $mysqli->prepare("UPDATE businesses SET status_documentos = 'aprovado', motivo_rejeicao = NULL, updated_at = NOW() WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            
            $stmt = $mysqli->prepare("INSERT INTO admin_audit_logs (admin_id, action, ip_address, created_at) VALUES (?, ?, ?, NOW())");
            $auditAction = "APROVADO_EMPRESA_$userId";
            $ip = $_SERVER['REMOTE_ADDR'];
            $stmt->bind_param("iss", $adminId, $auditAction, $ip);
            $stmt->execute();
            
            $mysqli->commit();
            echo json_encode(['success' => true, 'message' => '✅ Empresa aprovada com sucesso!']);
            
        } elseif ($action === 'rejeitar') {
            $motivo = $_POST['motivo'] ?? '';
            
            if (strlen(trim($motivo)) < 10) {
                throw new Exception('Motivo deve ter pelo menos 10 caracteres');
            }
            
            $stmt = $mysqli->prepare("UPDATE businesses SET status_documentos = 'rejeitado', motivo_rejeicao = ?, updated_at = NOW() WHERE user_id = ?");
            $stmt->bind_param("si", $motivo, $userId);
            $stmt->execute();
            
            $stmt = $mysqli->prepare("INSERT INTO admin_audit_logs (admin_id, action, ip_address, created_at) VALUES (?, ?, ?, NOW())");
            $auditAction = "REJEITADO_EMPRESA_$userId";
            $ip = $_SERVER['REMOTE_ADDR'];
            $stmt->bind_param("iss", $adminId, $auditAction, $ip);
            $stmt->execute();
            
            $mysqli->commit();
            echo json_encode(['success' => true, 'message' => '❌ Empresa rejeitada.']);
        } else {
            throw new Exception('Ação inválida');
        }
    } catch (Exception $e) {
        $mysqli->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

/* ================= VERIFICAR SE É DASHBOARD OU DETALHES ================= */
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($userId === 0) {
    // ============================================================
    // MODO DASHBOARD: MOSTRA CENTRO DE ANÁLISES
    // ============================================================
    
    /* ================= EMPRESAS PARA ANÁLISE ================= */
    $sql_empresas_analise = "
        SELECT 
            b.user_id,
            u.nome as empresa_nome,
            u.email,
            b.status_documentos,
            b.business_type,
            u.created_at,
            DATEDIFF(NOW(), u.created_at) as dias_registro
        FROM businesses b
        INNER JOIN users u ON b.user_id = u.id
        WHERE b.status_documentos = 'pendente'
        ORDER BY u.created_at ASC
        LIMIT 10
    ";
    $empresas_analise = $mysqli->query($sql_empresas_analise);
    
    /* ================= EMPRESAS JÁ ANALISADAS ================= */
    $sql_empresas_analisadas = "
        SELECT 
            b.user_id,
            u.nome as empresa_nome,
            b.status_documentos,
            b.updated_at
        FROM businesses b
        INNER JOIN users u ON b.user_id = u.id
        WHERE b.status_documentos IN ('aprovado', 'rejeitado')
        ORDER BY b.updated_at DESC
        LIMIT 10
    ";
    $empresas_analisadas = $mysqli->query($sql_empresas_analisadas);
    
    /* ================= USUÁRIOS SUSPEITOS ================= */
    $sql_usuarios_analise = "
        SELECT 
            u.id,
            u.nome,
            u.email,
            u.type,
            u.status,
            u.last_activity,
            u.created_at,
            u.is_in_lockdown,
            CASE 
                WHEN u.is_in_lockdown = 1 THEN 'Bloqueado'
                WHEN u.last_activity < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY)) THEN 'Inativo 30+ dias'
                WHEN u.email_verified_at IS NULL THEN 'Email não verificado'
                ELSE 'Normal'
            END as alerta
        FROM users u
        WHERE u.type IN ('person', 'company')
        AND (
            u.is_in_lockdown = 1 
            OR u.last_activity < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))
            OR u.email_verified_at IS NULL
        )
        ORDER BY u.is_in_lockdown DESC, u.created_at DESC
        LIMIT 10
    ";
    $usuarios_analise = $mysqli->query($sql_usuarios_analise);
    
    /* ================= CONTADORES ================= */
    $count_empresas_pendentes = $mysqli->query("SELECT COUNT(*) as total FROM businesses WHERE status_documentos = 'pendente'")->fetch_assoc()['total'];
    $count_empresas_analisadas_hoje = $mysqli->query("SELECT COUNT(*) as total FROM businesses WHERE status_documentos IN ('aprovado', 'rejeitado') AND DATE(updated_at) = CURDATE()")->fetch_assoc()['total'];
    $count_usuarios_suspeitos = $mysqli->query("SELECT COUNT(*) as total FROM users WHERE type IN ('person', 'company') AND (is_in_lockdown = 1 OR last_activity < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY)) OR email_verified_at IS NULL)")->fetch_assoc()['total'];
?>

<!-- HEADER -->
<div style="margin-bottom: 32px;">
    <h1 style="color: var(--text-title); font-size: 2rem; font-weight: 800; margin: 0 0 8px 0;">
        <i class="fa-solid fa-magnifying-glass-chart" style="color: var(--accent);"></i>
        Centro de Análises
    </h1>
    <p style="color: var(--text-secondary); font-size: 0.938rem;">
        Análise de empresas, usuários e comportamentos suspeitos
    </p>
</div>

<!-- KPI CARDS -->
<div class="stats-grid" style="margin-bottom: 32px;">
    <div class="stat-card" onclick="loadContent('modules/dashboard/pendencias')">
        <div class="stat-icon">
            <i class="fa-solid fa-hourglass-half"></i>
        </div>
        <div class="stat-label">Empresas Pendentes</div>
        <div class="stat-value"><?= $count_empresas_pendentes ?></div>
        <div class="stat-change neutral">
            <i class="fa-solid fa-clock"></i>
            Aguardando análise
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fa-solid fa-check-circle"></i>
        </div>
        <div class="stat-label">Analisadas Hoje</div>
        <div class="stat-value"><?= $count_empresas_analisadas_hoje ?></div>
        <div class="stat-change positive">
            <i class="fa-solid fa-calendar-check"></i>
            Hoje
        </div>
    </div>
    
    <div class="stat-card" onclick="loadContent('modules/usuarios/usuarios')">
        <div class="stat-icon">
            <i class="fa-solid fa-user-slash"></i>
        </div>
        <div class="stat-label">Usuários Suspeitos</div>
        <div class="stat-value"><?= $count_usuarios_suspeitos ?></div>
        <div class="stat-change <?= $count_usuarios_suspeitos > 0 ? 'negative' : 'positive' ?>">
            <i class="fa-solid fa-triangle-exclamation"></i>
            Requer atenção
        </div>
    </div>
</div>

<!-- EMPRESAS PARA ANALISAR -->
<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fa-solid fa-building"></i>
            Empresas Para Analisar
        </h3>
    </div>
    
    <?php if ($empresas_analise && $empresas_analise->num_rows > 0): ?>
    <div class="card-body" style="padding: 0;">
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Empresa</th>
                        <th>Tipo</th>
                        <th>Status</th>
                        <th>Tempo Pendente</th>
                        <th style="text-align: center;">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($emp = $empresas_analise->fetch_assoc()): ?>
                    <tr style="cursor: pointer;" onclick="loadContent('modules/dashboard/analise?id=<?= $emp['user_id'] ?>')">
                        <td>
                            <strong style="color: var(--text-primary);"><?= htmlspecialchars($emp['empresa_nome']) ?></strong><br>
                            <small style="color: var(--text-muted); font-size: 0.75rem;"><?= htmlspecialchars($emp['email']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($emp['business_type'] ?? 'N/A') ?></td>
                        <td><span class="badge warning">Pendente</span></td>
                        <td>
                            <span class="badge <?= $emp['dias_registro'] > 5 ? 'error' : 'neutral' ?>">
                                <?= $emp['dias_registro'] ?> dias
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <button class="btn btn-primary btn-sm" onclick="event.stopPropagation(); loadContent('modules/dashboard/analise?id=<?= $emp['user_id'] ?>')">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                Analisar
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="card-body">
        <div class="empty-state">
            <div class="empty-icon">
                <i class="fa-solid fa-check-double"></i>
            </div>
            <div class="empty-title">Nenhuma empresa pendente</div>
            <div class="empty-description">Todas as empresas foram analisadas</div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- EMPRESAS JÁ ANALISADAS -->
<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fa-solid fa-clipboard-check"></i>
            Empresas Analisadas Recentemente
        </h3>
    </div>
    
    <?php if ($empresas_analisadas && $empresas_analisadas->num_rows > 0): ?>
    <div class="card-body" style="padding: 0;">
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Empresa</th>
                        <th>Decisão</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($emp_analisada = $empresas_analisadas->fetch_assoc()): ?>
                    <tr style="cursor: pointer;" onclick="loadContent('modules/dashboard/analise?id=<?= $emp_analisada['user_id'] ?>')">
                        <td><strong style="color: var(--text-primary);"><?= htmlspecialchars($emp_analisada['empresa_nome']) ?></strong></td>
                        <td>
                            <?php
                                $statusClass = $emp_analisada['status_documentos'] === 'aprovado' ? 'success' : 'error';
                            ?>
                            <span class="badge <?= $statusClass ?>"><?= ucfirst($emp_analisada['status_documentos']) ?></span>
                        </td>
                        <td style="color: var(--text-secondary);"><?= date('d/m/Y H:i', strtotime($emp_analisada['updated_at'])) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="card-body">
        <div class="empty-state">
            <div class="empty-icon">
                <i class="fa-solid fa-inbox"></i>
            </div>
            <div class="empty-title">Nenhuma análise recente</div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- USUÁRIOS SUSPEITOS -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fa-solid fa-user-xmark"></i>
            Usuários Requerendo Análise
        </h3>
    </div>
    
    <?php if ($usuarios_analise && $usuarios_analise->num_rows > 0): ?>
    <div class="card-body" style="padding: 0;">
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Usuário</th>
                        <th>Tipo</th>
                        <th>Alerta</th>
                        <th style="text-align: center;">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($user = $usuarios_analise->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <strong style="color: var(--text-primary);"><?= htmlspecialchars($user['nome']) ?></strong><br>
                            <small style="color: var(--text-muted); font-size: 0.75rem;"><?= htmlspecialchars($user['email']) ?></small>
                        </td>
                        <td><?= ucfirst($user['type']) ?></td>
                        <td>
                            <span class="badge <?= $user['is_in_lockdown'] ? 'error' : 'warning' ?>">
                                <?= htmlspecialchars($user['alerta']) ?>
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <button class="btn btn-ghost btn-sm" onclick="window.open('modules/dashboard/detalhes?type=usuario&id=<?= $user['id'] ?>', '_blank')">
                                <i class="fa-solid fa-user-check"></i>
                                Verificar
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="card-body">
        <div class="empty-state">
            <div class="empty-icon">
                <i class="fa-solid fa-shield-check"></i>
            </div>
            <div class="empty-title">Nenhum usuário suspeito</div>
            <div class="empty-description">Sistema operando normalmente</div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
    exit; // Finaliza o modo dashboard
}

// ============================================================
// MODO DETALHES: MOSTRA EMPRESA ESPECÍFICA
// ============================================================

/* ================= BUSCAR DADOS DA EMPRESA ================= */
$sql = "
    SELECT 
        b.*,
        u.nome as empresa_nome,
        u.email,
        u.telefone,
        u.created_at as registro_em,
        DATEDIFF(NOW(), u.created_at) as dias_registro
    FROM businesses b
    INNER JOIN users u ON b.user_id = u.id
    WHERE b.user_id = ?
";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$empresa = $stmt->get_result()->fetch_assoc();

if (!$empresa) {
    ?>
    <div class="alert error">
        <i class="fa-solid fa-building-slash"></i>
        <div>
            <strong>Empresa não encontrada</strong><br>
            O ID fornecido não corresponde a nenhuma empresa
        </div>
    </div>
    <button onclick="loadContent('modules/dashboard/pendencias')" class="btn btn-primary" style="margin-top: 16px;">
        <i class="fa-solid fa-arrow-left"></i>
        Voltar para Pendências
    </button>
    <?php
    exit;
}

/* ================= CONFIGURAÇÃO DE CAMINHOS ================= */
// CRÍTICO: Usar URL web, não caminho de arquivo do sistema
// A pasta registration está na raiz do site, não dentro de pages/admin

// Caminho físico (para verificar se arquivo existe)
$uploadPathFisico = "../../../../registration/uploads/business/";

// URL web (para exibir no navegador) - ESTA É A CORREÇÃO PRINCIPAL
$uploadPathWeb = "/vsg/registration/uploads/business/";

// Verificar se existe e pegar informações do arquivo
$fileExists = false;
$fileSize = 0;
$ext = '';

if (!empty($empresa['license_path'])) {
    $caminhoFisico = $uploadPathFisico . $empresa['license_path'];
    $fileExists = file_exists($caminhoFisico);
    
    if ($fileExists) {
        $fileSize = filesize($caminhoFisico);
        $ext = strtolower(pathinfo($empresa['license_path'], PATHINFO_EXTENSION));
    }
}

// URL completa para o navegador acessar
$fileURL = $uploadPathWeb . ($empresa['license_path'] ?? '');
?>

<!-- HEADER -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; padding-bottom: 20px; border-bottom: 1px solid var(--border);">
    <div style="display: flex; align-items: center; gap: 20px;">
        <button onclick="loadContent('modules/dashboard/analise')" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left"></i>
            Voltar
        </button>
        <h1 style="color: var(--text-title); font-size: 2rem; font-weight: 800; margin: 0;">
            Análise de Documentos
        </h1>
    </div>
    <?php
        $statusClass = [
            'aprovado' => 'success',
            'pendente' => 'warning',
            'rejeitado' => 'error'
        ][$empresa['status_documentos']] ?? 'neutral';
    ?>
    <span class="badge <?= $statusClass ?>" style="font-size: 1rem; padding: 8px 16px;">
        <?= ucfirst($empresa['status_documentos']) ?>
    </span>
</div>

<!-- GRID PRINCIPAL -->
<div class="detail-grid">
    
    <!-- COLUNA ESQUERDA -->
    <div>
        <!-- INFORMAÇÕES DA EMPRESA -->
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa-solid fa-building"></i>
                    Informações da Empresa
                </h3>
            </div>
            <div class="card-body">
                <ul class="info-list">
                    <li>
                        <span class="info-label">Nome da Empresa</span>
                        <span class="info-value"><?= htmlspecialchars($empresa['empresa_nome']) ?></span>
                    </li>
                    <li>
                        <span class="info-label">Email</span>
                        <span class="info-value">
                            <a href="mailto:<?= htmlspecialchars($empresa['email']) ?>" style="color: var(--accent);">
                                <?= htmlspecialchars($empresa['email']) ?>
                            </a>
                        </span>
                    </li>
                    <li>
                        <span class="info-label">Telefone</span>
                        <span class="info-value">
                            <a href="tel:<?= htmlspecialchars($empresa['telefone']) ?>" style="color: var(--accent);">
                                <?= htmlspecialchars($empresa['telefone']) ?>
                            </a>
                        </span>
                    </li>
                    <li>
                        <span class="info-label">Tax ID (NUIT)</span>
                        <span class="info-value">
                            <code style="font-family: 'Courier New', monospace; background: var(--bg-elevated); padding: 4px 8px; border-radius: 4px;">
                                <?= htmlspecialchars($empresa['tax_id'] ?? 'N/A') ?>
                            </code>
                        </span>
                    </li>
                    <li>
                        <span class="info-label">Tipo de Negócio</span>
                        <span class="info-value"><?= htmlspecialchars($empresa['business_type'] ?? 'N/A') ?></span>
                    </li>
                    <li>
                        <span class="info-label">País</span>
                        <span class="info-value"><?= htmlspecialchars($empresa['country'] ?? 'N/A') ?></span>
                    </li>
                    <li>
                        <span class="info-label">Região</span>
                        <span class="info-value"><?= htmlspecialchars($empresa['region'] ?? 'N/A') ?></span>
                    </li>
                    <li>
                        <span class="info-label">Cidade</span>
                        <span class="info-value"><?= htmlspecialchars($empresa['city'] ?? 'N/A') ?></span>
                    </li>
                    <li>
                        <span class="info-label">Data de Registro</span>
                        <span class="info-value"><?= date('d/m/Y H:i', strtotime($empresa['registro_em'])) ?></span>
                    </li>
                </ul>
                
                <?php if (!empty($empresa['description'])): ?>
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border);">
                    <div class="info-label">Descrição do Negócio</div>
                    <p style="color: var(--text-secondary); margin-top: 8px; line-height: 1.6;">
                        <?= nl2br(htmlspecialchars($empresa['description'])) ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- DOCUMENTOS COM PREVIEW -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa-solid fa-file-lines"></i>
                    Documentos Anexados
                </h3>
            </div>
            <div class="card-body">
                <?php if (!empty($empresa['license_path'])): ?>
                <div class="document-preview" style="margin-bottom: 20px;">
                    <h4 style="color: var(--text-primary); margin-bottom: 16px; font-size: 0.938rem;">
                        <i class="fa-solid fa-file-contract"></i>
                        Alvará Comercial / Licença
                    </h4>
                    
                    <!-- DEBUG INFO (pode remover em produção) -->
                    <div style="background: var(--bg-elevated); padding: 12px; border-radius: 6px; margin-bottom: 16px; font-size: 0.813rem; font-family: monospace;">
                        <strong>🔍 Debug Info:</strong><br>
                        Arquivo BD: <code><?= htmlspecialchars($empresa['license_path']) ?></code><br>
                        Caminho Físico: <code><?= htmlspecialchars($caminhoFisico ?? 'N/A') ?></code><br>
                        URL Web: <code><?= htmlspecialchars($fileURL) ?></code><br>
                        Existe no servidor: <?= $fileExists ? '<span style="color: green;">✅ SIM</span>' : '<span style="color: red;">❌ NÃO</span>' ?><br>
                        <?php if ($fileExists): ?>
                        Tamanho: <?= number_format($fileSize / 1024, 2) ?> KB<br>
                        Extensão: <?= strtoupper($ext) ?>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($fileExists): ?>
                        <?php if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                            <!-- PREVIEW DE IMAGEM -->
                            <div style="position: relative; border: 1px solid var(--border); border-radius: 8px; overflow: hidden; margin-bottom: 16px; background: var(--bg-elevated);">
                                <img 
                                    src="<?= htmlspecialchars($fileURL) ?>" 
                                    alt="Alvará" 
                                    style="width: 100%; height: auto; display: block; cursor: pointer;"
                                    onclick="abrirModalPreview('<?= htmlspecialchars($fileURL) ?>', 'imagem')"
                                    onerror="mostrarErroImagem(this)"
                                >
                                <div style="position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.7); color: white; padding: 8px 12px; border-radius: 6px; font-size: 0.813rem; pointer-events: none;">
                                    <i class="fa-solid fa-search-plus"></i> Clique para ampliar
                                </div>
                            </div>
                            
                        <?php elseif ($ext === 'pdf'): ?>
                            <!-- PREVIEW DE PDF -->
                            <div style="position: relative; border: 1px solid var(--border); border-radius: 8px; overflow: hidden; margin-bottom: 16px; background: var(--bg-elevated);">
                                <object 
                                    data="<?= htmlspecialchars($fileURL) ?>#toolbar=0&navpanes=0" 
                                    type="application/pdf" 
                                    style="width: 100%; height: 600px; display: block;"
                                >
                                    <embed 
                                        src="<?= htmlspecialchars($fileURL) ?>" 
                                        type="application/pdf" 
                                        style="width: 100%; height: 600px;"
                                    >
                                        <div style="text-align: center; padding: 40px; background: white;">
                                            <i class="fa-solid fa-file-pdf" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 16px;"></i>
                                            <p style="color: var(--text-secondary); margin-bottom: 16px;">
                                                Seu navegador não suporta visualização de PDF inline.
                                            </p>
                                            <a href="<?= htmlspecialchars($fileURL) ?>" target="_blank" class="btn btn-primary">
                                                <i class="fa-solid fa-external-link"></i>
                                                Abrir PDF em Nova Aba
                                            </a>
                                        </div>
                                    </embed>
                                </object>
                                <button 
                                    onclick="abrirModalPreview('<?= htmlspecialchars($fileURL) ?>', 'pdf')" 
                                    class="btn btn-secondary" 
                                    style="position: absolute; top: 10px; right: 10px; z-index: 10;"
                                >
                                    <i class="fa-solid fa-expand"></i> Tela Cheia
                                </button>
                            </div>
                            
                        <?php else: ?>
                            <!-- OUTROS FORMATOS -->
                            <div style="text-align: center; padding: 40px; border: 1px solid var(--border); border-radius: 8px; margin-bottom: 16px; background: var(--bg-elevated);">
                                <i class="fa-solid fa-file" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 16px;"></i>
                                <p style="color: var(--text-secondary); margin-bottom: 8px;">
                                    <strong>Arquivo anexado</strong>
                                </p>
                                <p style="color: var(--text-muted); font-size: 0.875rem;">
                                    Tipo: <?= strtoupper($ext) ?> • Tamanho: <?= number_format($fileSize / 1024, 2) ?> KB
                                </p>
                            </div>
                        <?php endif; ?>
                        
                        <!-- BOTÕES DE AÇÃO -->
                        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                            <?php if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'])): ?>
                            <button 
                                onclick="abrirModalPreview('<?= htmlspecialchars($fileURL) ?>', '<?= in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']) ? 'imagem' : 'pdf' ?>')" 
                                class="btn btn-secondary" 
                                style="flex: 1;"
                            >
                                <i class="fa-solid fa-eye"></i>
                                Ver em Tela Cheia
                            </button>
                            <?php endif; ?>
                            
                            <a 
                                href="<?= htmlspecialchars($fileURL) ?>" 
                                target="_blank" 
                                class="btn btn-primary" 
                                style="flex: 1; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none;"
                            >
                                <i class="fa-solid fa-external-link"></i>
                                Abrir em Nova Aba
                            </a>
                            
                            <a 
                                href="<?= htmlspecialchars($fileURL) ?>" 
                                download 
                                class="btn btn-success" 
                                style="flex: 1; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none;"
                            >
                                <i class="fa-solid fa-download"></i>
                                Baixar Documento
                            </a>
                        </div>
                        
                    <?php else: ?>
                        <!-- ARQUIVO NÃO ENCONTRADO -->
                        <div class="alert error">
                            <i class="fa-solid fa-exclamation-triangle"></i>
                            <div>
                                <strong>⚠️ Arquivo não encontrado no servidor</strong><br>
                                O documento registrado no banco de dados não foi localizado no sistema de arquivos.<br>
                                <small style="font-family: monospace; opacity: 0.8; display: block; margin-top: 8px;">
                                    Caminho esperado: <?= htmlspecialchars($caminhoFisico) ?>
                                </small>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <!-- NENHUM DOCUMENTO -->
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fa-solid fa-file-slash"></i>
                    </div>
                    <div class="empty-title">Nenhum documento anexado</div>
                    <div class="empty-description">Esta empresa não enviou documentos</div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- COLUNA DIREITA -->
    <div>
        <div class="card" style="position: sticky; top: 20px;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa-solid fa-gavel"></i>
                    Decisão de Análise
                </h3>
            </div>
            <div class="card-body">
                <?php if ($empresa['status_documentos'] === 'pendente'): ?>
                    <button onclick="aprovarEmpresa()" class="btn btn-primary" style="width: 100%; margin-bottom: 12px;">
                        <i class="fa-solid fa-check-circle"></i>
                        APROVAR EMPRESA
                    </button>

                    <div style="margin: 15px 0; text-align: center; color: var(--text-secondary); font-size: 0.875rem;">ou</div>

                    <button onclick="mostrarRejeicao()" class="btn btn-danger" style="width: 100%;">
                        <i class="fa-solid fa-times-circle"></i>
                        REJEITAR EMPRESA
                    </button>
                    
                    <div class="form-group" id="motivoGroup" style="display: none; margin-top: 16px;">
                        <label class="form-label">Motivo da Rejeição *</label>
                        <textarea id="motivoRejeicao" class="form-textarea" rows="4" placeholder="Descreva o motivo da rejeição (mínimo 10 caracteres)..."></textarea>
                        <div class="form-hint">Seja claro e objetivo no motivo</div>
                    </div>
                    
                    <button onclick="rejeitarEmpresa()" id="btnConfirmarRejeicao" class="btn btn-danger" style="width: 100%; display: none; margin-top: 12px;">
                        <i class="fa-solid fa-exclamation-triangle"></i>
                        CONFIRMAR REJEIÇÃO
                    </button>
                <?php else: ?>
                    <div class="alert <?= $empresa['status_documentos'] === 'aprovado' ? 'success' : 'error' ?>">
                        <i class="fa-solid fa-circle-info"></i>
                        <div>
                            Esta empresa já foi <strong><?= $empresa['status_documentos'] === 'aprovado' ? 'aprovada' : 'rejeitada' ?></strong>.
                        </div>
                    </div>
                <?php endif; ?>

                <!-- TIMELINE -->
                <div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--border);">
                    <h4 style="color: var(--text-primary); font-size: 0.875rem; font-weight: 600; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        Linha do Tempo
                    </h4>
                    
                    <div class="timeline">
                        <div class="timeline-item pending">
                            <div class="timeline-content">
                                <div class="timeline-title">Registro Inicial</div>
                                <div class="timeline-meta">
                                    <?= date('d/m/Y H:i', strtotime($empresa['registro_em'])) ?> • 
                                    Há <?= $empresa['dias_registro'] ?> dias
                                </div>
                            </div>
                        </div>

                        <?php if ($empresa['updated_at']): ?>
                        <div class="timeline-item <?= $empresa['status_documentos'] === 'aprovado' ? 'completed' : 'rejected' ?>">
                            <div class="timeline-content">
                                <div class="timeline-title">Última Atualização</div>
                                <div class="timeline-meta">
                                    <?= date('d/m/Y H:i', strtotime($empresa['updated_at'])) ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($empresa['status_documentos'] === 'rejeitado' && !empty($empresa['motivo_rejeicao'])): ?>
                <div class="alert error" style="margin-top: 20px;">
                    <i class="fa-solid fa-exclamation-triangle"></i>
                    <div>
                        <strong>Motivo da Rejeição:</strong><br>
                        <?= nl2br(htmlspecialchars($empresa['motivo_rejeicao'])) ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DE PREVIEW EM TELA CHEIA -->
<div id="modalPreview" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 9999; padding: 20px;">
    <div style="position: relative; width: 100%; height: 100%; display: flex; flex-direction: column;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding: 10px;">
            <h3 style="color: white; margin: 0; font-size: 1.25rem;">
                <i class="fa-solid fa-eye"></i> Visualização do Documento
            </h3>
            <button onclick="fecharModalPreview()" class="btn btn-danger" style="z-index: 10;">
                <i class="fa-solid fa-times"></i> Fechar [ESC]
            </button>
        </div>
        <div id="modalPreviewContent" style="flex: 1; display: flex; justify-content: center; align-items: center; overflow: auto;">
            <!-- Conteúdo será inserido aqui via JS -->
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';
    
    const userId = <?= $userId ?>;

    // Mostrar campo de motivo de rejeição
    window.mostrarRejeicao = function() {
        document.getElementById('motivoGroup').style.display = 'block';
        document.getElementById('btnConfirmarRejeicao').style.display = 'block';
        document.getElementById('motivoRejeicao').focus();
    };

    // Aprovar empresa
    window.aprovarEmpresa = function() {
        if (!confirm('✅ Tem certeza que deseja APROVAR esta empresa?')) return;
        
        const formData = new FormData();
        formData.append('ajax_action', 'aprovar');
        formData.append('user_id', userId);
        
        fetch('modules/dashboard/analise.php', {
            method: 'POST',
            body: formData
        })
        .then(res => {
            if (!res.ok) throw new Error('Erro HTTP: ' + res.status);
            return res.json();
        })
        .then(data => {
            if (data.success) {
                alert(data.message);
                loadContent('modules/dashboard/analise');
            } else {
                alert('❌ Erro: ' + data.message);
            }
        })
        .catch(err => {
            console.error('Erro na requisição:', err);
            alert('❌ Erro ao processar requisição. Verifique o console.');
        });
    };

    // Rejeitar empresa
    window.rejeitarEmpresa = function() {
        const motivo = document.getElementById('motivoRejeicao').value.trim();
        
        if (motivo.length < 10) {
            alert('⚠️ Por favor, descreva o motivo com pelo menos 10 caracteres.');
            return;
        }
        
        if (!confirm('❌ Tem certeza que deseja REJEITAR esta empresa?\n\nMotivo: ' + motivo)) return;
        
        const formData = new FormData();
        formData.append('ajax_action', 'rejeitar');
        formData.append('user_id', userId);
        formData.append('motivo', motivo);
        
        fetch('modules/dashboard/analise.php', {
            method: 'POST',
            body: formData
        })
        .then(res => {
            if (!res.ok) throw new Error('Erro HTTP: ' + res.status);
            return res.json();
        })
        .then(data => {
            if (data.success) {
                alert(data.message);
                loadContent('modules/dashboard/analise');
            } else {
                alert('❌ Erro: ' + data.message);
            }
        })
        .catch(err => {
            console.error('Erro na requisição:', err);
            alert('❌ Erro ao processar requisição. Verifique o console.');
        });
    };
    
    // Abrir modal de preview
    window.abrirModalPreview = function(fileurl, tipo) {
        const modal = document.getElementById('modalPreview');
        const content = document.getElementById('modalPreviewContent');
        
        content.innerHTML = '';
        
        if (tipo === 'imagem') {
            const img = document.createElement('img');
            img.src = fileurl;
            img.style.maxWidth = '100%';
            img.style.maxHeight = '100%';
            img.style.objectFit = 'contain';
            img.alt = 'Preview do documento';
            content.appendChild(img);
        } else if (tipo === 'pdf') {
            const iframe = document.createElement('iframe');
            iframe.src = fileurl;
            iframe.style.width = '100%';
            iframe.style.height = '100%';
            iframe.style.border = 'none';
            iframe.style.background = 'white';
            content.appendChild(iframe);
        }
        
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    };
    
    // Fechar modal
    window.fecharModalPreview = function() {
        const modal = document.getElementById('modalPreview');
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    };
    
    // Tratamento de erro de imagem
    window.mostrarErroImagem = function(img) {
        const parent = img.parentElement;
        parent.innerHTML = `
            <div style="text-align: center; padding: 60px; background: var(--bg-elevated);">
                <i class="fa-solid fa-image-slash" style="font-size: 4rem; color: var(--text-muted); margin-bottom: 20px;"></i>
                <h4 style="color: var(--text-primary); margin-bottom: 12px;">Erro ao carregar imagem</h4>
                <p style="color: var(--text-secondary); font-size: 0.875rem; margin-bottom: 20px;">
                    O arquivo existe no servidor mas não pode ser exibido.<br>
                    Possíveis causas: permissões, tipo de arquivo incompatível, ou arquivo corrompido.
                </p>
                <div style="background: #fee; border: 1px solid #fcc; padding: 12px; border-radius: 6px; text-align: left; font-family: monospace; font-size: 0.75rem; margin-top: 16px;">
                    <strong>URL tentada:</strong><br>
                    ${img.src}
                </div>
            </div>
        `;
    };
    
    // Fechar modal com tecla ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            fecharModalPreview();
        }
    });
    
    console.log('✅ Sistema de análise carregado - User ID:', userId);
})();
</script>