<?php
/**
 * ================================================================================
 * VISIONGREEN DASHBOARD - VISUALIZADOR UNIVERSAL DE DETALHES
 * Módulo: modules/dashboard/detalhes.php
 * Descrição: Sistema universal para visualizar qualquer tipo de registro
 * Suporta: empresas, usuários, documentos, alertas, auditorias, transações, etc.
 * ================================================================================
 */

if (!defined('IS_ADMIN_PAGE')) {
    require_once '../../../../registration/includes/db.php';
    session_start();
}

$adminId = $_SESSION['auth']['user_id'] ?? 0;
$adminRole = $_SESSION['auth']['role'] ?? 'admin';
$adminName = $_SESSION['auth']['nome'] ?? 'Admin';
$isSuperAdmin = ($adminRole === 'superadmin');

/* ================= PARÂMETROS DA URL ================= */
$type = $_GET['type'] ?? 'empresa'; // empresa, usuario, documento, alerta, auditoria, transacao
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    echo '<div class="alert error">
        <i class="fa-solid fa-exclamation-triangle"></i>
        <div><strong>Erro:</strong> ID inválido</div>
    </div>';
    exit;
}

/* ================= CONFIGURAÇÃO DE TIPOS ================= */
$typeConfig = [
    'empresa' => [
        'table' => 'businesses',
        'join' => 'INNER JOIN users u ON businesses.user_id = u.id',
        'fields' => 'businesses.*, u.nome, u.email, u.telefone, u.created_at as user_created_at, u.type as user_type',
        'condition' => 'businesses.id = ?',
        'title_field' => 'nome',
        'icon' => 'fa-building',
        'color' => 'var(--accent)',
        'has_approval' => true,
        'has_documents' => true,
        'status_field' => 'status_documentos'
    ],
    'usuario' => [
        'table' => 'users',
        'join' => 'LEFT JOIN businesses b ON users.id = b.user_id',
        'fields' => 'users.*, b.status_documentos, b.license_path, b.tax_id, b.id as business_id',
        'condition' => 'users.id = ?',
        'title_field' => 'nome',
        'icon' => 'fa-user',
        'color' => '#58a6ff',
        'has_approval' => false,
        'has_documents' => false,
        'status_field' => null
    ],
    'documento' => [
        'table' => 'businesses',
        'join' => 'INNER JOIN users u ON businesses.user_id = u.id',
        'fields' => 'businesses.*, u.nome, u.email, u.telefone, u.created_at as user_created_at',
        'condition' => 'businesses.id = ?',
        'title_field' => 'nome',
        'icon' => 'fa-file-invoice',
        'color' => 'var(--accent)',
        'has_approval' => true,
        'has_documents' => true,
        'status_field' => 'status_documentos'
    ],
    'alerta' => [
        'table' => 'notifications',
        'join' => 'LEFT JOIN users u ON notifications.sender_id = u.id',
        'fields' => 'notifications.*, u.nome as sender_name, u.email as sender_email',
        'condition' => 'notifications.id = ?',
        'title_field' => 'subject',
        'icon' => 'fa-bell',
        'color' => '#f85149',
        'has_approval' => false,
        'has_documents' => false,
        'status_field' => 'status'
    ],
    'auditoria' => [
        'table' => 'admin_audit_logs',
        'join' => 'LEFT JOIN users u ON admin_audit_logs.admin_id = u.id',
        'fields' => 'admin_audit_logs.*, u.nome as admin_nome, u.email as admin_email',
        'condition' => 'admin_audit_logs.id = ?',
        'title_field' => 'action',
        'icon' => 'fa-clipboard-check',
        'color' => '#58a6ff',
        'has_approval' => false,
        'has_documents' => false,
        'status_field' => null
    ],
    'transacao' => [
        'table' => 'transactions',
        'join' => 'LEFT JOIN users u ON transactions.user_id = u.id',
        'fields' => 'transactions.*, u.nome as user_name, u.email as user_email',
        'condition' => 'transactions.id = ?',
        'title_field' => 'invoice_number',
        'icon' => 'fa-money-bill-wave',
        'color' => '#3fb950',
        'has_approval' => false,
        'has_documents' => false,
        'status_field' => 'status'
    ]
];

$config = $typeConfig[$type] ?? $typeConfig['empresa'];

