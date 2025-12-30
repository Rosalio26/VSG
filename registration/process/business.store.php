<?php
session_start();

/**
 * Arquivo: business.store.php
 * Sincronizado com o modelo de Chave Estrangeira (users -> businesses)
 * Retorna erros específicos por campo para validação no front-end.
 */

ini_set('display_errors', 0);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once '../includes/db.php';
require_once '../includes/security.php';
require_once '../includes/errors.php';
require_once '../includes/rate_limit.php';
require_once '../includes/mailer.php'; 

header('Content-Type: application/json');

try {
    /* ================= 1. SEGURANÇA ================= */
    if (!isset($_SESSION['cadastro']['started'])) {
        echo json_encode(['error' => 'Sessão expirada. Recarregue a página.']);
        exit;
    }

    rateLimit('business_store', 5, 60);

    if (!csrf_validate($_POST['csrf'] ?? '')) {
        echo json_encode(['error' => 'Token de segurança inválido.']);
        exit;
    }

    /* ================= 2. COLETA E VALIDAÇÃO DE DADOS ================= */
    $errors = [];

    // Dados para 'users'
    $nome_empresa   = trim($_POST['nome_empresa'] ?? '');
    $email_business = strtolower(trim($_POST['email_business'] ?? ''));
    $telefone       = trim($_POST['telefone'] ?? '');
    
    // Dados para 'businesses'
    $tax_id         = trim($_POST['tax_id'] ?? '');
    $tipo_empresa   = trim($_POST['tipo_empresa'] ?? '');
    $descricao      = trim($_POST['descricao'] ?? '');
    $pais           = trim($_POST['pais'] ?? '');
    $regiao         = trim($_POST['regiao'] ?? '');
    $localidade     = trim($_POST['localidade'] ?? '');

    // Validações de campos obrigatórios
    if (empty($nome_empresa)) $errors['nome_empresa'] = 'A razão social é obrigatória.';
    if (empty($email_business)) $errors['email_business'] = 'O e-mail é obrigatório.';
    if (empty($telefone)) $errors['telefone'] = 'O telefone é obrigatório.';
    if (empty($tax_id)) $errors['tax_id'] = 'O documento fiscal é obrigatório.';
    if (empty($pais)) $errors['pais'] = 'Selecione o país.';

    /* ================= 3. VERIFICAÇÃO DE DUPLICIDADE ================= */
    
    // Verifica E-mail
    if (!isset($errors['email_business'])) {
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email_business);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) $errors['email_business'] = 'Este e-mail já está em uso.';
        $stmt->close();
    }

    // Verifica Telefone
    if (!isset($errors['telefone'])) {
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE telefone = ? LIMIT 1");
        $stmt->bind_param('s', $telefone);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) $errors['telefone'] = 'Este telefone já está cadastrado.';
        $stmt->close();
    }

    // Verifica Tax ID (Documento Fiscal) na tabela de negócios
    if (!isset($errors['tax_id'])) {
        $stmt = $mysqli->prepare("SELECT id FROM businesses WHERE tax_id = ? LIMIT 1");
        $stmt->bind_param('s', $tax_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) $errors['tax_id'] = 'Este documento fiscal já está cadastrado.';
        $stmt->close();
    }

    // Se houver erros de validação ou duplicidade, retorna agora
    if (!empty($errors)) {
        echo json_encode(['errors' => $errors]);
        exit;
    }

    /* ================= 4. UPLOAD DE ARQUIVOS ================= */
    function uploadDoc($fileKey, $prefix) {
        if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) return null;
        $ext = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
        
        // Validação simples de extensão
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        if (!in_array($ext, $allowed)) return null;

        $newName = $prefix . "_" . bin2hex(random_bytes(8)) . "." . $ext;
        $dest = "../uploads/business/" . $newName;
        
        if (!is_dir("../uploads/business/")) mkdir("../uploads/business/", 0755, true);
        
        return move_uploaded_file($_FILES[$fileKey]['tmp_name'], $dest) ? $newName : null;
    }

    $pathLicenca = uploadDoc('licenca', 'lic');
    $pathLogo    = uploadDoc('logo', 'log');

    /* ================= 5. TRANSAÇÃO (TWO-STEP INSERT) ================= */
    $mysqli->begin_transaction();

    // 5.1 Inserir em 'users'
    $token   = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', time() + 3600);
    $type    = 'company';
    $step    = 'email_pending';
    $tempPass = password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT);

    $sqlUser = "INSERT INTO users (type, nome, email, telefone, password_hash, registration_step, email_token, email_token_expires) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $mysqli->prepare($sqlUser);
    $stmt->bind_param("ssssssss", $type, $nome_empresa, $email_business, $telefone, $tempPass, $step, $token, $expires);
    $stmt->execute();
    
    $newUserId = $mysqli->insert_id; 

    // 5.2 Inserir em 'businesses'
    $sqlBus = "INSERT INTO businesses (user_id, tax_id, business_type, description, country, region, city, license_path, logo_path) 
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmtBus = $mysqli->prepare($sqlBus);
    $stmtBus->bind_param("issssssss", 
        $newUserId, $tax_id, $tipo_empresa, $descricao, $pais, $regiao, $localidade, $pathLicenca, $pathLogo
    );
    $stmtBus->execute();

    // Comita as duas operações
    $mysqli->commit();

    /* ================= 6. FINALIZAÇÃO ================= */
    $_SESSION['user_id'] = $newUserId;
    unset($_SESSION['cadastro']);

    enviarEmailVisionGreen($email_business, $nome_empresa, $token);

    echo json_encode([
        'success' => true,
        'redirect' => '../process/verify_email.php?info=codigo_enviado'
    ]);

} catch (Exception $e) {
    if (isset($mysqli)) $mysqli->rollback();
    echo json_encode(['error' => 'Falha Crítica: ' . $e->getMessage()]);
}