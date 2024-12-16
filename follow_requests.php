<?php
require 'db_config.php';
require_once 'session_start_global.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];
$feedbackMessage = "";

// Busca informações do usuário logado
$userStmt = $pdo->prepare("SELECT name, profile_picture FROM users WHERE id = ?");
$userStmt->execute([$user_id]);
$loggedInUser = $userStmt->fetch(PDO::FETCH_ASSOC);


// Processa ações (aceitar, rejeitar solicitações, bloquear usuário)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    $requester_id = $_POST['requester_id'] ?? null;

    if ($action && $requester_id) {
        try {
            if ($action === 'accept_follow') {
                // Aceitar solicitação
                $acceptStmt = $pdo->prepare("INSERT INTO followers (follower_id, following_id) VALUES (?, ?)");
                $acceptStmt->execute([$requester_id, $current_user_id]);

                // Remove a solicitação após aceitar
                $deleteRequestStmt = $pdo->prepare("DELETE FROM follow_requests WHERE requester_id = ? AND requested_id = ?");
                $deleteRequestStmt->execute([$requester_id, $current_user_id]);
                $feedbackMessage = "Solicitação aceita com sucesso!";
            } elseif ($action === 'reject_follow') {
                // Rejeitar solicitação
                $deleteRequestStmt = $pdo->prepare("DELETE FROM follow_requests WHERE requester_id = ? AND requested_id = ?");
                $deleteRequestStmt->execute([$requester_id, $current_user_id]);
                $feedbackMessage = "Solicitação rejeitada com sucesso!";
            } elseif ($action === 'block_user') {
                // Bloquear usuário
                $blockStmt = $pdo->prepare("INSERT INTO blocked_users (user_id, blocked_user_id) VALUES (?, ?)");
                $blockStmt->execute([$current_user_id, $requester_id]);

                // Remove solicitações do usuário bloqueado
                $deleteRequestStmt = $pdo->prepare("DELETE FROM follow_requests WHERE requester_id = ? AND requested_id = ?");
                $deleteRequestStmt->execute([$requester_id, $current_user_id]);
                $feedbackMessage = "Usuário bloqueado com sucesso!";
            }
        } catch (PDOException $e) {
            $feedbackMessage = "Erro ao processar a solicitação: " . $e->getMessage();
        }
    }
}

// Busca todas as solicitações pendentes
$followRequestsStmt = $pdo->prepare("
    SELECT r.requester_id, u.name, u.is_online, 
           IFNULL(u.profile_picture, 'default-profile.png') AS profile_picture, 
           u.bio 
    FROM follow_requests r
    JOIN users u ON r.requester_id = u.id
    WHERE r.requested_id = ?
");
$followRequestsStmt->execute([$current_user_id]);
$followRequests = $followRequestsStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitações de Seguidores</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/solicitacao.css">
    <script>
        function confirmAction(message, form) {
            if (confirm(message)) {
                form.submit();
            }
        }
    </script>
</head>

<body class="bg-light">
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
                    <a class="btn btn-secondary dropdown-toggle d-flex align-items-center" href="#" role="button" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="<?= htmlspecialchars($loggedInUser['profile_picture'] ?: '/assets/default-avatar.png'); ?>" alt="Foto de perfil" class="rounded-circle me-2" style="width: 30px; height: 30px;">
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


    <div class="container mt-5">
        <h1 class="mb-4 text-center text-primary">Solicitações de Seguidores</h1>

        <?php if (!empty($feedbackMessage)): ?>
            <div class="alert alert-info"><?= htmlspecialchars($feedbackMessage); ?></div>
        <?php endif; ?>

        <?php if (!empty($followRequests)): ?>
            <ul class="list-group">
                <?php foreach ($followRequests as $request): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center shadow-sm">
                        <div class="d-flex align-items-center">
                            <img src="<?= htmlspecialchars($request['profile_picture']); ?>" alt="Perfil"
                                class="rounded-circle me-3" style="width: 50px; height: 50px; object-fit: cover;">
                            <div>
                                <strong><?= htmlspecialchars($request['name']); ?></strong>
                                <p class="text-muted small"><?= htmlspecialchars($request['bio'] ?: 'Sem biografia.'); ?></p>
                                <span class="<?= $request['is_online'] ? 'text-success' : 'text-muted'; ?>">
                                    <?= $request['is_online'] ? 'Online' : 'Offline'; ?>
                                </span>
                            </div>
                        </div>
                        <div>
                            <a href="profile.php?user_id=<?= $request['requester_id']; ?>" class="btn btn-info btn-sm">Ver Perfil</a>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="accept_follow">
                                <input type="hidden" name="requester_id" value="<?= $request['requester_id']; ?>">
                                <button type="button" onclick="confirmAction('Aceitar esta solicitação?', this.form);" class="btn btn-success btn-sm">Aceitar</button>
                            </form>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="reject_follow">
                                <input type="hidden" name="requester_id" value="<?= $request['requester_id']; ?>">
                                <button type="button" onclick="confirmAction('Rejeitar esta solicitação?', this.form);" class="btn btn-danger btn-sm">Rejeitar</button>
                            </form>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="block_user">
                                <input type="hidden" name="requester_id" value="<?= $request['requester_id']; ?>">
                                <button type="button" onclick="confirmAction('Bloquear este usuário?', this.form);" class="btn btn-dark btn-sm">Bloquear</button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="alert alert-info text-center shadow-sm">Nenhuma solicitação pendente no momento.</div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>