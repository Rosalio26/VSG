<?php
/**
 * ================================================================================
 * VISIONGREEN - GET BUSINESS DETAILS
 * Módulo: modules/tabelas/actions/
 * Descrição: AJAX endpoint para buscar detalhes de uma empresa
 * ================================================================================
 */

header('Content-Type: application/json');

if (!defined('IS_ADMIN_PAGE')) {
    require_once '../../../../registration/includes/db.php';
    session_start();
}

// Validar autenticação
if (!isset($_SESSION['auth']) || $_SESSION['auth']['role'] !== 'admin') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Acesso não autorizado'
    ]);
    exit;
}

$response = [
    'status' => 'error',
    'message' => 'ID inválido'
];

if (isset($_GET['id'])) {
    $userId = (int)$_GET['id'];
    
    // Buscar dados do usuário e negócio
    $query = "
        SELECT 
            u.id,
            u.nome,
            u.email,
            u.telefone,
            u.created_at,
            b.tax_id,
            b.business_type,
            b.description,
            b.country,
            b.region,
            b.city,
            b.license_path,
            b.logo_path,
            b.tax_id_file,
            b.status_documentos,
            b.motivo_rejeicao,
            b.updated_at
        FROM users u
        LEFT JOIN businesses b ON u.id = b.user_id
        WHERE u.id = $userId AND u.type = 'company' AND u.deleted_at IS NULL
        LIMIT 1
    ";
    
    $result = $mysqli->query($query);
    
    if ($result && $result->num_rows > 0) {
        $business = $result->fetch_assoc();
        
        $response = [
            'status' => 'success',
            'business' => [
                'id' => $business['id'],
                'nome' => $business['nome'],
                'email' => $business['email'],
                'telefone' => $business['telefone'],
                'tax_id' => $business['tax_id'],
                'business_type' => $business['business_type'],
                'description' => $business['description'],
                'country' => $business['country'],
                'region' => $business['region'],
                'city' => $business['city'],
                'license_path' => $business['license_path'],
                'logo_path' => $business['logo_path'],
                'tax_id_file' => $business['tax_id_file'],
                'status' => $business['status_documentos'] ?? 'pendente',
                'motivo_rejeicao' => $business['motivo_rejeicao'],
                'created_at' => $business['created_at'],
                'updated_at' => $business['updated_at']
            ]
        ];
    } else {
        $response = [
            'status' => 'error',
            'message' => 'Empresa não encontrada'
        ];
    }
}

echo json_encode($response);
exit;

?>