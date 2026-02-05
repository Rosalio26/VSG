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

    rateLimit('pessoa_store', 10, 120);

    if (!csrf_validate($_POST['csrf'] ?? '')) {
        echo json_encode(['error' => 'Token inválido.']);
        exit;
    }
    
    /* ================= 2. COLETA E VALIDAÇÃO ================= */
    $nome     = trim($_POST['nome'] ?? '');
    $apelido  = trim($_POST['apelido'] ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $telefone = trim($_POST['telefone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    $country        = trim($_POST['country'] ?? '');
    $country_code   = trim($_POST['country_code'] ?? '');
    $state          = trim($_POST['state'] ?? '');
    $city           = trim($_POST['city'] ?? '');
    $address        = trim($_POST['address'] ?? '');
    $postal_code    = trim($_POST['postal_code'] ?? '');
    $latitude       = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
    $longitude      = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;

    $errors = [];

    if (empty($nome)) $errors['nome'] = 'O nome é obrigatório.';
    if (empty($apelido)) $errors['apelido'] = 'O apelido é obrigatório.';
    if (empty($email)) $errors['email'] = 'O e-mail é obrigatório.';
    if (empty($telefone)) $errors['telefone'] = 'O telefone é obrigatório.';
    if (empty($password)) $errors['password'] = 'A senha é obrigatória.';
    if (empty($country)) $errors['country'] = 'País é obrigatório.';
    if (empty($state)) $errors['state'] = 'Estado/Província é obrigatório.';
    if (empty($city)) $errors['city'] = 'Cidade é obrigatória.';

    if (!isset($errors['nome']) && mb_strlen($nome) < 3) {
        $errors['nome'] = 'Nome muito curto (mínimo 3 letras).';
    }
    
    if (!isset($errors['apelido']) && mb_strlen($apelido) < 2) {
        $errors['apelido'] = 'Apelido muito curto.';
    }

    if (!isset($errors['email']) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Formato de e-mail inválido.';
    }

    if (!isset($errors['email']) && !empty($email)) {
        $provedoresPermitidos = [
            'gmail.com', 'googlemail.com',
            'outlook.com', 'hotmail.com', 'live.com', 'msn.com',
            'icloud.com', 'me.com', 'mac.com'
        ];

        $dominio = substr(strrchr($email, "@"), 1);
        
        if (!in_array(strtolower($dominio), $provedoresPermitidos)) {
            $errors['email'] = 'Desculpe tipo de email nao valido. Estamos trabalhando para adicionar outros provedores de emails em breve.';
        }
    }

    if (!isset($errors['telefone']) && !preg_match('/^\+[1-9]\d{7,14}$/', $telefone)) {
        $errors['telefone'] = 'Telefone inválido. Use formato internacional: +258841234567';
    }

    if (!isset($errors['password']) && strlen($password) < 8) {
        $errors['password'] = 'A senha deve ter no mínimo 8 caracteres.';
    }

    if (empty($errors['password_confirm']) && $password !== $confirm) {
        $errors['password_confirm'] = 'As senhas não coincidem.';
    }

    if (!empty($errors)) {
        echo json_encode(['errors' => $errors]);
        exit;
    }
    
    /* ================= 3. VERIFICAÇÃO DE DUPLICIDADE ================= */
    
    $stmt = $mysqli->prepare("SELECT id, nome FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $errors['email'] = 'Este e-mail já está cadastrado no sistema.';
    }
    $stmt->close();

    $stmt = $mysqli->prepare("SELECT id FROM users WHERE telefone = ? LIMIT 1");
    $stmt->bind_param('s', $telefone);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors['telefone'] = 'Este telefone já está cadastrado no sistema.';
    }
    $stmt->close();

    if (!empty($errors)) {
        echo json_encode(['errors' => $errors]);
        exit;
    }
    
    /* ================= 4. PREPARAÇÃO DOS DADOS ================= */
    $passHash = password_hash($password, PASSWORD_DEFAULT);
    $token    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires  = date('Y-m-d H:i:s', time() + 3600);

    $type   = 'person';
    $status = 'pending';
    $step   = 'email_pending';

    /* ================= 5. INSERÇÃO NO BANCO ================= */
    $sql = "INSERT INTO users (
                type, nome, apelido, email, telefone, password_hash, 
                status, registration_step, email_token, email_token_expires,
                country, country_code, state, city, address, postal_code, 
                latitude, longitude, location_updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $mysqli->prepare($sql);
    
    $stmt->bind_param(
        "ssssssssssssssssdd", 
        $type, $nome, $apelido, $email, $telefone, $passHash, 
        $status, $step, $token, $expires,
        $country, $country_code, $state, $city, $address, $postal_code, 
        $latitude, $longitude
    );

    $stmt->execute();
    $userId = $stmt->insert_id;
    $stmt->close();

    /* ================= 6. FINALIZAÇÃO ================= */
    $_SESSION['user_id'] = $userId;
    unset($_SESSION['cadastro']); 

    enviarEmailVisionGreen($email, $nome, $token);

    echo json_encode([
        'success' => true,
        'redirect' => '../process/verify_email.php?info=codigo_enviado'
    ]);

} catch (mysqli_sql_exception $e) {
    error_log("Erro SQL pessoa.store: " . $e->getMessage());
    echo json_encode(['error' => 'Erro ao processar cadastro. Tente novamente.']);
} catch (Exception $e) {
    error_log("Erro pessoa.store: " . $e->getMessage());
    echo json_encode(['error' => 'Erro crítico. Entre em contato com o suporte.']);
}