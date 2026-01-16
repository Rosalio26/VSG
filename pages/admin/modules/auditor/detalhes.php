<?php
/**
 * ================================================================================
 * VISIONGREEN - DETALHES DO AUDITOR
 * Módulo: modules/auditor/detalhes.php
 * Descrição: Página de detalhes do auditor com design do dashboard
 * ================================================================================
 */

if (!defined('IS_ADMIN_PAGE')) {
    require_once '../../../../registration/includes/db.php';
    session_start();
}

$adminId = $_SESSION['auth']['user_id'] ?? 0;
$adminRole = $_SESSION['auth']['role'] ?? 'admin';

if ($adminRole !== 'superadmin') {
    echo '<div class="alert error">
            <i class="fa-solid fa-lock"></i>
            <div><strong>Erro:</strong> Acesso restrito apenas para Superadministradores.</div>
          </div>';
    exit;
}

// Obter ID da URL
$auditorId = (int)($_GET['id'] ?? 0);

if (!$auditorId) {
    echo '<div class="alert error">
            <i class="fa-solid fa-exclamation-triangle"></i>
            <div><strong>Erro:</strong> ID do auditor inválido.</div>
          </div>';
    exit;
}

// Buscar auditor
$sql = "SELECT 
            id, 
            public_id, 
            nome, 
            email, 
            email_corporativo, 
            role, 
            status, 
            created_at, 
            updated_at, 
            last_activity, 
            password_changed_at 
        FROM users 
        WHERE id = ? 
        AND type = 'admin' 
        AND role IN ('admin', 'superadmin') 
        AND deleted_at IS NULL";

$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    echo '<div class="alert error">
            <i class="fa-solid fa-exclamation-triangle"></i>
            <div><strong>Erro:</strong> Erro ao preparar consulta.</div>
          </div>';
    exit;
}

$stmt->bind_param("i", $auditorId);
$stmt->execute();
$result = $stmt->get_result();
$auditor = $result->fetch_assoc();

if (!$auditor) {
    echo '<div class="alert error">
            <i class="fa-solid fa-exclamation-triangle"></i>
            <div><strong>Erro:</strong> Auditor não encontrado.</div>
          </div>';
    exit;
}

// Calcular status online
$isOnline = false;
$lastSeenText = "Offline";
if (!empty($auditor['last_activity'])) {
    $timeDiff = time() - strtotime($auditor['last_activity']);
    if ($timeDiff <= 300) { 
        $isOnline = true; 
    } else {
        $minutos = round($timeDiff / 60);
        if ($minutos < 60) {
            $lastSeenText = "Visto há $minutos min";
        } else {
            $horas = round($minutos / 60);
            $lastSeenText = "Visto há " . $horas . "h";
        }
    }
}

// Buscar histórico de auditoria
$historico = [];
$sql_hist = "
    SELECT 
        al.action,
        al.created_at,
        al.ip_address,
        u.nome as admin_nome
    FROM admin_audit_logs al
    LEFT JOIN users u ON al.admin_id = u.id
    WHERE al.action LIKE CONCAT('%AUDITOR_', ?, '%')
    ORDER BY al.created_at DESC
    LIMIT 20
";

$stmt_hist = $mysqli->prepare($sql_hist);
if ($stmt_hist) {
    $stmt_hist->bind_param("i", $auditorId);
    $stmt_hist->execute();
    $result_hist = $stmt_hist->get_result();
    while ($row = $result_hist->fetch_assoc()) {
        $historico[] = $row;
    }
}

// Calcular dias desde registro
$diasRegistro = (time() - strtotime($auditor['created_at'])) / 86400;
?>

<!-- DETAIL HEADER -->
<div class="detail-header">
    <div class="detail-info">
        <h1 class="detail-title">
            <i class="fa-solid fa-user-shield"></i>
            <?= htmlspecialchars($auditor['nome']) ?>
        </h1>
        <div class="detail-subtitle">
            <span class="badge <?= $isOnline ? 'success' : 'neutral' ?>">
                <i class="fa-solid fa-circle" style="font-size: 0.5rem;"></i>
                <?= $isOnline ? 'ONLINE AGORA' : $lastSeenText ?>
            </span>
            • Cadastro há <?= round($diasRegistro) ?> dias
        </div>
    </div>
    
    <div class="detail-actions">
        <button class="btn btn-ghost" onclick="loadContent('modules/auditor/auditor-lista')">
            <i class="fa-solid fa-arrow-left"></i>
            Voltar
        </button>
    </div>
</div>

