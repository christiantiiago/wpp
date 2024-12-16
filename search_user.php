<?php
require 'db_config.php';
require_once 'session_start_global.php'; // Já contém session_start()

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Obtém o ID do usuário logado
$user_id = $_SESSION['user_id'];

// Busca informações do usuário logado
$userStmt = $pdo->prepare("
    SELECT name, email, IFNULL(profile_picture, 'default-profile.png') AS profile_picture 
    FROM users 
    WHERE id = ?
");
$userStmt->execute([$user_id]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Erro: Usuário logado não encontrado.");
}

// Obtem o termo de busca
$searchTerm = isset($_GET['q']) ? trim($_GET['q']) : '';

// Query para buscar usuários
$users = [];
if ($searchTerm) {
    $stmt = $pdo->prepare("
        SELECT id, name, email, IFNULL(profile_picture, 'default-profile.png') AS profile_picture 
        FROM users 
        WHERE name LIKE ? OR email LIKE ? 
        ORDER BY name ASC
    ");
    $stmt->execute(["%$searchTerm%", "%$searchTerm%"]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buscar Usuários</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/procurar.css">
</head>

<body>
    <!-- Navbar -->
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
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'geren_chat.php' ? 'active' : ''; ?>" href="geren_chat.php">
                            <i class="fas fa-comments"></i> Chat
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
                        <img src="<?= htmlspecialchars($user['profile_picture'] ?: '/assets/default-avatar.png'); ?>" alt="Foto de Perfil" class="rounded-circle" style="width: 30px; height: 30px;">
                        <?= htmlspecialchars($user['name']); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user"></i> Meu Perfil</a></li>
                        <li><a class="dropdown-item" href="admin.php"><i class="fas fa-user-shield"></i> Admin</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cogs"></i> Configurações</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
    <br><br>
    <div class="container">
        <h1>Buscar Usuários</h1>
        <form method="GET" class="search-container">
            <div class="input-group mb-3">
                <input type="text" class="form-control" name="q" placeholder="Digite o nome ou email..." value="<?= htmlspecialchars($searchTerm); ?>" required>
                <button class="btn" type="submit">Buscar</button>
            </div>
        </form>

        <?php if ($searchTerm && empty($users)): ?>
            <div class="alert alert-warning">Nenhum usuário encontrado para "<?= htmlspecialchars($searchTerm); ?>"</div>
        <?php endif; ?>

        <?php if (!empty($users)): ?>
            <div class="user-list">
                <?php foreach ($users as $user): ?>
                    <div class="user-card">
                        <img src="<?= htmlspecialchars($user['profile_picture']); ?>" alt="Foto de Perfil">
                        <div>
                            <h5><?= htmlspecialchars($user['name']); ?></h5>
                            <p><?= htmlspecialchars($user['email']); ?></p>
                            <a href="profile.php?user_id=<?= $user['id']; ?>" class="btn btn-primary mt-2">Ver Perfil</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>