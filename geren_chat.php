<?php
session_start();
require 'db_config.php';
require_once 'session_start_global.php';


// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// ID do usuário logado
$user_id = $_SESSION['user_id'];

// Busca informações do usuário logado
try {
    $loggedInUserQuery = $pdo->prepare("SELECT name, profile_picture, status FROM users WHERE id = ?");
    $loggedInUserQuery->execute([$user_id]);
    $loggedInUser = $loggedInUserQuery->fetch(PDO::FETCH_ASSOC);

    if (!$loggedInUser) {
        throw new Exception("Usuário não encontrado.");
    }
} catch (Exception $e) {
    die("Erro ao buscar informações do usuário: " . $e->getMessage());
}

// Atualiza a última atividade do usuário
try {
    $updateActivityQuery = $pdo->prepare("UPDATE users SET last_active = NOW() WHERE id = ?");
    $updateActivityQuery->execute([$user_id]);
} catch (PDOException $e) {
    die("Erro ao atualizar atividade: " . $e->getMessage());
}

// Atualiza o status do usuário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
    try {
        $status = $_POST['status'];
        $updateStatusQuery = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $updateStatusQuery->execute([$status, $user_id]);
        header("Refresh:0");
    } catch (PDOException $e) {
        die("Erro ao atualizar status: " . $e->getMessage());
    }
}

// Excluir conversa individual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_conversation'])) {
    try {
        $recipientId = $_POST['recipient_id'];
        $deleteConversationQuery = $pdo->prepare("DELETE FROM messages WHERE 
            (sender_id = :user_id AND receiver_id = :recipient_id) OR 
            (sender_id = :recipient_id AND receiver_id = :user_id)");
        $deleteConversationQuery->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $deleteConversationQuery->bindParam(':recipient_id', $recipientId, PDO::PARAM_INT);
        $deleteConversationQuery->execute();
        header("Refresh:0");
    } catch (PDOException $e) {
        die("Erro ao excluir conversa: " . $e->getMessage());
    }
}

// Busca todos os usuários que o usuário logado segue
try {
    $followingUsersQuery = $pdo->prepare("SELECT u.id, u.name, u.profile_picture, u.last_active, u.status,
                                          (SELECT message_text FROM messages WHERE (sender_id = u.id AND receiver_id = :user_id) 
                                           OR (sender_id = :user_id AND receiver_id = u.id) ORDER BY created_at DESC LIMIT 1) AS last_message,
                                          (SELECT COUNT(*) FROM messages WHERE sender_id = u.id AND receiver_id = :user_id AND is_read = 0) AS unread_messages
                                          FROM followers f 
                                          JOIN users u ON f.following_id = u.id 
                                          WHERE f.follower_id = :user_id");
    $followingUsersQuery->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $followingUsersQuery->execute();
    $followingUsers = $followingUsersQuery->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao buscar usuários seguidos: " . $e->getMessage());
}

// Função para formatar a última atividade
function timeAgo($datetime)
{
    if ($datetime === null) {
        return "Desconhecido";
    }
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;

    if ($diff < 60) {
        return "Agora";
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return "$minutes min atrás";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return "$hours h atrás";
    } else {
        return date('d/m/Y H:i', $timestamp);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciador de Mensagens</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/ger_chat.css">
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
                        <img src="<?= htmlspecialchars($loggedInUser['profile_picture'] ?: '/assets/default-profile.png'); ?>" alt="Foto de Perfil" class="rounded-circle" style="width: 30px; height: 30px;">
                        <?= htmlspecialchars($loggedInUser['name']); ?>
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
    <!-- Lista de Usuários -->
    <div class="container mt-5">
        <h1 class="text-center">Gerenciador de Mensagens</h1>
        <div class="user-list">
            <?php if (empty($followingUsers)): ?>
                <p class="text-muted">Você ainda não segue ninguém.</p>
            <?php else: ?>
                <?php foreach ($followingUsers as $user): ?>
                    <div class="user user-card d-flex justify-content-between align-items-center mb-3 p-3 border rounded <?= $user['status'] ?? 'offline'; ?>">
                        <div class="d-flex align-items-center">
                            <img src="<?= htmlspecialchars($user['profile_picture'] ?? 'default-profile.png'); ?>" alt="Foto do usuário" class="rounded-circle me-3" width="50" height="50">
                            <div>
                                <strong><?= htmlspecialchars($user['name'] ?? 'Usuário Desconhecido'); ?></strong>
                                <p class="text-muted mb-0 small"><?= htmlspecialchars($user['last_message'] ?? 'Nenhuma mensagem ainda'); ?></p>
                                <p class="mb-0 small"><?= timeAgo($user['last_active'] ?? null); ?></p>
                            </div>
                        </div>
                        <div>
                            <a href="chat.php?recipient_id=<?= $user['id'] ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-comments"></i> Chat
                            </a>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="recipient_id" value="<?= $user['id'] ?>">
                                <button type="submit" name="delete_conversation" class="btn btn-danger btn-sm">
                                    <i class="fas fa-trash"></i> Excluir
                                </button>
                            </form>
                            <?php if (!empty($user['unread_messages'])): ?>
                                <span class="notification-badge"><?= $user['unread_messages'] ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>