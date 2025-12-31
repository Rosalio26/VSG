<?php
session_start();

/**
 * Arquivo: business.store.php
 * Finalizado com criação automática de diretórios e validação rigorosa de arquivos (5MB, formatos específicos).
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

    $nome_empresa   = trim($_POST['nome_empresa'] ?? '');
    $email_business = strtolower(trim($_POST['email_business'] ?? ''));
    $telefone       = trim($_POST['telefone'] ?? '');
    $password       = $_POST['password'] ?? '';
    $password_conf  = $_POST['password_confirm'] ?? '';
    
    $fiscal_mode    = $_POST['fiscal_mode'] ?? 'text';
    $tax_id         = trim($_POST['tax_id'] ?? '');
    $tipo_empresa   = trim($_POST['tipo_empresa'] ?? '');
    $descricao      = trim($_POST['descricao'] ?? '');
    $pais           = trim($_POST['pais'] ?? '');
    $regiao         = trim($_POST['regiao'] ?? '');
    $localidade     = trim($_POST['localidade'] ?? '');
    $no_logo        = isset($_POST['no_logo']);

    if (empty($nome_empresa))   $errors['nome_empresa'] = 'A razão social é obrigatória.';
    if (empty($email_business)) $errors['email_business'] = 'O e-mail é obrigatório.';
    if (empty($telefone))       $errors['telefone'] = 'O telefone é obrigatório.';
    if (empty($pais))           $errors['pais'] = 'Selecione o país.';

    if ($fiscal_mode === 'text' && empty($tax_id)) {
        $errors['tax_id'] = 'O código fiscal é obrigatório.';
    } elseif ($fiscal_mode === 'file' && (!isset($_FILES['tax_id_file']) || $_FILES['tax_id_file']['error'] !== UPLOAD_ERR_OK)) {
        $errors['tax_id'] = 'O upload do documento fiscal é obrigatório.';
    }

    if (empty($password)) {
        $errors['password'] = 'A senha é obrigatória.';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'A senha deve ter no mínimo 8 caracteres.';
    }
    if ($password !== $password_conf) {
        $errors['password_confirm'] = 'As senhas não coincidem.';
    }

    /* ================= 3. VERIFICAÇÃO DE DUPLICIDADE ================= */
    $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ? OR telefone = ? LIMIT 2");
    $stmt->bind_param('ss', $email_business, $telefone);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        // Lógica simples para identificar qual dado está duplicado
        // (Em produção, ideal fazer consultas separadas para mensagens precisas)
        $errors['email_business'] = 'E-mail ou telefone já cadastrados.';
    }

    if ($fiscal_mode === 'text' && !empty($tax_id)) {
        $stmt = $mysqli->prepare("SELECT id FROM businesses WHERE tax_id = ? LIMIT 1");
        $stmt->bind_param('s', $tax_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) $errors['tax_id'] = 'Documento fiscal já cadastrado.';
    }

    if (!empty($errors)) {
        echo json_encode(['errors' => $errors]);
        exit;
    }

    /* ================= 4. FUNÇÃO DE UPLOAD REFORÇADA ================= */
    function uploadBusinessDoc($fileKey, $prefix) {
        if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) return null;

        $file = $_FILES[$fileKey];
        $maxSize = 5 * 1024 * 1024; // 5MB
        $allowedExts = ['png', 'jpg', 'jpeg', 'pdf'];
        
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // Validação de Extensão
        if (!in_array($ext, $allowedExts)) {
            throw new Exception("Formato do arquivo '{$fileKey}' inválido. Apenas PNG, JPEG e PDF são aceitos.");
        }

        // Validação de Tamanho
        if ($file['size'] > $maxSize) {
            throw new Exception("O arquivo '{$fileKey}' excede o limite de 5MB.");
        }

        // Criação automática do diretório
        $uploadDir = "../uploads/business/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $newName = $prefix . "_" . bin2hex(random_bytes(10)) . "." . $ext;
        $dest = $uploadDir . $newName;
        
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            return $newName;
        }
        return null;
    }

    // Processar uploads
    try {
        $pathLicenca = uploadBusinessDoc('licenca', 'lic');
        if (!$pathLicenca) throw new Exception("Falha ao processar o arquivo do Alvará.");

        $pathLogo = null;
        if (!$no_logo) {
            $pathLogo = uploadBusinessDoc('logo', 'log');
            if (!$pathLogo) throw new Exception("Falha ao processar o arquivo da Logo.");
        }

        $pathFiscal = ($fiscal_mode === 'file') ? uploadBusinessDoc('tax_id_file', 'tax') : null;
    } catch (Exception $fileEx) {
        echo json_encode(['error' => $fileEx->getMessage()]);
        exit;
    }

    /* ================= 5. TRANSAÇÃO (BANCO DE DADOS) ================= */
    $mysqli->begin_transaction();

    $token    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires  = date('Y-m-d H:i:s', time() + 3600);
    $realPassHash = password_hash($password, PASSWORD_DEFAULT);

    // Inserir User
    $sqlUser = "INSERT INTO users (type, nome, email, telefone, password_hash, registration_step, email_token, email_token_expires) 
                VALUES ('company', ?, ?, ?, ?, 'email_pending', ?, ?)";
    $stmt = $mysqli->prepare($sqlUser);
    $stmt->bind_param("ssssss", $nome_empresa, $email_business, $telefone, $realPassHash, $token, $expires);
    $stmt->execute();
    $newUserId = $mysqli->insert_id; 

    // Valor final do Tax ID (Texto ou referência ao Arquivo)
    $finalTaxValue = ($fiscal_mode === 'file') ? "FILE:" . $pathFiscal : $tax_id;

    // Inserir Business
    $sqlBus = "INSERT INTO businesses (user_id, tax_id, business_type, description, country, region, city, license_path, logo_path) 
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmtBus = $mysqli->prepare($sqlBus);
    $stmtBus->bind_param("issssssss", 
        $newUserId, $finalTaxValue, $tipo_empresa, $descricao, $pais, $regiao, $localidade, $pathLicenca, $pathLogo
    );
    $stmtBus->execute();

    $mysqli->commit();

    /* ================= 6. FINALIZAÇÃO ================= */
    $_SESSION['user_id'] = $newUserId;
    enviarEmailVisionGreen($email_business, $nome_empresa, $token);

    echo json_encode([
        'success' => true,
        'redirect' => '../process/verify_email.php?info=codigo_enviado'
    ]);

} catch (Exception $e) {
    if (isset($mysqli)) $mysqli->rollback();
    error_log("Erro no registro de negócio: " . $e->getMessage());
    echo json_encode(['error' => 'Ocorreu um erro ao salvar os dados. Tente novamente.']);
}