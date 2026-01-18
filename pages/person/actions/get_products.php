<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../../../registration/includes/db.php';

try {
    $stmt = $mysqli->prepare("
        SELECT 
            p.id,
            p.user_id as company_id,
            p.name as nome,
            p.description as descricao,
            p.price as preco,
            p.currency,
            p.image_path as imagem,
            p.stock_quantity as stock,
            p.category,
            p.eco_verified,
            u.nome as company_name
        FROM products p
        INNER JOIN users u ON p.user_id = u.id
        WHERE p.is_active = 1 
          AND (p.stock_quantity > 0 OR p.stock_quantity IS NULL)
          AND u.status = 'active'
          AND p.deleted_at IS NULL
        ORDER BY p.created_at DESC
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    $companies = [];
    
    while ($row = $result->fetch_assoc()) {
        // Formatar imagem path
        if ($row['imagem']) {
            $row['imagem'] = '../uploads/' . $row['imagem'];
        }
        
        $products[] = $row;
        
        if (!isset($companies[$row['company_id']])) {
            $companies[$row['company_id']] = [
                'id' => $row['company_id'],
                'nome' => $row['company_name']
            ];
        }
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'products' => $products,
        'companies' => array_values($companies)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log('Erro get_products: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao carregar produtos'
    ], JSON_UNESCAPED_UNICODE);
}