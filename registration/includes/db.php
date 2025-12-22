<?php

/* ================= CONFIG ================= */

$DB_HOST = 'localhost';
$DB_NAME = 'vsg';
$DB_USER = 'root';
$DB_PASS = ''; // coloque a senha real aqui

/* ================= DSN ================= */

$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";

/* ================= OPÇÕES PDO ================= */

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // lança exceções
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // array associativo
    PDO::ATTR_EMULATE_PREPARES   => false,                   // prepared real
];

/* ================= CONEXÃO ================= */

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {

    // Em DEV mostra erro
    if (defined('APP_ENV') && APP_ENV === 'dev') {
        exit('Erro de conexão: ' . $e->getMessage());
    }

    // Em PROD não expõe nada
    http_response_code(500);
    exit('Erro interno. Tente novamente mais tarde.');
}
