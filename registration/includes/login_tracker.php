<?php
/**
 * ================================================================================
 * VISIONGREEN - FUNÇÃO DE REGISTRO DE LOGIN
 * Arquivo: registration/includes/login_tracker.php
 * Descrição: Registra tentativas de login no banco de dados
 * ================================================================================
 */

/**
 * Registra um login no histórico
 * 
 * @param mysqli $mysqli - Conexão com o banco de dados
 * @param int $userId - ID do usuário
 * @param string $ipAddress - IP do cliente
 * @param string $userAgent - User Agent do navegador
 * @param string $status - Status ('success' ou 'failed')
 * @return bool - Sucesso da operação
 */
function recordLogin($mysqli, $userId, $ipAddress, $userAgent, $status = 'success') {
    try {
        $stmt = $mysqli->prepare("
            INSERT INTO login_history (user_id, ip_address, user_agent, status, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        if (!$stmt) {
            error_log("Erro ao preparar statement: " . $mysqli->error);
            return false;
        }
        
        $stmt->bind_param("isss", $userId, $ipAddress, $userAgent, $status);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    } catch (Exception $e) {
        error_log("Erro ao registrar login: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtém o IP real do cliente (considerando proxies)
 * 
 * @return string - Endereço IP
 */
function getClientIP() {
    // Verificar IP de proxy reverso
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED'])) {
        return $_SERVER['HTTP_X_FORWARDED'];
    }
    if (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_FORWARDED_FOR'];
    }
    if (!empty($_SERVER['HTTP_FORWARDED'])) {
        return $_SERVER['HTTP_FORWARDED'];
    }
    
    // IP padrão
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Detecta navegador a partir do User Agent
 * 
 * @param string $userAgent - User Agent string
 * @return array - ['name', 'icon', 'color']
 */
function detectBrowser($userAgent) {
    if (strpos($userAgent, 'Chrome') !== false && strpos($userAgent, 'Chromium') === false) {
        return ['name' => 'Chrome', 'icon' => 'fa-chrome', 'color' => '#4285F4'];
    } elseif (strpos($userAgent, 'Safari') !== false && strpos($userAgent, 'Chrome') === false) {
        return ['name' => 'Safari', 'icon' => 'fa-safari', 'color' => '#000000'];
    } elseif (strpos($userAgent, 'Firefox') !== false) {
        return ['name' => 'Firefox', 'icon' => 'fa-firefox', 'color' => '#FF7139'];
    } elseif (strpos($userAgent, 'Edge') !== false || strpos($userAgent, 'Edg') !== false) {
        return ['name' => 'Edge', 'icon' => 'fa-edge', 'color' => '#0078D7'];
    } elseif (strpos($userAgent, 'Opera') !== false) {
        return ['name' => 'Opera', 'icon' => 'fa-opera', 'color' => '#FF1B2D'];
    } else {
        return ['name' => 'Desconhecido', 'icon' => 'fa-globe', 'color' => '#999999'];
    }
}

/**
 * Detecta sistema operacional a partir do User Agent
 * 
 * @param string $userAgent - User Agent string
 * @return array - ['name', 'icon', 'color']
 */
function detectOS($userAgent) {
    if (strpos($userAgent, 'Windows') !== false) {
        return ['name' => 'Windows', 'icon' => 'fa-windows', 'color' => '#0078D4'];
    } elseif (strpos($userAgent, 'Mac') !== false && strpos($userAgent, 'iPhone') === false && strpos($userAgent, 'iPad') === false) {
        return ['name' => 'macOS', 'icon' => 'fa-apple', 'color' => '#A2AAAD'];
    } elseif (strpos($userAgent, 'Linux') !== false && strpos($userAgent, 'Android') === false) {
        return ['name' => 'Linux', 'icon' => 'fa-linux', 'color' => '#FCC624'];
    } elseif (strpos($userAgent, 'Android') !== false) {
        return ['name' => 'Android', 'icon' => 'fa-android', 'color' => '#3DDC84'];
    } elseif (strpos($userAgent, 'iPhone') !== false || strpos($userAgent, 'iPad') !== false) {
        return ['name' => 'iOS', 'icon' => 'fa-apple', 'color' => '#555555'];
    } else {
        return ['name' => 'Desconhecido', 'icon' => 'fa-mobile', 'color' => '#999999'];
    }
}

/**
 * Obtém os logins recentes de um usuário
 * 
 * @param mysqli $mysqli - Conexão com o banco de dados
 * @param int $userId - ID do usuário
 * @param int $limit - Número máximo de registros
 * @return array - Array com logins
 */
function getLoginHistory($mysqli, $userId, $limit = 5) {
    $stmt = $mysqli->prepare("
        SELECT id, ip_address, user_agent, status, created_at 
        FROM login_history 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT ?
    ");
    
    if (!$stmt) {
        error_log("Erro ao preparar statement: " . $mysqli->error);
        return [];
    }
    
    $stmt->bind_param("ii", $userId, $limit);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $result;
}
?>