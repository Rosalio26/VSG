<?php
/**
 * EXCLUIR FUNCIONÁRIO
 */

header('Content-Type: application/json');

function logDebug($message, $data = null) {
    $logDir = __DIR__ . '/../debug/';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $log = date('H:i:s') . ' - ' . $message;
    if ($data !== null) {
        $log .= ' - ' . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    $log .= "\n";
    
    file_put_contents($logDir . 'excluir_funcionario.log', $log, FILE_APPEND);
}

logDebug('=== INÍCIO EXCLUIR FUNCIONÁRIO ===');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['auth']['user_id'])) {
    logDebug('ERRO: Não autenticado');
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

require_once __DIR__ . '/../../../../../registration/includes/db.php';

$userId = (int)$_SESSION['auth']['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$funcionarioId = (int)$input['id'];

logDebug('Parâmetros', [
    'user_id' => $userId,
    'funcionario_id' => $funcionarioId
]);

try {
    // Verificar se o funcionário pertence à empresa
    logDebug('Verificando proprietário');
    $stmt = $mysqli->prepare("
        SELECT id, nome
        FROM employees
        WHERE id = ?
        AND user_id = ?
    ");
    $stmt->bind_param('ii', $funcionarioId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        logDebug('ERRO: Funcionário não encontrado ou sem permissão');
        echo json_encode([
            'success' => false,
            'message' => 'Funcionário não encontrado'
        ]);
        exit;
    }
    
    $funcionario = $result->fetch_assoc();
    logDebug('Funcionário encontrado', ['nome' => $funcionario['nome']]);
    
    // Soft delete (apenas marcar como inativo)
    logDebug('Executando soft delete');
    $stmt = $mysqli->prepare("
        UPDATE employees
        SET is_active = 0,
            updated_at = NOW()
        WHERE id = ?
        AND user_id = ?
    ");
    $stmt->bind_param('ii', $funcionarioId, $userId);
    
    if (!$stmt->execute()) {
        logDebug('ERRO ao executar', ['error' => $stmt->error]);
        throw new Exception('Erro ao excluir funcionário');
    }
    
    logDebug('Funcionário excluído com sucesso');
    logDebug('=== FIM EXCLUIR FUNCIONÁRIO (SUCESSO) ===');
    
    echo json_encode([
        'success' => true,
        'message' => 'Funcionário excluído com sucesso'
    ]);
    
} catch (Exception $e) {
    logDebug('ERRO', ['message' => $e->getMessage(), 'line' => $e->getLine()]);
    logDebug('=== FIM EXCLUIR FUNCIONÁRIO (ERRO) ===');
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}