<?php

/* ================= SEGURANÇA ================= */
// Impede o acesso direto a este arquivo
if (basename($_SERVER['PHP_SELF']) === 'db.php') {
    http_response_code(403);
    exit('Acesso direto não permitido.');
}

/* ================= CONFIGURAÇÃO ================= */
// Dica: Em produção, mova isso para variáveis de ambiente (.env)
$DB_HOST = 'localhost';
$DB_NAME = 'vsg';
$DB_USER = 'root';
$DB_PASS = ''; 

/* ================= ATIVAR EXCEÇÕES ================= */
// Isso faz o MySQLi lançar exceções em vez de apenas avisos silenciosos
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    /* ================= CONEXÃO ================= */
    $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

    /* ================= CHARSET ================= */
    // Garantir suporte total a caracteres internacionais e emojis
    $mysqli->set_charset('utf8mb4');

    // Define o fuso horário da conexão para o banco (opcional, mas recomendado)
    $mysqli->query("SET time_zone = '-03:00'");

} catch (mysqli_sql_exception $e) {
    // Registrar o erro real no log do servidor (privado)
    error_log('Falha na Conexão DB: ' . $e->getMessage());

    // Resposta para o usuário (pública)
    http_response_code(500);
    
    if (defined('APP_ENV') && APP_ENV === 'dev') {
        // No dev, mostramos o erro detalhado
        exit('Erro de Conexão (Dev): ' . $e->getMessage());
    } else {
        // Em produção, apenas uma mensagem genérica e segura
        exit('Estamos passando por uma instabilidade técnica. Por favor, tente novamente em alguns instantes.');
    }
}