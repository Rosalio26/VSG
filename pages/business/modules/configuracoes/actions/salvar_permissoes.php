<?php
/**
 * SALVAR PERMISSÕES DE UM FUNCIONÁRIO
 * Atualiza todas as permissões de acesso de um funcionário
 */

session_start();
header('Content-Type: application/json');

require_once '../../../../../registration/includes/db.php';

// Verificar autenticação do gestor
if (!isset($_SESSION['auth']['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$userId = (int)$_SESSION['auth']['user_id'];

// Receber dados
$input = json_decode(file_get_contents('php://input'), true);
$employeeId = isset($input['employee_id']) ? (int)$input['employee_id'] : 0;
$permissions = isset($input['permissions']) ? $input['permissions'] : [];

if (!$employeeId || empty($permissions)) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
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
    echo json_encode(['success' => false, 'message' => 'Funcionário não encontrado']);
    exit;
}
$stmt->close();

// Iniciar transação
$mysqli->begin_transaction();

try {
    // Deletar permissões antigas
    $stmt = $mysqli->prepare("DELETE FROM employee_permissions WHERE employee_id = ?");
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    $stmt->close();

    // Inserir novas permissões
    $stmt = $mysqli->prepare("
        INSERT INTO employee_permissions 
        (employee_id, module, can_view, can_create, can_edit, can_delete)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    foreach ($permissions as $module => $perms) {
        $canView = isset($perms['can_view']) ? (int)$perms['can_view'] : 0;
        $canCreate = isset($perms['can_create']) ? (int)$perms['can_create'] : 0;
        $canEdit = isset($perms['can_edit']) ? (int)$perms['can_edit'] : 0;
        $canDelete = isset($perms['can_delete']) ? (int)$perms['can_delete'] : 0;

        $stmt->bind_param('isiiii', 
            $employeeId, 
            $module, 
            $canView, 
            $canCreate, 
            $canEdit, 
            $canDelete
        );
        $stmt->execute();
    }
    $stmt->close();

    // Registrar log de auditoria
    $stmt = $mysqli->prepare("
        INSERT INTO audit_logs 
        (user_id, action, entity_type, entity_id, details, ip_address, user_agent)
        VALUES (?, 'update_permissions', 'employee', ?, ?, ?, ?)
    ");
    
    $action = 'update_permissions';
    $entityType = 'employee';
    $details = json_encode([
        'employee_id' => $employeeId,
        'modules' => array_keys($permissions)
    ]);
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $stmt->bind_param('iisss', 
        $userId,
        $employeeId,
        $details,
        $ipAddress,
        $userAgent
    );
    $stmt->execute();
    $stmt->close();

    // Commit transação
    $mysqli->commit();

    echo json_encode([
        'success' => true,
        'message' => '✅ Permissões atualizadas com sucesso!'
    ]);

} catch (Exception $e) {
    $mysqli->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao salvar permissões: ' . $e->getMessage()
    ]);
}