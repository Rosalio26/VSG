<?php

/* ================= SEGURANÇA ================= */
// Impede o acesso direto a este arquivo
if (basename($_SERVER['PHP_SELF']) === 'db.php') {
    http_response_code(403);
    exit('Acesso direto não permitido.');
}

/* ================= CONFIGURAÇÃO ================= */
$DB_HOST = 'localhost';
$DB_NAME = 'vsg';
$DB_USER = 'root';
$DB_PASS = ''; 

/* ================= SINCRONIZAÇÃO GLOBAL (UTC) ================= */
// Define o fuso horário do PHP para o padrão universal
date_default_timezone_set('UTC');

/* ================= ATIVAR EXCEÇÕES ================= */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    /* ================= CONEXÃO ================= */
    $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

    /* ================= CHARSET ================= */
    $mysqli->set_charset('utf8mb4');

    /* ================= SINCRONIZAÇÃO DO BANCO ================= */
    // Força o MySQL a ignorar o fuso horário do servidor físico e usar UTC (+00:00)
    // Isso garante que NOW() e current_timestamp() batam exatamente com o time() do PHP
    $mysqli->query("SET time_zone = '+00:00'");

} catch (mysqli_sql_exception $e) {
    error_log('Falha na Conexão DB: ' . $e->getMessage());

    http_response_code(500);
    
    if (defined('APP_ENV') && APP_ENV === 'dev') {
        exit('Erro de Conexão (Dev): ' . $e->getMessage());
    } else {
        exit('Estamos passando por uma instabilidade técnica. Por favor, tente novamente em alguns instantes.');
    }
}