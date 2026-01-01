<?php
// Ajuste o caminho conforme a sua estrutura
require_once '../../registration/includes/db.php'; 

// Dados do administrador seguindo a regra do REGEXP: ^[0-9]{8}[PC]$
$nome = "Administrador Geral";
$email = "admin@visiongreen.com";
$telefone = "+000000000"; 
$password = "Mudar@123456"; 
$hash = password_hash($password, PASSWORD_BCRYPT);
$type = "company"; 
$role = "superadmin";
$status = "active";

// O ID precisa ter 8 números + 'C' para passar no CHECK do banco
$public_id = "00000000C"; 

$stmt = $mysqli->prepare("
    INSERT INTO users (
        nome, 
        email, 
        telefone, 
        password_hash, 
        type, 
        role, 
        status, 
        public_id, 
        email_verified_at,
        registration_step
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'completed')
");

$stmt->bind_param("ssssssss", $nome, $email, $telefone, $hash, $type, $role, $status, $public_id);

if ($stmt->execute()) {
    echo "<div style='font-family: sans-serif; padding: 20px; border: 2px solid #00a63e; border-radius: 10px; max-width: 500px; margin: 50px auto; background-color: #f0fff4;'>";
    echo "<h2 style='color: #00a63e;'>✅ Administrador criado com sucesso!</h2>";
    echo "<p><strong>Public ID:</strong> $public_id</p>";
    echo "<p><strong>E-mail:</strong> $email</p>";
    echo "<p><strong>Senha:</strong> $password</p>";
    echo "<hr style='border: 0; border-top: 1px solid #c6f6d5;'>";
    echo "<p style='color: #ff3232;'><strong>Atenção:</strong> Apague este ficheiro imediatamente.</p>";
    echo "</div>";
} else {
    echo "❌ Erro: " . $mysqli->error;
}
$stmt->close();
?>