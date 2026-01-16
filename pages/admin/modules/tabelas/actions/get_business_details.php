<?php
/**
 * ================================================================================
 * VISIONGREEN - GET BUSINESS DETAILS (CORRIGIDO)
 * ================================================================================
 */

// 1. Impedir que erros do PHP apareçam como texto e quebrem o JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Definir o Header IMEDIATAMENTE
header('Content-Type: application/json');

// 3. Corrigir o caminho do Banco de Dados
// Se o arquivo está em modules/tabelas/actions/, o caminho para registration é:
$db_path = '../../../../../registration/includes/db.php';

if (file_exists($db_path)) {
    require_once $db_path;
} else {
    echo json_encode(['status' => 'error', 'message' => 'Erro interno: Arquivo de banco nao encontrado.']);
    exit;
}

// 4. Validar autenticação
if (!isset($_SESSION['auth']) || empty($_SESSION['auth']['role'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sessao expirada ou invalida.']);
    exit;
}

if (!in_array($_SESSION['auth']['role'], ['admin', 'superadmin'])) {
    echo json_encode(['status' => 'error', 'message' => 'Acesso negado.']);
    exit;
}

$response = ['status' => 'error', 'message' => 'ID inválido'];

if (isset($_GET['id'])) {
    $userId = (int)$_GET['id'];
    
    if ($userId > 0) {
        try {
            $stmt = $mysqli->prepare("
                SELECT 
                    u.id, u.nome, u.email, u.telefone, u.created_at,
                    b.tax_id, b.business_type, b.description, b.country, b.region, b.city,
                    b.license_path, b.logo_path, b.tax_id_file, b.status_documentos,
                    b.motivo_rejeicao, b.updated_at
                FROM users u
                LEFT JOIN businesses b ON u.id = b.user_id
                WHERE u.id = ? AND u.type = 'company' AND u.deleted_at IS NULL
                LIMIT 1
            ");
            
            if (!$stmt) { throw new Exception($mysqli->error); }
            
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $business = $result->fetch_assoc();
                
                // Formatação dos caminhos de upload para exibição
                $uploadPath = "../../registration/uploads/business/";

                $response = [
                    'status' => 'success',
                    'business' => [
                        'id' => $business['id'],
                        'nome' => $business['nome'],
                        'email' => $business['email'],
                        'telefone' => $business['telefone'] ?? 'N/A',
                        'tax_id' => $business['tax_id'] ?? 'N/A',
                        'business_type' => $business['business_type'] ?? 'N/A',
                        'description' => $business['description'] ?? 'Sem descrição',
                        'location' => ($business['city'] ?? '') . ' / ' . ($business['region'] ?? ''),
                        'license_path' => $business['license_path'] ? $uploadPath . $business['license_path'] : null,
                        'status' => $business['status_documentos'] ?? 'pendente',
                        'motivo_rejeicao' => $business['motivo_rejeicao'],
                        'created_at' => date('d/m/Y H:i', strtotime($business['created_at']))
                    ]
                ];
            } else {
                throw new Exception('Empresa não localizada no banco.');
            }
            $stmt->close();
            
        } catch (Exception $e) {
            $response = ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}

// 5. Limpar qualquer output anterior e enviar JSON puro
ob_clean(); 
echo json_encode($response);
exit;