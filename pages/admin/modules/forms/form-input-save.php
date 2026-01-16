<?php
header('Content-Type: application/json');
require_once '../../../../registration/includes/db.php';
session_start();

$adminId = $_SESSION['auth']['user_id'] ?? 0;
$response = ['success' => false, 'message' => 'Ação desconhecida'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        // --- LÓGICA DE DELEÇÃO ---
        if ($action === 'delete') {
            $userId = (int)$_POST['user_id'];
            if (!$userId) throw new Exception('ID de usuário inválido');

            $mysqli->begin_transaction();
            
            $mysqli->query("DELETE FROM businesses WHERE user_id = $userId");
            $mysqli->query("DELETE FROM notifications WHERE sender_id = $userId OR receiver_id = $userId");
            $mysqli->query("DELETE FROM users WHERE id = $userId");
            
            $mysqli->query("INSERT INTO admin_audit_logs (admin_id, action, ip_address) VALUES ($adminId, 'DELETOU_EMPRESA_$userId', '{$_SERVER['REMOTE_ADDR']}')");
            
            $mysqli->commit();
            echo json_encode(['success' => true, 'message' => 'Empresa deletada com sucesso!']);
            exit;
        }

        // --- LÓGICA DE SALVAMENTO (CREATE/EDIT) ---
        if ($action === 'save') {
            // Aqui você deve colocar a lógica de INSERT/UPDATE que já está no seu arquivo original
            // Para brevidade, assumimos que você moverá o bloco 'save' para cá.
            // Exemplo simplificado:
            $nome = $mysqli->real_escape_string($_POST['nome']);
            $email = $mysqli->real_escape_string($_POST['email']);
            $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : null;

            if ($userId) {
                $mysqli->query("UPDATE users SET nome = '$nome', email = '$email' WHERE id = $userId");
                $msg = "Empresa atualizada!";
            } else {
                $mysqli->query("INSERT INTO users (nome, email, type, created_at) VALUES ('$nome', '$email', 'company', NOW())");
                $msg = "Empresa criada!";
            }

            echo json_encode(['success' => true, 'message' => $msg]);
            exit;
        }

    } catch (Exception $e) {
        if ($mysqli->in_transaction) $mysqli->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}