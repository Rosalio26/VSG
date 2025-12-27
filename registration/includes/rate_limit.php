<?php

/**
 * Função de Limitação de Taxa (Rate Limiting) via Sessão.
 * Compatível com requisições AJAX e redirecionamentos amigáveis.
 */
function rateLimit(string $key, int $max, int $seconds): void
{
    // Garante que a sessão está ativa
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $now = time();

    if (!isset($_SESSION['rate'])) {
        $_SESSION['rate'] = [];
    }

    // Se a chave não existe ou o tempo expirou, reinicia o contador
    if (!isset($_SESSION['rate'][$key]) || ($now - $_SESSION['rate'][$key]['time'] > $seconds)) {
        $_SESSION['rate'][$key] = [
            'count' => 1,
            'time'  => $now
        ];
        return;
    }

    // Se excedeu o limite
    if ($_SESSION['rate'][$key]['count'] >= $max) {
        http_response_code(429);

        // Verifica se é uma requisição AJAX (JSON)
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' ||
                  strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'errors' => ['rate' => 'Muitas tentativas. Aguarde ' . $seconds . ' segundos.']
            ]);
        } else {
            // Se for acesso direto via navegador, usa o seu sistema de erro visual
            if (function_exists('errorRedirect')) {
                errorRedirect('rate');
            } else {
                exit('Muitas requisições. Tente novamente em instantes.');
            }
        }
        exit;
    }

    // Incrementa o contador
    $_SESSION['rate'][$key]['count']++;
}