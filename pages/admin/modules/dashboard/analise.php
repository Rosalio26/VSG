<?php
/**
 * ================================================================================
 * VISIONGREEN DASHBOARD - CENTRO DE ANÁLISES
 * Módulo: modules/dashboard/analise.php
 * Descrição: Dashboard de análises + Visualização detalhada de empresas
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

$uploadPath = "../../../../registration/uploads/business/";
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

        <!-- DOCUMENTOS -->
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
                    
                    <?php 
                    $ext = strtolower(pathinfo($empresa['license_path'], PATHINFO_EXTENSION));
                    $filePath = $uploadPath . $empresa['license_path'];
                    ?>
                    
                    <?php if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                        <img src="<?= $filePath ?>" alt="Alvará" style="max-width: 100%; max-height: 500px; border-radius: 8px; margin-bottom: 16px;">
                    <?php elseif ($ext === 'pdf'): ?>
                        <iframe src="<?= $filePath ?>" frameborder="0" style="width: 100%; height: 500px; border: 1px solid var(--border); border-radius: 8px; margin-bottom: 16px;"></iframe>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px;">
                            <i class="fa-solid fa-file-pdf" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 16px;"></i>
                            <p style="color: var(--text-secondary); margin-bottom: 16px;">Documento anexado</p>
                        </div>
                    <?php endif; ?>
                    
                    <a href="<?= $filePath ?>" target="_blank" class="btn btn-primary" download>
                        <i class="fa-solid fa-download"></i>
                        Baixar Documento
                    </a>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fa-solid fa-file-slash"></i>
                    </div>
                    <div class="empty-title">Nenhum documento anexado</div>
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

<script>
(function() {
    'use strict';
    
    const userId = <?= $userId ?>;

    window.mostrarRejeicao = function() {
        document.getElementById('motivoGroup').style.display = 'block';
        document.getElementById('btnConfirmarRejeicao').style.display = 'block';
        document.getElementById('motivoRejeicao').focus();
    };

    window.aprovarEmpresa = function() {
        if (!confirm('✅ Tem certeza que deseja APROVAR esta empresa?')) return;
        
        const formData = new FormData();
        formData.append('ajax_action', 'aprovar');
        formData.append('user_id', userId);
        
        fetch(window.location.href.split('?')[0] + '?id=' + userId, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                loadContent('modules/dashboard/analise');
            }
        })
        .catch(err => {
            console.error(err);
            alert('❌ Erro ao processar requisição');
        });
    };

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
        
        fetch(window.location.href.split('?')[0] + '?id=' + userId, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                loadContent('modules/dashboard/analise');
            }
        })
        .catch(err => {
            console.error(err);
            alert('❌ Erro ao processar requisição');
        });
    };
    
    console.log('✅ Análise de empresa carregada - ID:', userId);
})();
</script>