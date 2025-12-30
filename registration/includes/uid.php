<?php

/**
 * Gera UID único para usuários (8 números + 1 letra)
 * Respeita a CONSTRAINT chk_public_id: regexp '^[0-9]{8}[PC]$'
 *
 * @param mysqli $mysqli Objeto de conexão
 * @param string $categoria 'P', 'C', 'business', 'company' ou 'person'
 * @return string UID gerado
 */
function gerarUID(mysqli $mysqli, string $categoria): string
{
    // Normalização robusta: transforma em maiúscula para facilitar a verificação
    $input = strtoupper(trim($categoria));

    /**
     * LÓGICA DE DEFINIÇÃO DO SUFIXO:
     * Se começar com 'B' (Business) ou 'C' (Company), o sufixo é 'C'.
     * Caso contrário, assume-se 'P' (Person/Pessoa).
     */
    $primeiraLetra = substr($input, 0, 1);
    
    if ($primeiraLetra === 'C' || $primeiraLetra === 'B') {
        $sufixo = 'C';
    } else {
        $sufixo = 'P';
    }

    $existe = true;
    $tentativas = 0;
    $uid = "";

    // Loop de tentativa para garantir unicidade no banco de dados
    while ($existe && $tentativas < 50) {
        $tentativas++;
        
        // Gera exatamente 8 dígitos aleatórios + a letra identificadora
        // random_int é criptograficamente seguro
        $uid = random_int(10000000, 99999999) . $sufixo;

        // Prepara a consulta usando MySQLi para verificar se este UID já existe
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE public_id = ? LIMIT 1");
        $stmt->bind_param('s', $uid);
        $stmt->execute();
        $stmt->store_result();
        
        // Se o número de linhas for 0, encontramos um código disponível
        if ($stmt->num_rows === 0) {
            $existe = false;
        }
        
        $stmt->close();
    }

    // Se após 50 tentativas ainda houver colisão (teoricamente impossível com 8 dígitos)
    if ($existe) {
        throw new Exception("Falha ao gerar um identificador único: Sistema saturado.");
    }

    return $uid;
}