/* ================= BUSCAR DADOS ================= */
$sql = "SELECT {$config['fields']} FROM {$config['table']} {$config['join']} WHERE {$config['condition']}";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if (!$data) {
    echo '<div class="alert error">
        <i class="fa-solid fa-exclamation-triangle"></i>
        <div><strong>Erro:</strong> Registro não encontrado</div>
    </div>';
    exit;
}

/* ================= PROCESSAR AÇÕES (POST) ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $motivo = $_POST['motivo'] ?? '';
    
    $mysqli->begin_transaction();
    try {
        if ($config['has_approval'] && in_array($action, ['approve', 'reject'])) {
            $newStatus = $action === 'approve' ? 'aprovado' : 'rejeitado';
            
            if ($action === 'approve') {
                $stmt = $mysqli->prepare("UPDATE {$config['table']} SET {$config['status_field']} = ?, motivo_rejeicao = NULL, updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("si", $newStatus, $id);
            } else {
                $stmt = $mysqli->prepare("UPDATE {$config['table']} SET {$config['status_field']} = ?, motivo_rejeicao = ?, updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("ssi", $newStatus, $motivo, $id);
            }
            $stmt->execute();
            
            // Log de auditoria
            $auditAction = "AUDIT_" . strtoupper($type) . "_" . strtoupper($action);
            $ip = $_SERVER['REMOTE_ADDR'];
            $stmt = $mysqli->prepare("INSERT INTO admin_audit_logs (admin_id, action, ip_address, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("iss", $adminId, $auditAction, $ip);
            $stmt->execute();
            
            $msg = $action === 'approve' ? "✅ Registro aprovado com sucesso!" : "❌ Registro rejeitado.";
        } elseif ($action === 'delete') {
            // Soft delete
            $stmt = $mysqli->prepare("UPDATE {$config['table']} SET deleted_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            
            $msg = "🗑️ Registro excluído com sucesso!";
        }
        
        $mysqli->commit();
        
        echo "<script>
            alert('$msg');
            window.opener.postMessage({type: 'reload'}, '*');
            setTimeout(() => window.close(), 1000);
        </script>";
        exit;
        
    } catch (Exception $e) {
        $mysqli->rollback();
        echo "<script>alert('❌ Erro: " . addslashes($e->getMessage()) . "');</script>";
    }
}

/* ================= BUSCAR HISTÓRICO ================= */
$historico = [];
$sql_hist = "
    SELECT 
        al.action,
        al.created_at,
        al.ip_address,
        u.nome as admin_nome
    FROM admin_audit_logs al
    LEFT JOIN users u ON al.admin_id = u.id
    WHERE al.action LIKE ?
    ORDER BY al.created_at DESC
    LIMIT 20
";
$searchPattern = "%{$type}%";
$stmt = $mysqli->prepare($sql_hist);
$stmt->bind_param("s", $searchPattern);
$stmt->execute();
$result_hist = $stmt->get_result();
while ($row = $result_hist->fetch_assoc()) {
    $historico[] = $row;
}

/* ================= CALCULAR DIAS ================= */
$diasRegistro = 0;
if (isset($data['created_at'])) {
    $diasRegistro = (time() - strtotime($data['created_at'])) / 86400;
} elseif (isset($data['user_created_at'])) {
    $diasRegistro = (time() - strtotime($data['user_created_at'])) / 86400;
}

/* ================= FUNÇÃO PARA RENDERIZAR CAMPOS ================= */
function renderField($label, $value, $type = 'text') {
    if (empty($value)) return '';
    
    $displayValue = htmlspecialchars($value);
    
    switch ($type) {
        case 'email':
            $displayValue = '<a href="mailto:' . $displayValue . '" style="color: var(--accent);">' . $displayValue . '</a>';
            break;
        case 'phone':
            $displayValue = '<a href="tel:' . $displayValue . '" style="color: var(--accent);">' . $displayValue . '</a>';
            break;
        case 'date':
            $displayValue = date('d/m/Y H:i', strtotime($value));
            break;
        case 'money':
            $displayValue = 'MZN ' . number_format($value, 2, ',', '.');
            break;
        case 'code':
            $displayValue = '<code style="font-family: \'Courier New\', monospace; background: var(--bg-elevated); padding: 4px 8px; border-radius: 4px;">' . $displayValue . '</code>';
            break;
    }
    
    return "
    <li>
        <span class=\"info-label\">$label</span>
        <span class=\"info-value\">$displayValue</span>
    </li>";
}

