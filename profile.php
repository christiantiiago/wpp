<?php
require 'db_config.php';
require_once 'session_start_global.php';


// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$isRequestPending = false; // Valor padrão

$current_user_id = $_SESSION['user_id']; // ID do usuário logado
$profile_user_id = $_GET['user_id'] ?? $current_user_id; // Perfil a ser exibido

// Busca os dados do usuário logado
$loggedInUserStmt = $pdo->prepare("SELECT name, IFNULL(profile_picture, 'default-profile.png') AS profile_picture FROM users WHERE id = ?");
$loggedInUserStmt->execute([$current_user_id]);
$loggedInUser = $loggedInUserStmt->fetch(PDO::FETCH_ASSOC);

if (!$loggedInUser) {
    die("Erro: Usuário não encontrado.");
}

// Define valor padrão para evitar "Undefined variable" no HTML
$isRequestPending = false;

// Verifica se a solicitação está pendente
$requestPendingStmt = $pdo->prepare("SELECT COUNT(*) FROM follow_requests WHERE requester_id = ? AND requested_id = ?");
$requestPendingStmt->execute([$current_user_id, $profile_user_id]);
$isRequestPending = $requestPendingStmt->fetchColumn() > 0;

// Lógica para solicitações de seguir e aceitar/rejeitar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'request_follow') {
        // Envia solicitação de seguir
        $checkRequest = $pdo->prepare("SELECT COUNT(*) FROM follow_requests WHERE requester_id = ? AND requested_id = ?");
        $checkRequest->execute([$current_user_id, $profile_user_id]);
        if ($checkRequest->fetchColumn() == 0) {
            $insertRequest = $pdo->prepare("INSERT INTO follow_requests (requester_id, requested_id) VALUES (?, ?)");
            $insertRequest->execute([$current_user_id, $profile_user_id]);
        }
    } elseif ($action === 'accept_follow' && isset($_POST['requester_id'])) {
        // Aceita solicitação de seguir
        $requester_id = $_POST['requester_id'];
        $insertFollow = $pdo->prepare("INSERT INTO followers (follower_id, following_id) VALUES (?, ?)");
        $insertFollow->execute([$requester_id, $current_user_id]);

        // Remove a solicitação aceita
        $deleteRequest = $pdo->prepare("DELETE FROM follow_requests WHERE requester_id = ? AND requested_id = ?");
        $deleteRequest->execute([$requester_id, $current_user_id]);
    } elseif ($action === 'reject_follow' && isset($_POST['requester_id'])) {
        // Rejeita solicitação de seguir
        $requester_id = $_POST['requester_id'];
        $deleteRequest = $pdo->prepare("DELETE FROM follow_requests WHERE requester_id = ? AND requested_id = ?");
        $deleteRequest->execute([$requester_id, $current_user_id]);
    } elseif ($action === 'unfollow') {
        // Deixar de seguir
        $deleteFollow = $pdo->prepare("DELETE FROM followers WHERE follower_id = ? AND following_id = ?");
        $deleteFollow->execute([$current_user_id, $profile_user_id]);
    }

    header("Location: profile.php?user_id=$profile_user_id");
    exit;
}


// Atualiza o status do usuário atual para online
$updateOnlineStatusStmt = $pdo->prepare("UPDATE users SET is_online = 1 WHERE id = ?");
$updateOnlineStatusStmt->execute([$current_user_id]);

