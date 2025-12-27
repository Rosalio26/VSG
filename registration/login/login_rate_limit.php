<?php

/**
 * VisionGreen - Controle de Taxa de Login (Rate Limit)
 * Protege contra ataques de força bruta rastreando E-mail + IP.
 */

/**
 * Verifica se o limite de tentativas foi excedido.
 * @param int $max Definido como 6 para alinhar com o bloqueio de conta (5 erros + 1 chance pós-recuperação).
 * @param int $seconds Janela de 300 segundos (5 minutos).
 */
function checkLoginRateLimit(mysqli $mysqli, string $email, int $max = 6, int $seconds = 300): bool
{
    $ip = $_SERVER['REMOTE_ADDR'];
    $now_ts = time();
    $now_db = date('Y-m-d H:i:s');

    // 1. Garbage Collector: Limpeza ocasional de registros antigos (5% de chance por requisição)
    if (rand(1, 100) <= 5) {
        $mysqli->query("DELETE FROM login_attempts WHERE last_attempt < DATE_SUB(NOW(), INTERVAL 1 DAY)");
    }

    // 2. Busca registro atual para este par E-mail/IP
    $stmt = $mysqli->prepare("
        SELECT id, attempts, last_attempt 
        FROM login_attempts 
        WHERE email = ? AND ip = ? 
        LIMIT 1
    ");
    $stmt->bind_param('ss', $email, $ip);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row) {
        $last_attempt_ts = strtotime($row['last_attempt']);
        $diff = $now_ts - $last_attempt_ts;

        // Caso 1: Ainda está bloqueado (tempo não expirou e atingiu o máximo)
        if ($diff < $seconds && $row['attempts'] >= $max) {
            return false;
        }

        // Caso 2: O tempo de janela já passou, resetamos o contador para 1
        // Caso 3: Dentro do tempo, mas abaixo do limite, incrementamos
        if ($diff >= $seconds) {
            $new_attempts = 1;
        } else {
            $new_attempts = $row['attempts'] + 1;
        }

        $stmt = $mysqli->prepare("
            UPDATE login_attempts 
            SET attempts = ?, last_attempt = ? 
            WHERE id = ?
        ");
        $stmt->bind_param('isi', $new_attempts, $now_db, $row['id']);
        $stmt->execute();
        $stmt->close();
    } else {
        // Primeira tentativa registrada para este usuário/origem
        $stmt = $mysqli->prepare("
            INSERT INTO login_attempts (email, ip, attempts, last_attempt) 
            VALUES (?, ?, 1, ?)
        ");
        $stmt->bind_param('sss', $email, $ip, $now_db);
        $stmt->execute();
        $stmt->close();
    }

    return true;
}

/**
 * Limpa as tentativas após um login ou recuperação bem-sucedida.
 * Chamado em login.process.php (sucesso) e verify_recovery.php (sucesso).
 */
function clearLoginAttempts(mysqli $mysqli, string $email)
{
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = $mysqli->prepare("DELETE FROM login_attempts WHERE email = ? AND ip = ?");
    $stmt->bind_param('ss', $email, $ip);
    $stmt->execute();
    $stmt->close();
}