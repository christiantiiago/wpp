<?php
// Inicia a sessão apenas se ainda não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'db_config.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
// Carrega as configurações do usuário
if (!isset($_SESSION['user_settings'])) {
    try {
        $query = $pdo->prepare("SELECT language, theme, notifications, privacy, timezone FROM users WHERE id = ?");
        $query->execute([$user_id]);
        $userSettings = $query->fetch(PDO::FETCH_ASSOC);

        if ($userSettings) {
            $_SESSION['user_settings'] = $userSettings;
        } else {
            throw new Exception("Configurações não encontradas.");
        }
    } catch (Exception $e) {
        die("Erro ao carregar configurações: " . $e->getMessage());
    }
}

// Configuração de idioma e fuso horário
setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf-8', 'portuguese');
date_default_timezone_set($_SESSION['user_settings']['timezone'] ?? 'America/Sao_Paulo');

// Atualiza o status do usuário para online
$pdo->prepare("UPDATE users SET is_online = 1 WHERE id = ?")->execute([$user_id]);

// Busca informações do usuário logado
$userStmt = $pdo->prepare("SELECT name, profile_picture FROM users WHERE id = ?");
$userStmt->execute([$user_id]);
$loggedInUser = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$loggedInUser) {
    die("Erro: Usuário não encontrado.");
}


$followingUsers = getFollowingUsers($pdo, $user_id);

setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf-8', 'portuguese');
date_default_timezone_set('America/Sao_Paulo');

// Obtem a data e hora atual no formato desejado
$dataHoraAtual = strftime('%A, %d de %B de %Y', time()); // Exibe o dia da semana, dia, mês e ano
$horaAtual = date('H:i'); // Exibe a hora no formato 24 horas

// Atualiza o status do usuário logado para online
$pdo->prepare("UPDATE users SET is_online = 1 WHERE id = ?")->execute([$user_id]);

// Busca informações do usuário logado
$userStmt = $pdo->prepare("SELECT name, profile_picture FROM users WHERE id = ?");
$userStmt->execute([$user_id]);
$loggedInUser = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$loggedInUser) {
    die("Erro: Usuário não encontrado.");
}

