<?php
session_start();

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
    $errorSteps = []; // Mapeia erros aos steps

    // STEP 1: Identidade
    $nome_empresa   = trim($_POST['nome_empresa'] ?? '');
    $tipo_empresa   = trim($_POST['tipo_empresa'] ?? '');
    $descricao      = trim($_POST['descricao'] ?? '');

    if (empty($nome_empresa)) {
        $errors['nome_empresa'] = 'A razão social é obrigatória.';
        $errorSteps['nome_empresa'] = 1;
    }
    if (empty($tipo_empresa) || $tipo_empresa === 'selet') {
        $errors['tipo_empresa'] = 'Selecione o tipo de empresa.';
        $errorSteps['tipo_empresa'] = 1;
    }

    // STEP 2: Localização & Fiscal
    $pais           = trim($_POST['pais'] ?? '');
    $country        = $pais;
    $country_code   = trim($_POST['country_code'] ?? '');
    $state          = trim($_POST['state'] ?? '');
    $city           = trim($_POST['city'] ?? '');
    $address        = trim($_POST['address'] ?? '');
    $postal_code    = trim($_POST['postal_code'] ?? '');
    $latitude       = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
    $longitude      = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;

    $fiscal_mode    = $_POST['fiscal_mode'] ?? 'text';
    $tax_id         = trim($_POST['tax_id'] ?? '');

    if (empty($country)) {
        $errors['pais'] = 'Selecione o país.';
        $errorSteps['pais'] = 2;
    }
    if (empty($state)) {
        $errors['state'] = 'Estado/Província é obrigatório.';
        $errorSteps['state'] = 2;
    }
    if (empty($city)) {
        $errors['city'] = 'Cidade é obrigatória.';
        $errorSteps['city'] = 2;
    }

    if ($fiscal_mode === 'text' && empty($tax_id)) {
        $errors['tax_id'] = 'O código fiscal é obrigatório.';
        $errorSteps['tax_id'] = 2;
    } elseif ($fiscal_mode === 'file' && (!isset($_FILES['tax_id_file']) || $_FILES['tax_id_file']['error'] !== UPLOAD_ERR_OK)) {
        $errors['tax_id_file'] = 'O upload do documento fiscal é obrigatório.';
        $errorSteps['tax_id_file'] = 2;
    }

    // STEP 3: Contatos & Documentação
    $email_business = strtolower(trim($_POST['email_business'] ?? ''));
    $telefone       = trim($_POST['telefone'] ?? '');
    $no_logo        = isset($_POST['no_logo']);

    if (empty($email_business)) {
        $errors['email_business'] = 'O e-mail é obrigatório.';
        $errorSteps['email_business'] = 3;
    } elseif (!filter_var($email_business, FILTER_VALIDATE_EMAIL)) {
        $errors['email_business'] = 'E-mail inválido.';
        $errorSteps['email_business'] = 3;
    } else {
        // ========== VALIDAÇÃO DE PROVEDORES PERMITIDOS ==========
        $provedoresPermitidos = [
            // Google
            'gmail.com',
            'googlemail.com',
            
            // Microsoft
            'outlook.com',
            'hotmail.com',
            'live.com',
            'msn.com',
            
            // Apple
            'icloud.com',
            'me.com',
            'mac.com'
        ];

        $dominio = substr(strrchr($email_business, "@"), 1);
        
        if (!in_array(strtolower($dominio), $provedoresPermitidos)) {
            $errors['email_business'] = 'Desculpe tipo de email nao valido. Estamos trabalhando para adicionar outros provedores de emails em breve.';
            $errorSteps['email_business'] = 3;
        }
        // ========== FIM DA VALIDAÇÃO ==========
    }

    if (empty($telefone)) {
        $errors['telefone'] = 'O telefone é obrigatório.';
        $errorSteps['telefone'] = 3;
    } elseif (!preg_match('/^\+/', $telefone)) {
        $errors['telefone'] = 'Inclua o código do país (ex: +258).';
        $errorSteps['telefone'] = 3;
    }

    // STEP 4: Segurança
    $password       = $_POST['password'] ?? '';
    $password_conf  = $_POST['password_confirm'] ?? '';

    if (empty($password)) {
        $errors['password'] = 'A senha é obrigatória.';
        $errorSteps['password'] = 4;
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'A senha deve ter no mínimo 8 caracteres.';
        $errorSteps['password'] = 4;
    }
    if ($password !== $password_conf) {
        $errors['password_confirm'] = 'As senhas não coincidem.';
        $errorSteps['password_confirm'] = 4;
    }

    /* ================= 3. VERIFICAÇÃO DE DUPLICIDADE ================= */
    
    // Verifica E-mail
    $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email_business);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors['email_business'] = 'Este e-mail já está cadastrado no sistema.';
        $errorSteps['email_business'] = 3;
    }
    $stmt->close();

    // Verifica Telefone
    $stmt = $mysqli->prepare("SELECT id FROM users WHERE telefone = ? LIMIT 1");
    $stmt->bind_param('s', $telefone);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors['telefone'] = 'Este telefone já está cadastrado no sistema.';
        $errorSteps['telefone'] = 3;
    }
    $stmt->close();

    // Verifica Tax ID (se for texto)
    if ($fiscal_mode === 'text' && !empty($tax_id)) {
        $stmt = $mysqli->prepare("SELECT id FROM businesses WHERE tax_id = ? LIMIT 1");
        $stmt->bind_param('s', $tax_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors['tax_id'] = 'Este documento fiscal já está cadastrado.';
            $errorSteps['tax_id'] = 2;
        }
        $stmt->close();
    }

    // Se houver erros, retorna com o step do primeiro erro
    if (!empty($errors)) {
        $firstErrorStep = !empty($errorSteps) ? min($errorSteps) : 1;
        echo json_encode([
            'errors' => $errors,
            'errorStep' => $firstErrorStep,
            'errorSteps' => $errorSteps
        ]);
        exit;
    }

    /* ================= 4. FUNÇÃO DE UPLOAD ================= */
    function uploadBusinessDoc($fileKey, $prefix, &$errors, $step) {
        global $errorSteps;
        
        if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
            $errors[$fileKey] = "Falha técnica no upload do arquivo.";
            $errorSteps[$fileKey] = $step;
            return null;
        }

        $file = $_FILES[$fileKey];
        $maxSize = 5 * 1024 * 1024; // 5MB
        $allowedExts = ['png', 'jpg', 'jpeg', 'pdf'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExts)) {
            $errors[$fileKey] = "Formato inválido. Use PNG, JPEG ou PDF.";
            $errorSteps[$fileKey] = $step;
            return null;
        }

        if ($file['size'] > $maxSize) {
            $errors[$fileKey] = "Arquivo muito grande. O limite é 5MB.";
            $errorSteps[$fileKey] = $step;
            return null;
        }

        $uploadDir = "../uploads/business/";
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                $errors[$fileKey] = "Erro interno: Falha ao criar pasta de destino.";
                $errorSteps[$fileKey] = $step;
                return null;
            }
        }

        $newName = $prefix . "_" . bin2hex(random_bytes(10)) . "." . $ext;
        $dest = $uploadDir . $newName;
        
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            return $newName;
        }

        $errors[$fileKey] = "Não foi possível salvar o arquivo no servidor.";
        $errorSteps[$fileKey] = $step;
        return null;
    }

    // Processar uploads
    $pathLicenca = uploadBusinessDoc('licenca', 'lic', $errors, 3);
    if (!$pathLicenca && !isset($errors['licenca'])) {
        $errors['licenca'] = "O upload do Alvará / Licença é obrigatório.";
        $errorSteps['licenca'] = 3;
    }

    $pathLogo = null;
    if (!$no_logo) {
        $pathLogo = uploadBusinessDoc('logo', 'log', $errors, 3);
        if (!$pathLogo && !isset($errors['logo'])) {
            $errors['logo'] = "A logo é obrigatória ou selecione 'Não tenho logo'.";
            $errorSteps['logo'] = 3;
        }
    }

    $pathFiscal = null;
    if ($fiscal_mode === 'file') {
        $pathFiscal = uploadBusinessDoc('tax_id_file', 'tax', $errors, 2);
        if (!$pathFiscal && !isset($errors['tax_id_file'])) {
            $errors['tax_id_file'] = "O upload do documento fiscal é obrigatório.";
            $errorSteps['tax_id_file'] = 2;
        }
    }

    if (!empty($errors)) {
        $firstErrorStep = !empty($errorSteps) ? min($errorSteps) : 1;
        echo json_encode([
            'errors' => $errors,
            'errorStep' => $firstErrorStep,
            'errorSteps' => $errorSteps
        ]);
        exit;
    }

    /* ================= 5. TRANSAÇÃO (BANCO DE DADOS) ================= */
    $mysqli->begin_transaction();

    $token    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires  = date('Y-m-d H:i:s', time() + 3600);
    $realPassHash = password_hash($password, PASSWORD_DEFAULT);

    // Inserir User com localização
    $sqlUser = "INSERT INTO users (
                    type, nome, email, telefone, password_hash, registration_step, 
                    email_token, email_token_expires,
                    country, country_code, state, city, address, postal_code, latitude, longitude, location_updated_at
                ) VALUES ('company', ?, ?, ?, ?, 'email_pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $mysqli->prepare($sqlUser);
    $stmt->bind_param("ssssssssssssdd", 
        $nome_empresa, $email_business, $telefone, $realPassHash, $token, $expires, 
        $country, $country_code, $state, $city, $address, $postal_code, $latitude, $longitude
    );
    $stmt->execute();
    $newUserId = $mysqli->insert_id; 

    $finalTaxIdText = ($fiscal_mode === 'text') ? $tax_id : null;
    $finalTaxIdFile = ($fiscal_mode === 'file') ? $pathFiscal : null;

    // Inserir Business
    $sqlBus = "INSERT INTO businesses (user_id, tax_id, tax_id_file, business_type, description, country, region, city, license_path, logo_path) 
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmtBus = $mysqli->prepare($sqlBus);
    $stmtBus->bind_param("isssssssss", 
        $newUserId, $finalTaxIdText, $finalTaxIdFile, $tipo_empresa, $descricao, $country, $state, $city, $pathLicenca, $pathLogo
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
    echo json_encode(['error' => 'Ocorreu um erro ao salvar os dados: ' . $e->getMessage()]);
}