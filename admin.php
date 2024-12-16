<?php
require 'db_config.php';
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Obtém o ID do usuário logado
$user_id = $_SESSION['user_id'];

// Busca informações do usuário logado
$currentUserStmt = $pdo->prepare("
    SELECT name, email, role, IFNULL(profile_picture, 'default-profile.png') AS profile_picture 
    FROM users 
    WHERE id = ?
");
$currentUserStmt->execute([$user_id]);
$currentUser = $currentUserStmt->fetch(PDO::FETCH_ASSOC);

if (!$currentUser) {
    die("Erro: Usuário logado não encontrado.");
}
$stmt = $pdo->query("SELECT id, name, email, role, IFNULL(is_active, TRUE) AS is_active FROM users ORDER BY name ASC");

// Verifica se o usuário é administrador
if ($currentUser['role'] !== 'admin') {
    echo "<div class='alert alert-danger'>Acesso negado. Apenas administradores podem acessar esta página.</div>";
    exit;
}

// Mensagem de feedback
$message = '';

// Função para registrar logs
function logAction($pdo, $userId, $action, $description)
{
    $logStmt = $pdo->prepare("INSERT INTO logs (user_id, action, description) VALUES (?, ?, ?)");
    $logStmt->execute([$userId, $action, $description]);
}

// Processamento de ações do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update' && isset($_POST['user_id'])) {
        $updateUserId = $_POST['user_id'];
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "<div class='alert alert-danger'>E-mail inválido!</div>";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?");
                $stmt->execute([$name, $email, $role, $updateUserId]);
                logAction($pdo, $user_id, 'update_user', "Atualizou o usuário $name com ID $updateUserId");
                $message = "<div class='alert alert-success'>Usuário atualizado com sucesso!</div>";
            } catch (PDOException $e) {
                $message = "<div class='alert alert-danger'>Erro ao atualizar o usuário: " . $e->getMessage() . "</div>";
            }
        }
    } elseif ($_POST['action'] === 'toggle_status' && isset($_POST['user_id'])) {
        $toggleUserId = $_POST['user_id'];
        try {
            $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([$toggleUserId]);
            logAction($pdo, $user_id, 'toggle_status', "Alterou o status do usuário com ID $toggleUserId");
            $message = "<div class='alert alert-success'>Status do usuário alterado com sucesso!</div>";
        } catch (PDOException $e) {
            $message = "<div class='alert alert-danger'>Erro ao alterar status do usuário: " . $e->getMessage() . "</div>";
        }
    } elseif ($_POST['action'] === 'export') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename=usuarios.csv');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Nome', 'E-mail', 'Papel', 'Ativo']);

        $stmt = $pdo->query("SELECT id, name, email, role, is_active FROM users");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }
}

// Busca de usuários para exibição
$users = [];
try {
    $stmt = $pdo->query("SELECT id, name, email, role, IFNULL(is_active, TRUE) AS is_active FROM users ORDER BY name ASC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "<div class='alert alert-danger'>Erro ao buscar usuários: " . $e->getMessage() . "</div>";
}

// Estatísticas básicas
try {
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalAdmins = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    $totalLogs = $pdo->query("SELECT COUNT(*) FROM logs")->fetchColumn();
} catch (PDOException $e) {
    $message = "<div class='alert alert-danger'>Erro ao buscar estatísticas: " . $e->getMessage() . "</div>";
}

// Logs de atividades
$logs = [];
try {
    $stmt = $pdo->query("SELECT l.id, l.action, l.description, l.created_at, u.name AS user_name 
                         FROM logs l
                         JOIN users u ON l.user_id = u.id
                         ORDER BY l.created_at DESC LIMIT 50");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "<div class='alert alert-danger'>Erro ao buscar logs: " . $e->getMessage() . "</div>";
}

// Verifica se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'register') {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $role = $_POST['role'] ?? 'user';

        if (!empty($name) && !empty($email) && !empty($password)) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = "<div class='alert alert-danger'>E-mail inválido!</div>";
            } else {
                try {
                    // Verifica se o e-mail já está cadastrado
                    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                    $checkStmt->execute([$email]);

                    if ($checkStmt->rowCount() > 0) {
                        $message = "<div class='alert alert-danger'>E-mail já cadastrado!</div>";
                    } else {
                        // Insere o novo usuário
                        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                        $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$name, $email, $passwordHash, $role]);

                        // Registra no log
                        logAction($pdo, $user_id, 'register_user', "Registrou o usuário $name com o papel $role");

                        $message = "<div class='alert alert-success'>Usuário registrado com sucesso!</div>";
                    }
                } catch (PDOException $e) {
                    $message = "<div class='alert alert-danger'>Erro ao registrar o usuário: " . $e->getMessage() . "</div>";
                }
            }
        } else {
            $message = "<div class='alert alert-danger'>Preencha todos os campos!</div>";
        }
    } elseif ($_POST['action'] === 'delete' && isset($_POST['user_id'])) {
        $deleteUserId = $_POST['user_id'];

        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$deleteUserId]);

            // Registra no log
            logAction($pdo, $user_id, 'delete_user', "Excluiu o usuário com ID $deleteUserId");

            $message = "<div class='alert alert-success'>Usuário excluído com sucesso!</div>";
        } catch (PDOException $e) {
            $message = "<div class='alert alert-danger'>Erro ao excluir o usuário: " . $e->getMessage() . "</div>";
        }
    }
}

