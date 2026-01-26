<?php
session_start();

/**
 * Arquivo: pessoa.store.php
 * Sincronizado com a tabela 'users' (password_hash e ENUMs corretos)
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

    rateLimit('pessoa_store', 5, 60);

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

    // LOCALIZAÇÃO
    $country        = trim($_POST['country'] ?? '');
    $country_code   = trim($_POST['country_code'] ?? '');
    $state          = trim($_POST['state'] ?? '');
    $city           = trim($_POST['city'] ?? '');
    $address        = trim($_POST['address'] ?? '');
    $postal_code    = trim($_POST['postal_code'] ?? '');
    $latitude       = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
    $longitude      = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;

    $errors = [];

    // 1. VALIDAÇÃO DE CAMPOS VAZIOS (Obrigatórios)
    if (empty($nome)) $errors['nome'] = 'O nome é obrigatório.';
    if (empty($apelido)) $errors['apelido'] = 'O apelido é obrigatório.';
    if (empty($email)) $errors['email'] = 'O e-mail é obrigatório.';
    if (empty($telefone)) $errors['telefone'] = 'O telefone é obrigatório.';
    if (empty($password)) $errors['password'] = 'A senha é obrigatória.';
    if (empty($country)) $errors['country'] = 'País é obrigatório.';
    if (empty($state)) $errors['state'] = 'Estado/Província é obrigatório.';
    if (empty($city)) $errors['city'] = 'Cidade é obrigatória.';

    // 2. VALIDAÇÕES ESPECÍFICAS (Só executa se o campo não estiver vazio)
    if (!isset($errors['nome']) && mb_strlen($nome) < 3) {
        $errors['nome'] = 'Nome muito curto (mínimo 3 letras).';
    }
    
    if (!isset($errors['apelido']) && mb_strlen($apelido) < 2) {
        $errors['apelido'] = 'Apelido muito curto.';
    }

    if (!isset($errors['email']) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Formato de e-mail inválido.';
    }

    // Validação de Telefone (verifica se tem o código do país e número mínimo)
    if (!isset($errors['telefone'])) {
        if (strlen($telefone) < 8) {
            $errors['telefone'] = 'Número de telefone incompleto.';
        } elseif (!preg_match('/^\+/', $telefone)) {
            $errors['telefone'] = 'Código do país ausente.';
        }
    }

    if (!isset($errors['password']) && strlen($password) < 8) {
        $errors['password'] = 'A senha deve ter no mínimo 8 caracteres.';
    }

    if (empty($errors['password_confirm']) && $password !== $confirm) {
        $errors['password_confirm'] = 'As senhas não coincidem.';
    }

    /* ================= ENVIO DA RESPOSTA ================= */
    if ($errors) {
        header('Content-Type: application/json');
        echo json_encode(['errors' => $errors]);
        exit;
    }
    
    /* ================= 3. VERIFICAÇÃO DE DUPLICIDADE (SEPARADA) ================= */
    
    // 3.1 Verificar E-mail
    $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors['email'] = 'Este e-mail já está em uso.';
    }
    $stmt->close();

    // 3.2 Verificar Telefone
    $stmt = $mysqli->prepare("SELECT id FROM users WHERE telefone = ? LIMIT 1");
    $stmt->bind_param('s', $telefone);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors['telefone'] = 'Este telefone já está cadastrado.';
    }
    $stmt->close();

    // Se houver qualquer erro de duplicidade, interrompe e envia para os campos certos
    if (!empty($errors)) {
        echo json_encode(['errors' => $errors]);
        exit;
    }
    
    /* ================= 4. DADOS PARA O BANCO (NOMES EXATOS) ================= */
    $passHash = password_hash($password, PASSWORD_DEFAULT);
    $token    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires  = date('Y-m-d H:i:s', time() + 3600);

    // Valores baseados nos seus ENUMs
    $type   = 'person';
    $status = 'pending';
    $step   = 'email_pending'; // Ajustado para o seu ENUM

    /* ================= 5. INSERÇÃO (PASSWORD_HASH) ================= */
    $sql = "INSERT INTO users (
                type, nome, apelido, email, telefone, password_hash, 
                status, registration_step, email_token, email_token_expires,
                country, country_code, state, city, address, postal_code, latitude, longitude, location_updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $mysqli->prepare($sql);
    
    $stmt->bind_param(
        "ssssssssssssssssdd", 
        $type, $nome, $apelido, $email, $telefone, $passHash, 
        $status, $step, $token, $expires,
        $country, $country_code, $state, $city, $address, $postal_code, $latitude, $longitude
    );

    $stmt->execute();
    $userId = $stmt->insert_id;
    $stmt->close();

    /* ================= 6. FINALIZAÇÃO ================= */
    $_SESSION['user_id'] = $userId;
    unset($_SESSION['cadastro']); 

    // Envio do e-mail
    enviarEmailVisionGreen($email, $nome, $token);

    echo json_encode([
        'success' => true,
        'redirect' => '../process/verify_email.php?info=codigo_enviado'
    ]);

} catch (mysqli_sql_exception $e) {
    echo json_encode(['error' => 'Erro no Banco: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Erro Crítico: ' . $e->getMessage()]);
}