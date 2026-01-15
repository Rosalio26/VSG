<?php
/**
 * ================================================================================
 * VISIONGREEN - AUDIT HELPER
 * Módulo: modules/includes/audit_helper.php
 * Descrição: Funções para logging de auditoria
 * ================================================================================
 */

/**
 * Log de aprovação de documentos
 */
function auditDocApprove($mysqli, $adminId, $businessId, $userId) {
    $action = 'Documentos Aprovados';
    $details = "Documentos da empresa (ID: $businessId) aprovados por Admin ID: $adminId";
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    $userAgent = $mysqli->real_escape_string($userAgent);
    $details = $mysqli->real_escape_string($details);
    
    $mysqli->query("
        INSERT INTO admin_audit_logs 
        (admin_id, action, ip_address, user_agent, details, created_at) 
        VALUES (
            $adminId, 
            '$action', 
            '$ipAddress', 
            '$userAgent', 
            '$details', 
            NOW()
        )
    ");
}

/**
 * Log de rejeição de documentos
 */
function auditDocReject($mysqli, $adminId, $businessId, $userId, $motivo) {
    $action = 'Documentos Rejeitados';
    $motivo = $mysqli->real_escape_string($motivo);
    $details = "Documentos da empresa (ID: $businessId) rejeitados. Motivo: $motivo";
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    $userAgent = $mysqli->real_escape_string($userAgent);
    $details = $mysqli->real_escape_string($details);
    
    $mysqli->query("
        INSERT INTO admin_audit_logs 
        (admin_id, action, ip_address, user_agent, details, created_at) 
        VALUES (
            $adminId, 
            '$action', 
            '$ipAddress', 
            '$userAgent', 
            '$details', 
            NOW()
        )
    ");
}

/**
 * Log genérico de ação
 */
function auditAction($mysqli, $adminId, $action, $details = '') {
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    $action = $mysqli->real_escape_string($action);
    $userAgent = $mysqli->real_escape_string($userAgent);
    $details = $mysqli->real_escape_string($details);
    
    $mysqli->query("
        INSERT INTO admin_audit_logs 
        (admin_id, action, ip_address, user_agent, details, created_at) 
        VALUES (
            $adminId, 
            '$action', 
            '$ipAddress', 
            '$userAgent', 
            '$details', 
            NOW()
        )
    ");
}

?>