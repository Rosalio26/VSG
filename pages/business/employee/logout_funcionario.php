<?php
/**
 * LOGOUT DE FUNCIONÁRIOS
 * Arquivo: pages/business/employee/logout_funcionario.php
 */

session_start();

require_once '../../../registration/includes/db.php';

// Registrar log de logout
if (isset($_SESSION['employee_auth']['employee_id'])) {
    $employeeId = (int)$_SESSION['employee_auth']['employee_id'];
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $stmt = $mysqli->prepare("
        INSERT INTO employee_access_logs (employee_id, action, ip_address, user_agent)
        VALUES (?, 'logout', ?, ?)
    ");
    $stmt->bind_param('iss', $employeeId, $ip, $userAgent);
    $stmt->execute();
    $stmt->close();
}

// Limpar sessão de funcionário
unset($_SESSION['employee_auth']);

// Destruir sessão se estiver vazia
if (empty($_SESSION)) {
    session_destroy();
}

// Redirecionar para login PRINCIPAL com indicador
header('Location: ../../../registration/login/login.php?employee_logout=1');
exit;