/* ================= STATUS BADGE ================= */
$statusBadge = '';
if ($config['status_field'] && isset($data[$config['status_field']])) {
    $statusValue = $data[$config['status_field']];
    $statusClass = [
        'aprovado' => 'success',
        'pendente' => 'warning',
        'rejeitado' => 'error',
        'unread' => 'warning',
        'read' => 'neutral',
        'completed' => 'success',
        'failed' => 'error',
        'processing' => 'info'
    ][$statusValue] ?? 'neutral';
    
    $statusBadge = '<span class="badge ' . $statusClass . '">' . ucfirst($statusValue) . '</span>';
}

/* ================= TÍTULO DA PÁGINA ================= */
$pageTitle = $data[$config['title_field']] ?? 'Registro #' . $id;
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - VisionGreen</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard-github-dark.css">
    <link rel="stylesheet" href="../../assets/css/dashboard-components.css">
</head>

<body style="background: var(--bg-page); padding: 24px; min-height: 100vh;">

<!-- HEADER -->
<div class="detail-header">
    <div class="detail-info">
        <h1 class="detail-title">
            <i class="fa-solid <?= $config['icon'] ?>" style="color: <?= $config['color'] ?>;"></i>
            <?= htmlspecialchars($pageTitle) ?>
        </h1>
        <div class="detail-subtitle">
            <?= $statusBadge ?>
            <?php if ($diasRegistro > 0): ?>
                • Criado há <?= round($diasRegistro) ?> dias
            <?php endif; ?>
        </div>
    </div>
    
    <div class="detail-actions">
        <button class="btn btn-ghost" onclick="window.print()" title="Imprimir">
            <i class="fa-solid fa-print"></i>
        </button>
        <button class="btn btn-ghost" onclick="exportToPDF()" title="Exportar PDF">
            <i class="fa-solid fa-file-pdf"></i>
        </button>
        <button class="btn btn-secondary" onclick="window.close()" title="Fechar">
            <i class="fa-solid fa-times"></i>
            Fechar
        </button>
    </div>
</div>

