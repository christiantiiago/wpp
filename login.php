<?php
require 'db_config.php';
session_start();

// Atualiza o campo last_active para o usuário logado
if (isset($_SESSION['user_id'])) {
    try {
        $pdo->prepare("UPDATE users SET last_active = NOW() WHERE id = ?")->execute([$_SESSION['user_id']]);
    } catch (PDOException $e) {
        die("Erro ao atualizar a última atividade: " . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Busca o usuário pelo email
    try {
        $stmt = $pdo->prepare("SELECT id, password_hash, is_online FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Erro ao buscar o usuário: " . $e->getMessage());
    }

    // Verifica se o usuário existe e se a senha é válida
    if ($user && password_verify($password, $user['password_hash'])) {
        try {
            // Atualiza o status do usuário para online
            $updateOnlineStmt = $pdo->prepare("UPDATE users SET is_online = 1 WHERE id = ?");
            $updateOnlineStmt->execute([$user['id']]);

            // Cria a sessão do usuário
            $_SESSION['user_id'] = $user['id'];

            // Redireciona para a página de perfil
            header("Location: profile.php");
            exit;
        } catch (PDOException $e) {
            die("Erro ao atualizar status para online: " . $e->getMessage());
        }
    } else {
        // Exibe mensagem de erro caso as credenciais sejam inválidas
        $error = "Email ou senha incorretos.";
    }
}

// Função para marcar um usuário como offline (usada ao sair)
function setOffline($userId, $pdo)
{
    try {
        $stmt = $pdo->prepare("UPDATE users SET is_online = 0 WHERE id = ?");
        $stmt->execute([$userId]);
    } catch (PDOException $e) {
        die("Erro ao marcar o usuário como offline: " . $e->getMessage());
    }
}

// No caso de logout, define o status como offline
if (isset($_GET['logout']) && isset($_SESSION['user_id'])) {
    setOffline($_SESSION['user_id'], $pdo);
    session_destroy();
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .login-container {
            background: #fff;
            padding: 2rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }

        .login-container h1 {
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
        }

        .form-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <h1>Login</h1>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Senha</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="form-footer">
                <button type="submit" class="btn btn-primary">Entrar</button>
                <a href="password_recovery.php" class="text-decoration-none">Esqueceu a senha?</a>
            </div>
        </form>
    </div>
</body>

</html>