<!-- DETAIL GRID -->
<div class="detail-grid">
    
    <!-- COLUNA ESQUERDA -->
    <div>
        
        <!-- INFORMAÇÕES BÁSICAS -->
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa-solid fa-circle-info"></i>
                    Informações Básicas
                </h3>
            </div>
            <div class="card-body">
                <ul class="info-list">
                    <li>
                        <span class="info-label">UID (Identificador Único)</span>
                        <code style="background: rgba(35,134,54,0.1); color: var(--accent); padding: 4px 8px; border-radius: 4px; font-weight: 700;">
                            <?= htmlspecialchars($auditor['public_id']) ?>
                        </code>
                    </li>
                    <li>
                        <span class="info-label">Email Pessoal</span>
                        <a href="mailto:<?= htmlspecialchars($auditor['email']) ?>" style="color: var(--accent); text-decoration: none;">
                            <?= htmlspecialchars($auditor['email']) ?>
                        </a>
                    </li>
                    <li>
                        <span class="info-label">Email Corporativo</span>
                        <a href="mailto:<?= htmlspecialchars($auditor['email_corporativo']) ?>" style="color: var(--accent); text-decoration: none;">
                            <?= htmlspecialchars($auditor['email_corporativo']) ?>
                        </a>
                    </li>
                    <li>
                        <span class="info-label">Cargo</span>
                        <span class="badge <?= $auditor['role'] == 'superadmin' ? 'warning' : 'info' ?>">
                            <?= strtoupper($auditor['role']) ?>
                        </span>
                    </li>
                    <li>
                        <span class="info-label">Status</span>
                        <span class="badge <?= $auditor['status'] == 'active' ? 'success' : 'error' ?>">
                            <?= ucfirst($auditor['status']) ?>
                        </span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- DATAS IMPORTANTES -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa-solid fa-calendar"></i>
                    Datas Importantes
                </h3>
            </div>
            <div class="card-body">
                <ul class="info-list">
                    <li>
                        <span class="info-label">Data de Criação</span>
                        <span class="info-value"><?= date('d/m/Y H:i', strtotime($auditor['created_at'])) ?></span>
                    </li>
                    <?php if ($auditor['password_changed_at']): ?>
                    <li>
                        <span class="info-label">Última Mudança de Senha</span>
                        <span class="info-value"><?= date('d/m/Y H:i', strtotime($auditor['password_changed_at'])) ?></span>
                    </li>
                    <?php endif; ?>
                    <li>
                        <span class="info-label">Última Atividade</span>
                        <span class="info-value"><?= $auditor['last_activity'] ? date('d/m/Y H:i', strtotime($auditor['last_activity'])) : 'Nunca' ?></span>
                    </li>
                    <?php if ($auditor['updated_at']): ?>
                    <li>
                        <span class="info-label">Última Atualização</span>
                        <span class="info-value"><?= date('d/m/Y H:i', strtotime($auditor['updated_at'])) ?></span>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- COLUNA DIREITA -->
    <div>
        
        <!-- HISTÓRICO DE AÇÕES -->
        <?php if (!empty($historico)): ?>
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    Histórico de Ações
                </h3>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <?php foreach ($historico as $h): 
                        $timelineClass = 'pending';
                        if (strpos($h['action'], 'CREATE') !== false || strpos($h['action'], 'CREATED') !== false) {
                            $timelineClass = 'completed';
                        } elseif (strpos($h['action'], 'DELETE') !== false || strpos($h['action'], 'DELETED') !== false) {
                            $timelineClass = 'rejected';
                        }
                    ?>
                    <div class="timeline-item <?= $timelineClass ?>">
                        <div class="timeline-content">
                            <div class="timeline-title"><?= htmlspecialchars($h['action']) ?></div>
                            <div class="timeline-meta">
                                <?= htmlspecialchars($h['admin_nome'] ?? 'Sistema') ?> • 
                                <?= date('d/m/Y H:i', strtotime($h['created_at'])) ?> • 
                                <?= htmlspecialchars($h['ip_address'] ?? 'N/A') ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    Histórico de Ações
                </h3>
            </div>
            <div class="card-body">
                <div class="empty-state">
                    <div class="empty-icon"><i class="fa-solid fa-history"></i></div>
                    <div class="empty-title">Nenhuma ação registrada</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- AÇÕES ADMINISTRATIVAS -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa-solid fa-toolbox"></i>
                    Ações Administrativas
                </h3>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <button class="btn btn-primary" onclick="copiarID('<?= $auditor['id'] ?>')" style="justify-content: flex-start;">
                        <i class="fa-solid fa-copy"></i> Copiar ID do Auditor
                    </button>
                    
                    <button class="btn btn-secondary" onclick="copiarUID('<?= htmlspecialchars($auditor['public_id']) ?>')" style="justify-content: flex-start;">
                        <i class="fa-solid fa-copy"></i> Copiar UID
                    </button>
                    
                    <button class="btn btn-ghost" onclick="enviarEmail('<?= htmlspecialchars($auditor['email']) ?>')" style="justify-content: flex-start;">
                        <i class="fa-solid fa-envelope"></i> Enviar Email
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function copiarID(id) {
        navigator.clipboard.writeText(id).then(() => {
            mostrarToast('✅ ID copiado: ' + id, 'success');
        });
    }

    function copiarUID(uid) {
        navigator.clipboard.writeText(uid).then(() => {
            mostrarToast('✅ UID copiado: ' + uid, 'success');
        });
    }

    function enviarEmail(email) {
        window.open('mailto:' + email);
    }

    function mostrarToast(mensagem, tipo = 'info') {
        const toast = document.createElement('div');
        toast.className = 'toast ' + tipo;
        toast.textContent = mensagem;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'fadeOut 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
</script>