<!-- GRID PRINCIPAL -->
<div class="detail-grid">
    
    <!-- COLUNA ESQUERDA: INFORMAÇÕES -->
    <div>
        <!-- INFORMAÇÕES PRINCIPAIS -->
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa-solid fa-circle-info"></i>
                    Informações Principais
                </h3>
            </div>
            <div class="card-body">
                <ul class="info-list">
                    <?php
                    // Renderizar campos baseado no tipo
                    switch ($type) {
                        case 'empresa':
                        case 'usuario':
                            echo renderField('Nome Completo', $data['nome']);
                            echo renderField('Email', $data['email'], 'email');
                            echo renderField('Telefone', $data['telefone'], 'phone');
                            echo renderField('Tipo de Conta', ucfirst($data['user_type'] ?? $data['type']));
                            echo renderField('NUIT', $data['tax_id'], 'code');
                            echo renderField('Data de Registro', $data['user_created_at'] ?? $data['created_at'], 'date');
                            break;
                            
                        case 'documento':
                            echo renderField('Empresa', $data['nome']);
                            echo renderField('Email', $data['email'], 'email');
                            echo renderField('Status', ucfirst($data['status_documentos']));
                            echo renderField('NUIT', $data['tax_id'], 'code');
                            echo renderField('Enviado em', $data['user_created_at'], 'date');
                            break;
                            
                        case 'alerta':
                            echo renderField('Assunto', $data['subject']);
                            echo renderField('Categoria', strtoupper($data['category']));
                            echo renderField('Prioridade', ucfirst($data['priority']));
                            echo renderField('Remetente', $data['sender_name'] ?? 'Sistema');
                            echo renderField('Data', $data['created_at'], 'date');
                            break;
                            
                        case 'auditoria':
                            echo renderField('Ação', $data['action']);
                            echo renderField('Admin', $data['admin_nome'] ?? 'Sistema');
                            echo renderField('Email', $data['admin_email'], 'email');
                            echo renderField('Endereço IP', $data['ip_address'], 'code');
                            echo renderField('Data', $data['created_at'], 'date');
                            break;
                            
                        case 'transacao':
                            echo renderField('Invoice', $data['invoice_number'], 'code');
                            echo renderField('Usuário', $data['user_name']);
                            echo renderField('Valor', $data['amount'], 'money');
                            echo renderField('Status', ucfirst($data['status']));
                            echo renderField('Data', $data['created_at'], 'date');
                            break;
                    }
                    ?>
                    
                    <?php if (isset($data['updated_at']) && $data['updated_at']): ?>
                        <li>
                            <span class="info-label">Última Atualização</span>
                            <span class="info-value"><?= date('d/m/Y H:i', strtotime($data['updated_at'])) ?></span>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- DADOS ADICIONAIS (se houver) -->
        <?php if ($type === 'alerta' && !empty($data['message'])): ?>
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa-solid fa-message"></i>
                    Mensagem Completa
                </h3>
            </div>
            <div class="card-body">
                <div style="color: var(--text-primary); line-height: 1.6;">
                    <?= nl2br(htmlspecialchars($data['message'])) ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- HISTÓRICO DE AÇÕES -->
        <?php if (!empty($historico)): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    Histórico de Ações
                </h3>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <?php foreach ($historico as $h): ?>
                    <?php
                        $timelineClass = 'pending';
                        if (strpos($h['action'], 'APROVADO') !== false || strpos($h['action'], 'COMPLETED') !== false) {
                            $timelineClass = 'completed';
                        } elseif (strpos($h['action'], 'REJEITADO') !== false || strpos($h['action'], 'FAILED') !== false) {
                            $timelineClass = 'rejected';
                        }
                    ?>
                    <div class="timeline-item <?= $timelineClass ?>">
                        <div class="timeline-content">
                            <div class="timeline-title"><?= htmlspecialchars($h['action']) ?></div>
                            <div class="timeline-meta">
                                <?= htmlspecialchars($h['admin_nome'] ?? 'Sistema') ?> • 
                                <?= date('d/m/Y H:i', strtotime($h['created_at'])) ?> • 
                                IP: <?= htmlspecialchars($h['ip_address']) ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- COLUNA DIREITA: DOCUMENTOS E AÇÕES -->
    <div>
        
        <!-- VISUALIZAÇÃO DE DOCUMENTOS -->
        <?php if ($config['has_documents'] && !empty($data['license_path'])): ?>
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa-solid fa-file-pdf"></i>
                    Documento Anexado
                </h3>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="document-preview">
                    <?php
                        $filePath = '../../' . $data['license_path'];
                        $fileExt = strtolower(pathinfo($data['license_path'], PATHINFO_EXTENSION));
                        
                        if (in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif', 'webp'])):
                    ?>
                        <img src="<?= htmlspecialchars($filePath) ?>" alt="Documento" style="max-width: 100%; max-height: 500px; border-radius: 8px;">
                    <?php elseif ($fileExt === 'pdf'): ?>
                        <iframe src="<?= htmlspecialchars($filePath) ?>" frameborder="0" style="width: 100%; height: 500px; border: 1px solid var(--border); border-radius: 8px;"></iframe>
                    <?php else: ?>
                        <div style="text-align: center; padding: 60px 20px;">
                            <i class="fa-solid fa-file" style="font-size: 4rem; color: var(--text-muted); margin-bottom: 16px;"></i>
                            <p style="color: var(--text-secondary); margin-bottom: 16px;">
                                Arquivo: <?= basename($data['license_path']) ?>
                            </p>
                            <a href="<?= htmlspecialchars($filePath) ?>" class="btn btn-primary" download>
                                <i class="fa-solid fa-download"></i>
                                Baixar Documento
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- FORMULÁRIO DE APROVAÇÃO/REJEIÇÃO -->
        <?php if ($config['has_approval'] && $data[$config['status_field']] === 'pendente'): ?>
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa-solid fa-gavel"></i>
                    Decisão de Aprovação
                </h3>
            </div>
            <div class="card-body">
                <form method="POST" id="decisionForm" onsubmit="return confirmDecision(event)">
                    <div class="decision-options">
                        <div class="decision-option approve" onclick="selectDecision('approve')">
                            <div class="decision-option-icon" style="color: var(--status-success);">
                                <i class="fa-solid fa-check-circle"></i>
                            </div>
                            <div class="decision-option-label">Aprovar</div>
                        </div>
                        
                        <div class="decision-option reject" onclick="selectDecision('reject')">
                            <div class="decision-option-icon" style="color: var(--status-error);">
                                <i class="fa-solid fa-times-circle"></i>
                            </div>
                            <div class="decision-option-label">Rejeitar</div>
                        </div>
                    </div>
                    
                    <input type="hidden" name="action" id="decisionAction">
                    
                    <div class="form-group" id="motivoGroup" style="display: none; margin-top: 16px;">
                        <label class="form-label">Motivo da Rejeição *</label>
                        <textarea name="motivo" id="motivo" class="form-textarea" rows="4" placeholder="Descreva o motivo da rejeição..."></textarea>
                        <div class="form-hint">Seja claro e objetivo no motivo</div>
                    </div>
                    
                    <div style="display: flex; gap: 12px; margin-top: 20px;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;" id="submitBtn" disabled>
                            <i class="fa-solid fa-paper-plane"></i>
                            Confirmar Decisão
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- MOTIVO DE REJEIÇÃO -->
        <?php if (isset($data['motivo_rejeicao']) && !empty($data['motivo_rejeicao'])): ?>
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa-solid fa-comment-slash"></i>
                    Motivo da Rejeição
                </h3>
            </div>
            <div class="card-body">
                <div class="alert error">
                    <i class="fa-solid fa-exclamation-triangle"></i>
                    <div><?= nl2br(htmlspecialchars($data['motivo_rejeicao'])) ?></div>
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
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    
                    <?php if ($type === 'usuario' && isset($data['business_id'])): ?>
                    <button class="btn btn-secondary" onclick="window.open('detalhes?type=empresa&id=<?= $data['business_id'] ?>', '_blank')">
                        <i class="fa-solid fa-building"></i>
                        Ver Dados da Empresa
                    </button>
                    <?php endif; ?>
                    
                    <button class="btn btn-ghost" onclick="copyToClipboard('<?= $id ?>')">
                        <i class="fa-solid fa-copy"></i>
                        Copiar ID do Registro
                    </button>
                    
                    <button class="btn btn-ghost" onclick="shareLink()">
                        <i class="fa-solid fa-share"></i>
                        Compartilhar Link
                    </button>
                    
                    <?php if ($isSuperAdmin): ?>
                    <button class="btn btn-danger" onclick="deleteRecord()">
                        <i class="fa-solid fa-trash"></i>
                        Excluir Registro
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    let selectedDecision = null;

    function selectDecision(action) {
        document.querySelectorAll('.decision-option').forEach(opt => opt.classList.remove('selected'));
        document.querySelector(`.decision-option.${action}`).classList.add('selected');
        
        selectedDecision = action;
        document.getElementById('decisionAction').value = action;
        document.getElementById('submitBtn').disabled = false;
        
        const motivoGroup = document.getElementById('motivoGroup');
        if (action === 'reject') {
            motivoGroup.style.display = 'block';
            document.getElementById('motivo').required = true;
        } else {
            motivoGroup.style.display = 'none';
            document.getElementById('motivo').required = false;
        }
    }

    function confirmDecision(event) {
        event.preventDefault();
        
        if (!selectedDecision) {
            alert('⚠️ Selecione uma decisão');
            return false;
        }
        
        if (selectedDecision === 'reject' && !document.getElementById('motivo').value.trim()) {
            alert('⚠️ Informe o motivo da rejeição');
            return false;
        }
        
        const action = selectedDecision === 'approve' ? 'APROVAR' : 'REJEITAR';
        if (confirm(`Confirma a decisão de ${action} este registro?`)) {
            document.getElementById('decisionForm').submit();
            return true;
        }
        
        return false;
    }

    function deleteRecord() {
        if (!confirm('⚠️ ATENÇÃO: Deseja realmente excluir este registro?\n\nEsta ação não pode ser desfeita!')) {
            return;
        }
        
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="delete">';
        document.body.appendChild(form);
        form.submit();
    }

    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            alert('✅ ID copiado: ' + text);
        });
    }

    function shareLink() {
        const url = window.location.href;
        navigator.clipboard.writeText(url).then(() => {
            alert('✅ Link copiado para a área de transferência!');
        });
    }

    function exportToPDF() {
        alert('🚧 Funcionalidade de exportação em desenvolvimento');
        // Implementar exportação PDF futuramente
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'p') {
            e.preventDefault();
            window.print();
        }
        if (e.key === 'Escape') {
            window.close();
        }
    });

    console.log('✅ Detalhes carregados - Tipo: <?= $type ?> | ID: <?= $id ?>');
</script>

</body>
</html>