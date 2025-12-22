<?php

/**
 * Gera UID único para usuários
 * 8 números + 1 letra (categoria)
 *
 * Ex:
 *  12345678P  → Pessoa
 *  87654321C  → Company
 */
function gerarUID(PDO $pdo, string $categoria): string
{
    // Garante apenas 1 letra maiúscula
    $categoria = strtoupper(substr($categoria, 0, 1));

    do {
        $uid = random_int(10000000, 99999999) . $categoria;

        $stmt = $pdo->prepare("
            SELECT 1
            FROM users
            WHERE public_id = ?
            LIMIT 1
        ");
        $stmt->execute([$uid]);

        $existe = $stmt->fetchColumn();

    } while ($existe);

    return $uid;
}
