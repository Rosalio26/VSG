<?php
// HANDLER EXCLUSIVO PARA AJAX - NÃO RETORNA HTML
header('Content-Type: application/json');

require_once '../../../../registration/includes/db.php';
session_start();

$adminId = $_SESSION['auth']['user_id'] ?? 0;
$adminRole = $_SESSION['auth']['role'] ?? 'admin';
$isSuperAdmin = ($adminRole === 'superadmin');

// Validar acesso
if (!$isSuperAdmin) {
    echo json_encode([
        'success' => false,
        'message' => 'Acesso negado. Apenas SuperAdmin pode alterar configurações.'
    ]);
    exit;
}

// Validar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || $_POST['action'] !== 'save_config') {
    echo json_encode([
        'success' => false,
        'message' => 'Requisição inválida.'
    ]);
    exit;
}

/* ================= HELPER FUNCTION ================= */
function setConfig($mysqli, $key, $value, $adminId) {
    $key = $mysqli->real_escape_string($key);
    $value = $mysqli->real_escape_string($value);
    
    $query = "INSERT INTO form_config (config_key, config_value, updated_by) 
              VALUES ('$key', '$value', $adminId)
              ON DUPLICATE KEY UPDATE config_value = '$value', updated_by = $adminId";
    
    $result = $mysqli->query($query);
    
    if (!$result) {
        throw new Exception("Erro ao salvar '$key': " . $mysqli->error);
    }
    
    return $result;
}

/* ================= PROCESSAR SAVE ================= */
try {
    $mysqli->begin_transaction();
    
    // Salvar cada configuração
    setConfig($mysqli, 'require_tax_id', isset($_POST['require_tax_id']) ? '1' : '0', $adminId);
    setConfig($mysqli, 'require_license', isset($_POST['require_license']) ? '1' : '0', $adminId);
    setConfig($mysqli, 'allow_manual_creation', isset($_POST['allow_manual_creation']) ? '1' : '0', $adminId);
    setConfig($mysqli, 'validate_nif_format', isset($_POST['validate_nif_format']) ? '1' : '0', $adminId);
    setConfig($mysqli, 'tax_id_min_length', (string)(int)($_POST['tax_id_min_length'] ?? 9), $adminId);
    setConfig($mysqli, 'tax_id_max_length', (string)(int)($_POST['tax_id_max_length'] ?? 14), $adminId);
    setConfig($mysqli, 'allow_duplicate_email', isset($_POST['allow_duplicate_email']) ? '1' : '0', $adminId);
    setConfig($mysqli, 'auto_approve', isset($_POST['auto_approve']) ? '1' : '0', $adminId);
    setConfig($mysqli, 'notify_on_create', isset($_POST['notify_on_create']) ? '1' : '0', $adminId);
    setConfig($mysqli, 'notify_on_approve', isset($_POST['notify_on_approve']) ? '1' : '0', $adminId);
    setConfig($mysqli, 'notify_on_reject', isset($_POST['notify_on_reject']) ? '1' : '0', $adminId);
    setConfig($mysqli, 'send_welcome_email', isset($_POST['send_welcome_email']) ? '1' : '0', $adminId);
    setConfig($mysqli, 'reminder_after_days', (string)(int)($_POST['reminder_after_days'] ?? 7), $adminId);
    setConfig($mysqli, 'create_in_platform_x', isset($_POST['create_in_platform_x']) ? '1' : '0', $adminId);
    setConfig($mysqli, 'add_to_crm', isset($_POST['add_to_crm']) ? '1' : '0', $adminId);
    setConfig($mysqli, 'generate_contract', isset($_POST['generate_contract']) ? '1' : '0', $adminId);
    setConfig($mysqli, 'setup_payment', isset($_POST['setup_payment']) ? '1' : '0', $adminId);
    
    // Log de auditoria
    $mysqli->query("INSERT INTO admin_audit_logs (admin_id, action, ip_address) 
                   VALUES ($adminId, 'ATUALIZOU_CONFIG_FORMULARIOS', '{$_SERVER['REMOTE_ADDR']}')");
    
    $mysqli->commit();
    
    echo json_encode([
        'success' => true,
        'message' => '✅ Configurações salvas com sucesso!',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    $mysqli->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => '❌ Erro ao salvar: ' . $e->getMessage()
    ]);
}