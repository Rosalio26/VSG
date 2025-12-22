<?php

/* ================= CONFIG ================= */

$DB_HOST = 'localhost';
$DB_NAME = 'vsg';
$DB_USER = 'root';
$DB_PASS = ''; // coloque a senha real aqui

/* ================= CONEXÃO ================= */

$mysqli = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

/* ================= ERRO DE CONEXÃO ================= */

if ($mysqli->connect_errno) {

    if (defined('APP_ENV') && APP_ENV === 'dev') {
        exit('Erro de conexão: ' . $mysqli->connect_error);
    }

    http_response_code(500);
    exit('Erro interno. Tente novamente mais tarde.');
}

/* ================= CHARSET ================= */

if (!$mysqli->set_charset('utf8mb4')) {

    if (defined('APP_ENV') && APP_ENV === 'dev') {
        exit('Erro ao definir charset: ' . $mysqli->error);
    }

    http_response_code(500);
    exit('Erro interno. Tente novamente mais tarde.');
}
