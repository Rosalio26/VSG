<?php
/**
 * ================================================================================
 * HELPER DE AUDIT LOGS
 * Arquivo: includes/audit_helper.php
 * Descrição: Funções auxiliares para registro de logs de auditoria
 * ================================================================================
 */

if (!defined('IS_ADMIN_PAGE')) {
    die('Acesso negado');
}

/**
 * Registra uma ação no log de auditoria
 * 
 * @param mysqli $mysqli Conexão com banco de dados
 * @param int $adminId ID do admin que executou a ação
 * @param string $action Nome da ação (ex: USER_UPDATE, DOC_APPROVE)
 * @param array|string|null $details Detalhes adicionais (será convertido para JSON)
 * @return bool Sucesso ou falha
 */
function auditLog($mysqli, $adminId, $action, $details = null) {
    try {
        // Captura informações do request
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        // Converte detalhes para JSON se for array
        if (is_array($details)) {
            $details = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        
        // Prepara statement
        $stmt = $mysqli->prepare("
            INSERT INTO admin_audit_logs 
            (admin_id, action, ip_address, user_agent, details, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        if (!$stmt) {
            error_log("Audit Log Error: " . $mysqli->error);
            return false;
        }
        
        $stmt->bind_param("issss", $adminId, $action, $ip, $userAgent, $details);
        $success = $stmt->execute();
        $stmt->close();
        
        return $success;
        
    } catch (Exception $e) {
        error_log("Audit Log Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Registra login de admin
 */
function auditLogin($mysqli, $adminId, $success = true) {
    $action = $success ? 'LOGIN_SUCCESS' : 'LOGIN_FAILED';
    $details = [
        'timestamp' => date('Y-m-d H:i:s'),
        'success' => $success
    ];
    return auditLog($mysqli, $adminId, $action, $details);
}

/**
 * Registra logout de admin
 */
function auditLogout($mysqli, $adminId) {
    return auditLog($mysqli, $adminId, 'LOGOUT', [
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Registra alteração em usuário
 */
function auditUserChange($mysqli, $adminId, $targetUserId, $changes) {
    return auditLog($mysqli, $adminId, 'USER_UPDATE', [
        'target_user_id' => $targetUserId,
        'changes' => $changes,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Registra criação de usuário
 */
function auditUserCreate($mysqli, $adminId, $newUserId, $userType) {
    return auditLog($mysqli, $adminId, 'USER_CREATE', [
        'new_user_id' => $newUserId,
        'user_type' => $userType,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Registra deleção de usuário
 */
function auditUserDelete($mysqli, $adminId, $deletedUserId, $reason = null) {
    return auditLog($mysqli, $adminId, 'USER_DELETE', [
        'deleted_user_id' => $deletedUserId,
        'reason' => $reason,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Registra bloqueio de usuário
 */
function auditUserBlock($mysqli, $adminId, $blockedUserId, $reason = null) {
    return auditLog($mysqli, $adminId, 'USER_BLOCKED', [
        'blocked_user_id' => $blockedUserId,
        'reason' => $reason,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Registra desbloqueio de usuário
 */
function auditUserUnblock($mysqli, $adminId, $unblockedUserId) {
    return auditLog($mysqli, $adminId, 'USER_UNBLOCKED', [
        'unblocked_user_id' => $unblockedUserId,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Registra aprovação de documento
 */
function auditDocApprove($mysqli, $adminId, $businessId, $userId) {
    return auditLog($mysqli, $adminId, 'DOC_APPROVE', [
        'business_id' => $businessId,
        'user_id' => $userId,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Registra rejeição de documento
 */
function auditDocReject($mysqli, $adminId, $businessId, $userId, $reason) {
    return auditLog($mysqli, $adminId, 'DOC_REJECT', [
        'business_id' => $businessId,
        'user_id' => $userId,
        'reason' => $reason,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Registra mudança de senha
 */
function auditPasswordChange($mysqli, $adminId, $targetUserId = null) {
    $details = ['timestamp' => date('Y-m-d H:i:s')];
    
    if ($targetUserId && $targetUserId != $adminId) {
        $details['target_user_id'] = $targetUserId;
        $action = 'PASSWORD_CHANGE_OTHER';
    } else {
        $action = 'PASSWORD_CHANGE_SELF';
    }
    
    return auditLog($mysqli, $adminId, $action, $details);
}

/**
 * Registra mudança de role
 */
function auditRoleChange($mysqli, $adminId, $targetUserId, $oldRole, $newRole) {
    return auditLog($mysqli, $adminId, 'ROLE_CHANGE', [
        'target_user_id' => $targetUserId,
        'old_role' => $oldRole,
        'new_role' => $newRole,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Registra alteração de configuração do sistema
 */
function auditSystemConfig($mysqli, $adminId, $configKey, $oldValue, $newValue) {
    return auditLog($mysqli, $adminId, 'SYS_CONFIG', [
        'config_key' => $configKey,
        'old_value' => $oldValue,
        'new_value' => $newValue,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Registra backup do banco de dados
 */
function auditDatabaseBackup($mysqli, $adminId, $backupFile) {
    return auditLog($mysqli, $adminId, 'DB_BACKUP', [
        'backup_file' => $backupFile,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Registra exportação de dados
 */
function auditDataExport($mysqli, $adminId, $exportType, $recordCount) {
    return auditLog($mysqli, $adminId, 'DATA_EXPORT', [
        'export_type' => $exportType,
        'record_count' => $recordCount,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Registra alerta de segurança
 */
function auditSecurityAlert($mysqli, $adminId, $alertType, $details) {
    return auditLog($mysqli, $adminId, 'SECURITY_ALERT', [
        'alert_type' => $alertType,
        'details' => $details,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Registra acesso negado (tentativa de acessar recurso não autorizado)
 */
function auditAccessDenied($mysqli, $adminId, $resource, $reason = null) {
    return auditLog($mysqli, $adminId, 'ACCESS_DENIED', [
        'resource' => $resource,
        'reason' => $reason,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * EXEMPLOS DE USO:
 * 
 * // Login
 * auditLogin($mysqli, $adminId, true);
 * 
 * // Atualizar usuário
 * auditUserChange($mysqli, $adminId, 123, [
 *     'field' => 'status',
 *     'old_value' => 'inactive',
 *     'new_value' => 'active'
 * ]);
 * 
 * // Aprovar documento
 * auditDocApprove($mysqli, $adminId, 456, 123);
 * 
 * // Mudança de role
 * auditRoleChange($mysqli, $adminId, 789, 'admin', 'superadmin');
 * 
 * // Log customizado
 * auditLog($mysqli, $adminId, 'CUSTOM_ACTION', [
 *     'description' => 'Ação personalizada',
 *     'data' => ['key' => 'value']
 * ]);
 */
