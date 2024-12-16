<?php
require 'db_config.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';

    // Verifica se o email existe
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Gera um token único
        $token = bin2hex(random_bytes(16));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Salva o token no banco
        $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$email, $token, $expires]);

        // Envia o email com o link de redefinição
        $resetLink = "http://yourdomain.com/reset_password.php?token=$token";
        mail($email, "Recuperação de Senha", "Clique no link para redefinir sua senha: $resetLink");

        $message = "<div class='alert alert-success'>Email enviado com as instruções para redefinir sua senha.</div>";
    } else {
        $message = "<div class='alert alert-danger'>Email não encontrado.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Recuperação de Senha</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/recovery.css">
</head>
<body>
<div class="container mt-5">
    <h1 class="mb-4">Recuperação de Senha</h1>
    <?= $message; ?>
    <form method="POST">
        <div class="mb-3">
            <label for="email" class="form-label">Digite seu email</label>
            <input type="email" class="form-control" id="email" name="email" required>
        </div>
        <button type="submit" class="btn btn-primary">Enviar</button>
    </form>
</div>
</body>
</html>
