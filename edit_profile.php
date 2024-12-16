<?php
session_start();
require 'db_config.php';
require_once 'session_start_global.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Busca informações do usuário logado na tabela users
$userStmt = $pdo->prepare("
    SELECT 
        name, 
        email, 
        IFNULL(profile_picture, 'default-profile.png') AS profile_picture, 
        bio 
    FROM 
        users 
    WHERE 
        id = ?
");
$userStmt->execute([$user_id]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Usuário não encontrado.");
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $bio = trim($_POST['bio']);

    try {
        // Atualiza as informações do usuário na tabela users
        $updateUserStmt = $pdo->prepare("
            UPDATE users 
            SET name = ?, email = ?, bio = ? 
            WHERE id = ?
        ");
        $updateUserStmt->execute([$name, $email, $bio, $user_id]);

        // Verifica se foi enviado um novo arquivo de foto de perfil
        if (!empty($_FILES['profile_pic']['tmp_name'])) {
            $profilePicPath = 'uploads/profile_pics/';
            $fileName = uniqid() . '-' . basename($_FILES['profile_pic']['name']);
            $filePath = $profilePicPath . $fileName;

            // Cria o diretório de uploads, se não existir
            if (!is_dir($profilePicPath)) {
                mkdir($profilePicPath, 0777, true);
            }

            // Move o arquivo enviado para o diretório de uploads
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $filePath)) {
                // Remove a foto de perfil antiga, se existir
                if (!empty($user['profile_picture']) && file_exists($user['profile_picture']) && $user['profile_picture'] !== 'default-profile.png') {
                    unlink($user['profile_picture']);
                }

                // Atualiza o caminho da nova foto de perfil no banco de dados
                $updateProfilePicStmt = $pdo->prepare("
                    UPDATE users 
                    SET profile_picture = ? 
                    WHERE id = ?
                ");
                $updateProfilePicStmt->execute([$filePath, $user_id]);

                $user['profile_picture'] = $filePath; // Atualiza na variável $user
            } else {
                $error = "Erro ao salvar a foto de perfil.";
            }
        }

        if (empty($error)) {
            $success = "Perfil atualizado com sucesso!";
        }
    } catch (PDOException $e) {
        $error = "Erro ao atualizar perfil: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Perfil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
        }

        .profile-pic {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
        }
    </style>
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
    <br>

    <!-- Conteúdo -->
    <div class="container mt-5">
        <h1 class="mb-4">Editar Perfil</h1>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success); ?></div>
        <?php elseif (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="name" class="form-label">Nome</label>
                <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($user['name']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="bio" class="form-label">Bio</label>
                <textarea class="form-control" id="bio" name="bio" rows="3"><?= htmlspecialchars($user['bio']); ?></textarea>
            </div>
            <div class="mb-3">
                <label for="profile_pic" class="form-label">Foto de Perfil</label>
                <?php if (!empty($user['profile_picture'])): ?>
                    <div class="mb-2">
                        <img src="<?= htmlspecialchars($user['profile_picture']); ?>" alt="Foto de Perfil" class="profile-pic">
                    </div>
                <?php endif; ?>
                <input type="file" class="form-control" id="profile_pic" name="profile_pic" accept="image/*">
            </div>
            <button type="submit" class="btn btn-primary">Salvar</button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>