// Busca informações do perfil
$profileStmt = $pdo->prepare("
    SELECT u.name, u.email, u.is_online, u.bio, 
           IFNULL(u.profile_picture, 'default-profile.png') AS profile_picture
    FROM users u 
    WHERE u.id = ?
");
$profileStmt->execute([$profile_user_id]);
$user = $profileStmt->fetch(PDO::FETCH_ASSOC);

// Valida se o perfil foi encontrado
if (!$user) {
    die("Erro: Usuário não encontrado.");
}

// Busca notificações do usuário logado
$notificationsStmt = $pdo->prepare("
    SELECT message, created_at, is_read
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$notificationsStmt->execute([$current_user_id]);
$notifications = $notificationsStmt->fetchAll(PDO::FETCH_ASSOC);

// Verifica se o usuário logado já segue o perfil
$followStmt = $pdo->prepare("SELECT COUNT(*) FROM followers WHERE follower_id = ? AND following_id = ?");
$followStmt->execute([$current_user_id, $profile_user_id]);
$isFollowing = $followStmt->fetchColumn() > 0;

// Conta o número de seguidores do perfil
$followersCountStmt = $pdo->prepare("SELECT COUNT(*) FROM followers WHERE following_id = ?");
$followersCountStmt->execute([$profile_user_id]);
$followersCount = $followersCountStmt->fetchColumn() ?: 0;

// Busca lista de quem o usuário logado segue
$followingStmt = $pdo->prepare("
    SELECT u.id, u.name, IFNULL(u.profile_picture, 'default-profile.png') AS profile_picture, u.is_online
    FROM followers f
    JOIN users u ON f.following_id = u.id
    WHERE f.follower_id = ?
    ORDER BY u.name ASC
");
$followingStmt->execute([$current_user_id]);
$followingUsers = $followingStmt->fetchAll(PDO::FETCH_ASSOC);

// Soma todas as curtidas nas ocorrências do perfil
$likesStmt = $pdo->prepare("SELECT SUM(likes) AS total_likes FROM occurrences WHERE user_id = ?");
$likesStmt->execute([$profile_user_id]);
$totalLikes = $likesStmt->fetchColumn() ?: 0;

// Busca as ocorrências publicadas pelo usuário
$stmt = $pdo->prepare("
    SELECT o.id, o.title, o.description, o.created_at, o.likes
    FROM occurrences o
    WHERE o.user_id = ? 
    ORDER BY o.created_at DESC
");
$stmt->execute([$profile_user_id]);
$occurrences = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepara o statement para buscar arquivos
$fileStmt = $pdo->prepare("
    SELECT file_name, file_path, file_type
    FROM files
    WHERE occurrence_id = ?
");

// Prepara o statement para buscar comentários
$commentStmt = $pdo->prepare("
    SELECT c.comment, c.created_at, u.name, 
           IFNULL(u.profile_picture, 'default-profile.png') AS profile_picture
    FROM comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.occurrence_id = ?
    ORDER BY c.created_at ASC
");

// Lógica para seguir/desseguir
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'follow') {
        $followInsertStmt = $pdo->prepare("INSERT INTO followers (follower_id, following_id) VALUES (?, ?)");
        $followInsertStmt->execute([$current_user_id, $profile_user_id]);
        $isFollowing = true;
    } elseif ($action === 'unfollow') {
        $followDeleteStmt = $pdo->prepare("DELETE FROM followers WHERE follower_id = ? AND following_id = ?");
        $followDeleteStmt->execute([$current_user_id, $profile_user_id]);
        $isFollowing = false;
    }

    header("Location: profile.php?user_id=$profile_user_id");
    exit;
}

// Impede múltiplas curtidas na mesma ocorrência
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['like_occurrence_id'])) {
    $occurrence_id = $_POST['like_occurrence_id'];

    $likeCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE user_id = ? AND occurrence_id = ?");
    $likeCheckStmt->execute([$current_user_id, $occurrence_id]);
    $alreadyLiked = $likeCheckStmt->fetchColumn() > 0;

    if (!$alreadyLiked) {
        $likeInsertStmt = $pdo->prepare("INSERT INTO likes (user_id, occurrence_id) VALUES (?, ?)");
        $likeInsertStmt->execute([$current_user_id, $occurrence_id]);

        $updateLikesStmt = $pdo->prepare("UPDATE occurrences SET likes = likes + 1 WHERE id = ?");
        $updateLikesStmt->execute([$occurrence_id]);
    }
}

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil de <?= htmlspecialchars($user['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/perfil.css">
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
                    <a class="btn btn-secondary dropdown-toggle d-flex align-items-center" href="#" role="button" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="<?= htmlspecialchars($loggedInUser['profile_picture']); ?>" alt="Foto de Perfil" class="rounded-circle me-2" style="width: 30px; height: 30px;">
                        <?= htmlspecialchars($loggedInUser['name']); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
                        <li><a class="dropdown-item" href="profile.php "><i class="fas fa-user"></i> Meu Perfil</a></li>
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
    <div class="profile-header mb-4 p-4 shadow-sm rounded bg-light d-flex align-items-center">
        <!-- Imagem do Perfil -->
        <div class="me-4">
            <img src="<?= htmlspecialchars($user['profile_picture']); ?>" alt="Foto de Perfil"
                class="rounded-circle shadow" style="width: 120px; height: 120px; object-fit: cover;">
        </div>

        <!-- Informações do Usuário -->
        <div class="flex-grow-1">
            <h1 class="h3 mb-2"><?= htmlspecialchars($user['name']); ?></h1>
            <p class="mb-1 text-muted"><strong>Email:</strong> <?= htmlspecialchars($user['email']); ?></p>
            <p class="mb-1 text-muted"><strong>Bio:</strong> <?= htmlspecialchars($user['bio'] ?: 'Nenhuma bio disponível.'); ?></p>
            <p class="mb-3">
                <span class="badge bg-primary me-2">Seguidores: <?= $followersCount; ?></span>
                <span class="badge bg-success">Curtidas: <?= $totalLikes; ?></span>
            </p>

            <!-- Botões de Ação -->
            <div>
                <?php if ($current_user_id === $profile_user_id): ?>
                    <!-- Botão de Editar Perfil -->
                    <a href="edit_profile.php" class="btn btn-outline-primary btn-sm me-2">Editar Perfil</a>

                    <!-- Solicitações Pendentes -->
                    <?php if (!empty($followRequests)): ?>
                        <h5 class="mt-3">Solicitações de Seguidores</h5>
                        <ul class="list-group">
                            <?php foreach ($followRequests as $request): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <img src="<?= htmlspecialchars($request['profile_picture']); ?>" alt="Perfil"
                                            class="rounded-circle me-2" style="width: 40px; height: 40px;">
                                        <span><?= htmlspecialchars($request['name']); ?></span>
                                    </div>
                                    <div>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="accept_follow">
                                            <input type="hidden" name="requester_id" value="<?= $request['requester_id']; ?>">
                                            <button type="submit" class="btn btn-success btn-sm">Aceitar</button>
                                        </form>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="reject_follow">
                                            <input type="hidden" name="requester_id" value="<?= $request['requester_id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Rejeitar</button>
                                        </form>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                <?php else: ?>
                    <!-- Ações para Visitantes -->
                    <div class="mt-3">
                        <?php if ($isFollowing): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="unfollow">
                                <button type="submit" class="btn btn-danger btn-sm">Deixar de Seguir</button>
                            </form>
                        <?php elseif ($isRequestPending): ?>
                            <button class="btn btn-warning btn-sm" disabled>Solicitação Enviada</button>
                        <?php else: ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="request_follow">
                                <button type="submit" class="btn btn-primary btn-sm">Enviar Solicitação</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>


    <hr>

    <h2>Ocorrências Publicadas</h2>
    <?php if (!empty($occurrences)): ?>
        <?php foreach ($occurrences as $occurrence): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="card-title">🔖 <?= htmlspecialchars($occurrence['title']); ?></h5>
                    <h6 class="card-subtitle mb-2 text-muted">
                        Publicado em <?= date('d/m/Y H:i', strtotime($occurrence['created_at'])); ?> | Curtidas: <?= $occurrence['likes']; ?>
                    </h6>
                    <p class="card-text occurrence-content"><?= nl2br(htmlspecialchars($occurrence['description'])); ?></p>

                    <h6>Arquivos:</h6>
                    <?php
                    $fileStmt->execute([$occurrence['id']]);
                    $files = $fileStmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <?php if (!empty($files)): ?>
                        <div class="file-list">
                            <?php foreach ($files as $file): ?>
                                <?php if (strpos($file['file_type'], 'image') !== false): ?>
                                    <img src="<?= htmlspecialchars($file['file_path']); ?>" alt="Imagem" class="img-fluid mb-2">
                                <?php elseif (strpos($file['file_type'], 'video') !== false): ?>
                                    <video controls class="media-preview">
                                        <source src="<?= htmlspecialchars($file['file_path']); ?>" type="<?= htmlspecialchars($file['file_type']); ?>">
                                        Seu navegador não suporta o elemento de vídeo.
                                    </video>

                                <?php else: ?>
                                    <a href="<?= htmlspecialchars($file['file_path']); ?>" target="_blank" class="btn btn-link"><?= htmlspecialchars($file['file_name']); ?></a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">Nenhum arquivo anexado.</p>
                    <?php endif; ?>
                    <div class="like-comment mt-3">
                        <span class="like-btn" data-id="<?= $occurrence['id']; ?>">👍 Curtidas (<span id="likes-<?= $occurrence['id']; ?>"><?= $occurrence['likes']; ?></span>)</span>
                    </div>
                    <br>
                    <h6>Comentários:</h6>
                    <?php
                    $commentStmt->execute([$occurrence['id']]);
                    $comments = $commentStmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <?php if (!empty($comments)): ?>
                        <?php foreach ($comments as $comment): ?>
                            <div class="d-flex align-items-start mb-3">
                                <img src="<?= htmlspecialchars($comment['profile_picture']); ?>" alt="Comentador" class="rounded-circle me-3" style="width: 40px; height: 40px;">
                                <div>
                                    <strong><?= htmlspecialchars($comment['name']); ?></strong>
                                    <p><?= nl2br(htmlspecialchars($comment['comment'])); ?></p>
                                    <small class="text-muted"><?= date('d/m/Y H:i', strtotime($comment['created_at'])); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>

                    <?php else: ?>
                        <p class="text-muted">Nenhum comentário ainda.</p>
                    <?php endif; ?>


                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="alert alert-info">Nenhuma ocorrência publicada.</div>
    <?php endif; ?>
    </div>
    <button class="btn btn-primary toggle-following">👥 Seguindo</button>
    <div class="following-list">
        <?php if (!empty($followingUsers)): ?>
            <div class="list-group">
                <?php foreach ($followingUsers as $person): ?>
                    <a href="profile.php?user_id=<?= $person['id']; ?>" class="list-group-item list-group-item-action d-flex align-items-center">
                        <img src="<?= htmlspecialchars($person['profile_picture']); ?>" alt="Foto de Perfil"
                            class="rounded-circle me-3" style="width: 50px; height: 50px; object-fit: cover;">
                        <div class="d-flex justify-content-between align-items-center w-100">
                            <div>
                                <h5 class="mb-0"><?= htmlspecialchars($person['name']); ?></h5>
                                <small class="<?= $person['is_online'] ? 'text-success' : 'text-muted'; ?>">
                                    <?= $person['is_online'] ? 'Online' : 'Offline'; ?>
                                </small>
                            </div>
                            <span class="online-status <?= $person['is_online'] ? 'online' : 'offline'; ?>"
                                title="<?= $person['is_online'] ? 'Usuário está online' : 'Usuário está offline'; ?>">
                                <i class="bi <?= $person['is_online'] ? 'bi-circle-fill text-success' : 'bi-circle text-muted'; ?>"></i>
                            </span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted text-center">Você ainda não está seguindo ninguém.</p>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.querySelector('.toggle-following').addEventListener('click', function() {
            document.querySelector('.following-list').classList.toggle('active');
        });
    </script>
</body>

</html>