?>


<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Gerenciar Usuários</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/admin.css">
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">Meu Sistema</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'follow_requests.php' ? 'active' : ''; ?>" href="follow_requests.php">
                            <i class="fas fa-user-friends"></i> Pedidos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'fed.php' ? 'active' : ''; ?>" href="fed.php">
                            <i class="fas fa-home"></i> Feed
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'form_ocorrencia.php' ? 'active' : ''; ?>" href="form_ocorrencia.php">
                            <i class="fas fa-plus-circle"></i> Registrar Ocorrência
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'gerenciar_ocorrencias.php' ? 'active' : ''; ?>" href="gerenciar_ocorrencias.php">
                            <i class="fas fa-cogs"></i> Gerenciar
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : ''; ?>" href="categories.php">
                            <i class="fas fa-th-list"></i> Categorias
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'relatorios.php' ? 'active' : ''; ?>" href="relatorios.php">
                            <i class="fas fa-chart-bar"></i> Relatórios
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'search_user.php' ? 'active' : ''; ?>" href="search_user.php">
                            <i class="fas fa-search"></i> Procurar
                        </a>
                    </li>
                </ul>
                <div class="dropdown">
                    <a class="btn btn-secondary dropdown-toggle" href="#" role="button" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="<?= htmlspecialchars($currentUser['profile_picture'] ?: '/assets/default-profile.png'); ?>" alt="Foto de Perfil" class="rounded-circle" style="width: 30px; height: 30px;">
                        <?= htmlspecialchars($currentUser['name']); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user"></i> Meu Perfil</a></li>
                        <li><a class="dropdown-item" href="admin.php"><i class="fas fa-user-shield"></i> Admin</a></li>
                        <li><a class="dropdown-item" href="configuracoes.php"><i class="fas fa-cogs"></i> Configurações</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
                    </ul>
                </div>

            </div>
        </div>
    </nav>
    <div class="container mt-5">
        <h1 class="mb-4">Administração</h1>
        <?= $message; ?>

        <h2 class="mt-4">Estatísticas</h2>
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <h5 class="card-title">Total de Usuários</h5>
                        <p class="card-text"><?= $totalUsers; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <h5 class="card-title">Administradores</h5>
                        <p class="card-text"><?= $totalAdmins; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-secondary">
                    <div class="card-body">
                        <h5 class="card-title">Total de Logs</h5>
                        <p class="card-text"><?= $totalLogs; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <h2 class="mt-4">Lista de Usuários</h2>
        <form method="POST">
            <input type="hidden" name="action" value="export">
            <button type="submit" class="btn btn-secondary mb-3">Exportar para CSV</button>
        </form>
        <div class="table-wrapper">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th>Papel</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['id']); ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="text" name="name" value="<?= htmlspecialchars($user['name']); ?>" class="form-control form-control-sm">
                            </td>
                            <td>
                                <input type="text" name="email" value="<?= htmlspecialchars($user['email']); ?>" class="form-control form-control-sm">
                            </td>
                            <td>
                                <select name="role" class="form-select form-select-sm">
                                    <option value="user" <?= $user['role'] === 'user' ? 'selected' : ''; ?>>Usuário</option>
                                    <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                                </select>
                            </td>
                            <td><?= $user['is_active'] ? 'Ativo' : 'Inativo'; ?></td>
                            <td>
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="user_id" value="<?= $user['id']; ?>">
                                <button type="submit" class="btn btn-primary btn-sm">Salvar</button>
                                </form>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="user_id" value="<?= $user['id']; ?>">
                                    <button type="submit" class="btn btn-warning btn-sm">
                                        <?= $user['is_active'] ? 'Desativar' : 'Ativar'; ?>
                                    </button>
                                </form>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?= $user['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Excluir</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="logs-wrapper">
            <table class="table">
                <h2 class="mt-4">Registrar Novo Usuário</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="register">
                    <div class="mb-3">
                        <label for="name" class="form-label">Nome</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Senha</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="role" class="form-label">Papel</label>
                        <select class="form-select" id="role" name="role">
                            <option value="user">Usuário</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Registrar</button>
                </form>
            </table>
        </div>
        <div class="logs-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Ação</th>
                        <th>Descrição</th>
                        <th>Usuário</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= htmlspecialchars($log['id']); ?></td>
                            <td><?= htmlspecialchars($log['action']); ?></td>
                            <td><?= htmlspecialchars($log['description']); ?></td>
                            <td><?= htmlspecialchars($log['user_name']); ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($log['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>