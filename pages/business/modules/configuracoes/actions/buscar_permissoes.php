<?php
/**
 * BUSCAR PERMISSÕES DE UM FUNCIONÁRIO
 * Retorna todas as permissões atuais de um funcionário específico
 */

session_start();
header('Content-Type: application/json');

require_once '../../../../../registration/includes/db.php';

// Verificar autenticação do gestor
if (!isset($_SESSION['auth']['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$userId = (int)$_SESSION['auth']['user_id'];
$employeeId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;

if (!$employeeId) {
    echo json_encode(['success' => false, 'error' => 'ID do funcionário não fornecido']);
    exit;
}

// Verificar se o funcionário pertence à empresa
$stmt = $mysqli->prepare("
    SELECT id FROM employees 
    WHERE id = ? AND user_id = ? AND is_active = 1
");
$stmt->bind_param('is', $employeeId, $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Funcionário não encontrado']);
    exit;
}
$stmt->close();

// Buscar permissões atuais
$stmt = $mysqli->prepare("
    SELECT 
        module,
        can_view,
        can_create,
        can_edit,
        can_delete
    FROM employee_permissions
    WHERE employee_id = ?
");
$stmt->bind_param('i', $employeeId);
$stmt->execute();
$result = $stmt->get_result();

$permissions = [];
while ($row = $result->fetch_assoc()) {
    $permissions[$row['module']] = [
        'can_view' => (int)$row['can_view'],
        'can_create' => (int)$row['can_create'],
        'can_edit' => (int)$row['can_edit'],
        'can_delete' => (int)$row['can_delete']
    ];
}
$stmt->close();

echo json_encode([
    'success' => true,
    'permissions' => $permissions
]);