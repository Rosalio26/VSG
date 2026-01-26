<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

if ($user_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

switch ($action) {
    case 'mark_as_read':
        // Marcar como lida
        $notif_id = intval($_POST['notif_id']);
        
        $sql = "UPDATE notifications 
                SET status = 'lida', read_at = NOW() 
                WHERE id = ? AND receiver_id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $notif_id, $user_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao atualizar']);
        }
        break;

    case 'mark_all_read':
        // Marcar todas como lidas
        $sql = "UPDATE notifications 
                SET status = 'lida', read_at = NOW() 
                WHERE receiver_id = ? AND status = 'nao_lida'";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'count' => $stmt->affected_rows]);
        } else {
            echo json_encode(['success' => false]);
        }
        break;

    case 'delete':
        // Deletar notificação (soft delete)
        $notif_id = intval($_POST['notif_id']);
        
        $sql = "UPDATE notifications 
                SET deleted_at = NOW() 
                WHERE id = ? AND receiver_id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $notif_id, $user_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        break;

    case 'get_notification':
        // Buscar notificação completa
        $notif_id = intval($_GET['notif_id']);
        
        $sql = "SELECT n.*, 
                o.order_number, o.total as order_total,
                p.nome as product_name
                FROM notifications n
                LEFT JOIN orders o ON n.related_order_id = o.id
                LEFT JOIN products p ON n.related_product_id = p.id
                WHERE n.id = ? AND n.receiver_id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $notif_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            echo json_encode(['success' => true, 'data' => $row]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Notificação não encontrada']);
        }
        break;

    case 'get_count':
        // Obter contador atualizado
        $sql = "SELECT COUNT(*) as total 
                FROM notifications 
                WHERE receiver_id = ? 
                AND status = 'nao_lida' 
                AND deleted_at IS NULL";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_assoc()['total'];
        
        echo json_encode(['success' => true, 'count' => $count]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Ação inválida']);
}