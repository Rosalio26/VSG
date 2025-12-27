<?php

/**
 * Gera UID único para usuários (8 números + 1 letra)
 * Compatível com o objeto $mysqli definido no seu db.php
 *
 * @param mysqli $mysqli Objeto de conexão
 * @param string $categoria 'P' para Pessoa, 'C' para Company
 * @return string UID gerado
 */
function gerarUID(mysqli $mysqli, string $categoria): string
{
    // Garante que a categoria seja apenas a inicial maiúscula (P ou C)
    $sufixo = strtoupper(substr($categoria, 0, 1));
    $existe = true;
    $tentativas = 0;

    while ($existe && $tentativas < 50) {
        $tentativas++;
        
        // Gera 8 dígitos aleatórios + sufixo
        $uid = random_int(10000000, 99999999) . $sufixo;

        // Prepara a consulta usando MySQLi
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE public_id = ? LIMIT 1");
        $stmt->bind_param('s', $uid);
        $stmt->execute();
        $stmt->store_result();
        
        // Se não encontrar linhas, o UID é único e podemos sair do loop
        if ($stmt->num_rows === 0) {
            $existe = false;
        }
        
        $stmt->close();
    }

    // Caso o banco esteja saturado (improvável, mas seguro)
    if ($existe) {
        throw new Exception("Falha ao gerar um identificador único após várias tentativas.");
    }

    return $uid;
}