// Função para buscar usuários online que o usuário logado segue
function getFollowingOnline($pdo, $userId)
{
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, 
               IFNULL(u.profile_picture, 'default-profile.png') AS profile_picture, 
               u.is_online 
        FROM followers f
        JOIN users u ON f.following_id = u.id
        WHERE f.follower_id = ? AND u.is_online = 1
        ORDER BY u.name ASC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function getFollowingUsers($pdo, $userId)
{
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, 
               IFNULL(u.profile_picture, 'default-profile.png') AS profile_picture, 
               u.is_online 
        FROM followers f
        JOIN users u ON f.following_id = u.id
        WHERE f.follower_id = ?
        ORDER BY u.name ASC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Busca lista de usuários online que o usuário logado segue
$followingUsers = getFollowingUsers($pdo, $user_id);


// Função para registrar notificações
function addNotification($pdo, $userId, $message)
{
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
    $stmt->execute([$userId, $message]);
}

// Busca todas as ocorrências com paginação
$limit = 10; // Número de ocorrências por página
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$stmt = $pdo->prepare("
    SELECT o.id, o.title, o.description, o.created_at, u.name AS user_name, 
           u.profile_picture, c.name AS category_name, o.likes
    FROM occurrences o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN categories c ON o.category_id = c.id
    ORDER BY o.created_at DESC
    LIMIT ? OFFSET ?");
$stmt->bindValue(1, $limit, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$occurrences = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contagem total de ocorrências para paginação
$totalOccurrences = $pdo->query("SELECT COUNT(*) FROM occurrences")->fetchColumn();
$totalPages = ceil($totalOccurrences / $limit);

// Query para buscar notificações
$notifications = $pdo->prepare("
    SELECT id, message, created_at, is_read 
    FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10");
$notifications->execute([$user_id]);
$notifications = $notifications->fetchAll(PDO::FETCH_ASSOC);

// Marcar notificações como lidas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_as_read'])) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$user_id]);
}

// Buscar todas as ocorrências e arquivos
$stmt = $pdo->prepare("SELECT 
    o.id, o.title, o.description, o.created_at, u.name AS user_name, u.profile_picture, c.name AS category_name, o.likes
FROM 
    occurrences o
JOIN 
    users u ON o.user_id = u.id
LEFT JOIN 
    categories c ON o.category_id = c.id
ORDER BY 
    o.created_at DESC");
$stmt->execute();
$occurrences = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT o.id, o.title, o.description, o.created_at, u.id AS user_id, 
           u.name AS user_name, u.profile_picture, c.name AS category_name, o.likes
    FROM occurrences o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN categories c ON o.category_id = c.id
    ORDER BY o.created_at DESC
    LIMIT ? OFFSET ?");
$stmt->bindValue(1, $limit, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$occurrences = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Query para buscar comentários com informações do usuário
$commentStmt = $pdo->prepare("SELECT 
    c.id AS comment_id, 
    c.comment, 
    u.name, 
    u.profile_picture, 
    c.user_id AS comment_user_id, 
    DATE_FORMAT(c.created_at, '%d/%m/%Y %H:%i') AS formatted_date 
FROM 
    comments c 
JOIN 
    users u 
ON 
    c.user_id = u.id 
WHERE 
    c.occurrence_id = ? 
ORDER BY 
    c.created_at DESC");

// Adicionar curtida
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['like_occurrence_id'])) {
    $occurrenceId = $_POST['like_occurrence_id'];

    // Adiciona a curtida (lógica existente)
    $stmt = $pdo->prepare("UPDATE occurrences SET likes = likes + 1 WHERE id = ?");
    $stmt->execute([$occurrenceId]);

    // Obtém o ID do usuário da ocorrência
    $stmt = $pdo->prepare("SELECT user_id FROM occurrences WHERE id = ?");
    $stmt->execute([$occurrenceId]);
    $ownerId = $stmt->fetchColumn();

    // Adiciona uma notificação
    if ($ownerId) {
        addNotification($pdo, $ownerId, "Seu post foi curtido por {$loggedInUser['name']}.");
    }
}

// Adicionar comentário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['occurrence_id']) && isset($_POST['comment'])) {
    $occurrenceId = $_POST['occurrence_id'];
    $comment = $_POST['comment'];

    // Adiciona o comentário (lógica existente)
    $stmt = $pdo->prepare("INSERT INTO comments (occurrence_id, user_id, comment) VALUES (?, ?, ?)");
    $stmt->execute([$occurrenceId, $user_id, $comment]);

    // Obtém o ID do usuário da ocorrência
    $stmt = $pdo->prepare("SELECT user_id FROM occurrences WHERE id = ?");
    $stmt->execute([$occurrenceId]);
    $ownerId = $stmt->fetchColumn();

    // Adiciona uma notificação
    if ($ownerId) {
        addNotification($pdo, $ownerId, "Seu post recebeu um comentário de {$loggedInUser['name']}.");
    }
}

// Buscar notificações do usuário
$notifications = [];
try {
    $stmt = $pdo->prepare("SELECT id, message, created_at, is_read FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Erro ao buscar notificações: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_as_read'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?");
    $stmt->execute([$user_id]);
}

// Buscar notificações não lidas
$notificationStmt = $pdo->prepare("
    SELECT id, message, created_at 
    FROM notifications 
    WHERE user_id = ? AND is_read = 0 
    ORDER BY created_at DESC 
    LIMIT 10
");
$notificationStmt->execute([$user_id]);
$unreadNotifications = $notificationStmt->fetchAll(PDO::FETCH_ASSOC);

// Marcar todas as notificações como lidas (se solicitado)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_notifications'])) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$user_id]);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Buscar arquivos relacionados
$fileStmt = $pdo->prepare("SELECT file_name, file_path, file_type, occurrence_id FROM files WHERE occurrence_id = ?");
?>


<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feed de Ocorrências</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">



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
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-bell"></i>
                        <span class="badge bg-danger" id="notificationCount" style="display: <?= count($unreadNotifications) > 0 ? 'inline-block' : 'none'; ?>;">
                            <?= count($unreadNotifications); ?>
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationDropdown" id="notificationList">
                        <?php if (!empty($unreadNotifications)): ?>
                            <?php foreach ($unreadNotifications as $notification): ?>
                                <li>
                                    <a class="dropdown-item" href="geren_chat.php">
                                        <?= htmlspecialchars($notification['message']); ?>
                                        <small class="text-muted d-block"><?= date('d/m/Y H:i', strtotime($notification['created_at'])); ?></small>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                            <li>
                                <form method="POST" id="clearNotificationsForm" style="text-align: center;">
                                    <button type="button" id="clearNotificationsButton" class="btn btn-link text-danger">Limpar todas</button>
                                </form>
                            </li>
                        <?php else: ?>
                            <li><span class="dropdown-item-text">Nenhuma notificação</span></li>
                        <?php endif; ?>
                    </ul>
                </li>


                <div class="dropdown">
                    <a class="btn btn-secondary dropdown-toggle d-flex align-items-center" href="#" role="button" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="<?= htmlspecialchars($loggedInUser['profile_picture'] ?: '/assets/default-avatar.png'); ?>" alt="Foto de perfil" class="rounded-circle me-2" style="width: 30px; height: 30px;">
                        <?= htmlspecialchars($loggedInUser['name']); ?>
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
    <div class="container mt-5 pt-4">
        <h1>Bem-vindo, <?= htmlspecialchars($loggedInUser['name']); ?></h1>
        <p>Hoje é <?= ucfirst(utf8_encode($dataHoraAtual)); ?>, <?= $horaAtual; ?></p>
    </div>

    <div class="container mt-5">
        <h1 class="mb-4">Feed de Ocorrências</h1>

        <?php if (!empty($occurrences)): ?>
            <?php foreach ($occurrences as $occurrence): ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">🔖 <?= htmlspecialchars($occurrence['title']); ?></h5>
                        <h6 class="card-subtitle mb-2 text-muted">
                            <div class="d-flex align-items-center">
                                <?php if (!empty($occurrence['profile_picture'])): ?>
                                    <img src="<?= htmlspecialchars($occurrence['profile_picture']); ?>" alt="Foto de perfil" class="me-2" style="width: 30px; height: 30px; border-radius: 50%;">
                                <?php endif; ?>
                                <a href="profile.php?user_id=<?= $occurrence['user_id']; ?>" class="text-decoration-none fw-bold">
                                    <?= htmlspecialchars($occurrence['user_name']); ?>
                                </a>
                                <span class="mx-2 fw-light">em</span>
                                <span class="text-muted"><?= date('d/m/Y H:i', strtotime($occurrence['created_at'])); ?></span>
                            </div>
                            <?php if (!empty($occurrence['category_name'])): ?>
                                <div class="mt-1">
                                    <small class="badge bg-primary">Categoria: <?= htmlspecialchars($occurrence['category_name']); ?></small>
                                </div>
                            <?php endif; ?>
                        </h6>

                        <p class="card-text" id="card-text-<?= $occurrence['id']; ?>">
                            🖍 <?= nl2br(htmlspecialchars($occurrence['description'])); ?>
                        </p>
                        <?php if (strlen($occurrence['description']) > 200): ?>
                            <a href="javascript:void(0);" class="read-more" onclick="toggleText('<?= $occurrence['id']; ?>')">Leia mais</a>
                        <?php endif; ?>

                        <h6>Arquivos Anexados:</h6>
                        <ul class="file-list">
                            <?php
                            $fileStmt->execute([$occurrence['id']]);
                            $files = $fileStmt->fetchAll(PDO::FETCH_ASSOC);
                            if (!empty($files)) {
                                foreach ($files as $file): ?>
                                    <li>
                                        <?php if (strpos($file['file_type'], 'image') !== false): ?>
                                            <img src="<?= htmlspecialchars($file['file_path']); ?>" alt="Imagem" class="media-preview">
                                        <?php elseif (strpos($file['file_type'], 'video') !== false): ?>
                                            <video controls class="media-preview">
                                                <source src="<?= htmlspecialchars($file['file_path']); ?>" type="<?= htmlspecialchars($file['file_type']); ?>">
                                                Seu navegador não suporta o elemento de vídeo.
                                            </video>

                                        <?php elseif (strpos($file['file_type'], 'audio') !== false): ?>
                                            <audio controls class="media-preview">
                                                <source src="<?= htmlspecialchars($file['file_path']); ?>" type="<?= htmlspecialchars($file['file_type']); ?>">
                                                Seu navegador não suporta o elemento de áudio.
                                            </audio>
                                        <?php elseif (strpos($file['file_type'], 'pdf') !== false): ?>
                                            <a href="<?= htmlspecialchars($file['file_path']); ?>" target="_blank" class="file-link">📄 <?= htmlspecialchars($file['file_name']); ?> (Download)</a>
                                        <?php else: ?>
                                            <a href="<?= htmlspecialchars($file['file_path']); ?>" target="_blank" class="file-link">📁 <?= htmlspecialchars($file['file_name']); ?></a>
                                        <?php endif; ?>
                                    </li>
                            <?php endforeach;
                            } else {
                                echo "<li>Nenhum arquivo anexado.</li>";
                            }
                            ?>
                        </ul>

                        <div class="like-comment mt-3">
                            <span class="like-btn" data-id="<?= $occurrence['id']; ?>">👍 Curtir (<span id="likes-<?= $occurrence['id']; ?>"><?= $occurrence['likes']; ?></span>)</span>
                        </div>

                        <div class="comment-section mt-4">
                            <h6>Comentários:</h6>
                            <div id="comments-<?= htmlspecialchars($occurrence['id']); ?>" class="mb-3">
                                <?php
                                $commentStmt->execute([$occurrence['id']]);
                                $comments = $commentStmt->fetchAll(PDO::FETCH_ASSOC);

                                if (!empty($comments)) {
                                    foreach ($comments as $comment): ?>
                                        <div class="d-flex align-items-start mb-2" id="comment-<?= $comment['comment_id']; ?>">
                                            <img src="<?= htmlspecialchars($comment['profile_picture']); ?>" alt="Foto de perfil" class="me-2" style="width:30px; height:30px; border-radius:50%;">
                                            <div class="w-100">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <p><strong><?= htmlspecialchars($comment['name']); ?>:</strong> <span class="comment-text"><?= htmlspecialchars($comment['comment']); ?></span></p>
                                                    <?php if ($comment['comment_user_id'] == $user_id): ?>
                                                        <div class="dropdown">
                                                            <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" id="dropdownMenuButton<?= $comment['comment_id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                                ⋮
                                                            </button>
                                                            <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton<?= $comment['comment_id']; ?>">
                                                                <li><button class="dropdown-item edit-comment-btn" data-id="<?= $comment['comment_id']; ?>">Editar</button></li>
                                                                <li><button class="dropdown-item delete-comment-btn text-danger" data-id="<?= $comment['comment_id']; ?>">Excluir</button></li>
                                                            </ul>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <small class="text-muted"><?= htmlspecialchars($comment['formatted_date']); ?></small>
                                            </div>
                                        </div>
                                <?php endforeach;
                                } else {
                                    echo "<p class='text-muted'>Nenhum comentário ainda.</p>";
                                }
                                ?>
                            </div>
                            <form method="POST" class="add-comment-form">
                                <input type="hidden" name="occurrence_id" value="<?= $occurrence['id']; ?>">
                                <div class="input-group">
                                    <input type="text" class="form-control" placeholder="Adicione um comentário..." name="comment" required>
                                    <button type="submit" class="btn btn-primary">Enviar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info">Nenhuma ocorrência registrada até o momento.</div>
        <?php endif; ?>

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

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const notificationDropdown = document.getElementById('notificationDropdown');
        const notificationCount = document.getElementById('notificationCount');
        const notificationList = document.getElementById('notificationList');

        // Função para buscar notificações
        async function fetchNotifications() {
            try {
                const response = await fetch('fetch_notifications.php'); // Chama o arquivo PHP para obter notificações
                const data = await response.json();

                if (data.success) {
                    const notifications = data.notifications;
                    const unreadCount = notifications.length;

                    // Atualiza o contador de notificações
                    notificationCount.textContent = unreadCount;
                    notificationCount.style.display = unreadCount > 0 ? 'inline-block' : 'none';

                    // Atualiza a lista de notificações
                    notificationList.innerHTML = ''; // Limpa a lista existente
                    if (unreadCount > 0) {
                        notifications.forEach(notification => {
                            const listItem = document.createElement('li');
                            listItem.innerHTML = `
                        <a class="dropdown-item" href="geren_chat.php">
                            ${notification.message}
                            <small class="text-muted d-block">${new Date(notification.created_at).toLocaleString('pt-BR')}</small>
                        </a>
                    `;
                            notificationList.appendChild(listItem);
                        });

                        // Adiciona o botão "Limpar todas"
                        const clearButton = document.createElement('li');
                        clearButton.innerHTML = `
                    <form id="clearNotificationsForm" method="POST" style="text-align: center;">
                        <button type="button" id="clearNotificationsButton" class="btn btn-link text-danger">Limpar todas</button>
                    </form>
                `;
                        notificationList.appendChild(clearButton);

                        // Evento para limpar notificações
                        document.getElementById('clearNotificationsButton').addEventListener('click', clearNotifications);
                    } else {
                        notificationList.innerHTML = '<li><span class="dropdown-item-text">Nenhuma notificação</span></li>';
                    }
                } else {
                    console.error('Erro ao buscar notificações:', data.error);
                }
            } catch (error) {
                console.error('Erro ao buscar notificações:', error);
            }
        }

        // Função para limpar notificações
        async function clearNotifications() {
            try {
                const response = await fetch('clear_notifications.php', {
                    method: 'POST',
                });

                const data = await response.json();
                if (data.success) {
                    fetchNotifications(); // Atualiza as notificações após limpar
                } else {
                    console.error('Erro ao limpar notificações:', data.error);
                }
            } catch (error) {
                console.error('Erro ao limpar notificações:', error);
            }
        }

        // Atualiza as notificações automaticamente a cada 5 segundos
        setInterval(fetchNotifications, 5000);
        fetchNotifications(); // Busca notificações ao carregar a página
        document.querySelector('.toggle-following').addEventListener('click', function() {
            document.querySelector('.following-list').classList.toggle('active');
        });



        document.querySelectorAll('.like-btn').forEach(button => {
            button.addEventListener('click', event => {
                event.preventDefault();
                const occurrenceId = button.getAttribute('data-id');
                const likesElement = document.getElementById(`likes-${occurrenceId}`);

                fetch(`like.php?id=${occurrenceId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            likesElement.textContent = data.likes;
                        } else {
                            alert('Erro ao curtir!');
                        }
                    })
                    .catch(error => console.error('Erro:', error));
            });
        });


        document.querySelectorAll('.submit-comment').forEach(button => {
            button.addEventListener('click', function() {
                const form = button.closest('.add-comment-form');
                const occurrenceId = form.querySelector('input[name="occurrence_id"]').value;
                const commentInput = form.querySelector('input[name="comment"]');
                const commentText = commentInput.value.trim();

                if (!commentText) {
                    alert('Digite um comentário.');
                    return;
                }

                fetch('comment.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: new URLSearchParams({
                            occurrence_id: occurrenceId,
                            comment: commentText
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const commentsDiv = document.querySelector(`#comments-${occurrenceId}`);
                            const newComment = `
                            <div class="d-flex align-items-start mb-2" id="comment-${data.comment_id}">
                                <img src="${data.profile_picture}" alt="Foto de perfil" class="me-2" style="width:30px; height:30px; border-radius:50%; object-fit: cover;">
                                <div class="w-100">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <p><strong>${data.user_name}:</strong> <span class="comment-text">${data.comment}</span></p>
                                        <div class="comment-options" data-id="${data.comment_id}">⋮</div>
                                    </div>
                                    <small class="text-muted">${data.created_at}</small>
                                </div>
                            </div>`;
                            commentsDiv.innerHTML = newComment + commentsDiv.innerHTML;
                            commentInput.value = '';
                        } else {
                            console.error('Erro ao adicionar comentário:', data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                    });
            });
        });

        document.querySelectorAll('.comment-options').forEach(menu => {
            menu.addEventListener('click', function() {
                const commentId = menu.getAttribute('data-id');
                const commentDiv = document.querySelector(`#comment-${commentId}`);
                const commentText = commentDiv.querySelector('.comment-text').textContent;

                const editTextarea = document.createElement('textarea');
                editTextarea.className = 'form-control';
                editTextarea.value = commentText;

                commentDiv.querySelector('.comment-text').innerHTML = '';
                commentDiv.querySelector('.comment-text').appendChild(editTextarea);

                const saveButton = document.createElement('button');
                saveButton.textContent = 'Salvar';
                saveButton.className = 'btn btn-success btn-sm';
                saveButton.onclick = function() {
                    fetch('edit_comment.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: new URLSearchParams({
                                comment_id: commentId,
                                comment: editTextarea.value
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                commentDiv.querySelector('.comment-text').textContent = editTextarea.value;
                                editTextarea.remove();
                                saveButton.remove();
                            } else {
                                alert('Erro ao salvar o comentário.');
                            }
                        });
                };
                commentDiv.appendChild(saveButton);
            });
        });

        document.addEventListener('DOMContentLoaded', () => {
            // Edição de comentário
            document.querySelectorAll('.edit-comment-btn').forEach(button => {
                button.addEventListener('click', () => {
                    const commentId = button.getAttribute('data-id');
                    const currentText = button.getAttribute('data-text');
                    const newText = prompt('Edite seu comentário:', currentText);

                    if (newText !== null && newText.trim() !== '') {
                        fetch('comment_actions.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                                },
                                body: new URLSearchParams({
                                    action: 'edit',
                                    comment_id: commentId,
                                    comment_text: newText
                                })
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    document.querySelector(`#comment-${commentId} .comment-text`).textContent = newText;
                                    alert(data.message);
                                } else {
                                    alert(data.message);
                                }
                            })
                            .catch(error => console.error('Erro ao editar comentário:', error));
                    }
                });
            });

            // Exclusão de comentário
            document.querySelectorAll('.delete-comment-btn').forEach(button => {
                button.addEventListener('click', () => {
                    const commentId = button.getAttribute('data-id');

                    if (confirm('Tem certeza que deseja excluir este comentário?')) {
                        fetch('comment_actions.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                                },
                                body: new URLSearchParams({
                                    action: 'delete',
                                    comment_id: commentId
                                })
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    document.getElementById(`comment-${commentId}`).remove();
                                    alert(data.message);
                                } else {
                                    alert(data.message);
                                }
                            })
                            .catch(error => console.error('Erro ao excluir comentário:', error));
                    }
                });
            });
        });
    </script>
</body>

</html>