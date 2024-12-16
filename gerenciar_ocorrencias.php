<?php
require 'db_config.php';
require_once 'session_start_global.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo "<div class='alert alert-danger'>Erro: Usuário não autenticado.</div>";
    exit;
}

$user_id = $_SESSION['user_id'];

// Busca informações do usuário logado
$userStmt = $pdo->prepare("SELECT name, profile_picture FROM users WHERE id = ?");
$userStmt->execute([$user_id]);
$loggedInUser = $userStmt->fetch(PDO::FETCH_ASSOC);

// Obtém as ocorrências registradas pelo usuário logado
$stmt = $pdo->prepare("SELECT id, title, description, created_at FROM occurrences WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$occurrences = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Ocorrências</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/css/ger.css?version=1.0">

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
    <br><br>
    <div class="container mt-5">
        <h1 class="mb-4">Minhas Ocorrências</h1>

        <?php if (!empty($occurrences)): ?>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Título</th>
                            <th>Descrição</th>
                            <th>Data de Criação</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($occurrences as $occurrence): ?>
                            <tr id="occurrence-<?= $occurrence['id']; ?>">
                                <td><?= htmlspecialchars($occurrence['title']); ?></td>
                                <td><?= htmlspecialchars($occurrence['description']); ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($occurrence['created_at'])); ?></td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton<?= $occurrence['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                            ⋮
                                        </button>
                                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton<?= $occurrence['id']; ?>">
                                            <li>
                                                <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#editModal" onclick="populateEditModal(<?= $occurrence['id']; ?>, '<?= htmlspecialchars($occurrence['title']); ?>', '<?= htmlspecialchars($occurrence['description']); ?>')">
                                                    Editar
                                                </button>
                                            </li>
                                            <li>
                                                <button class="dropdown-item text-danger" onclick="deleteOccurrence(<?= $occurrence['id']; ?>)">
                                                    Excluir
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>
            <div class="alert alert-info">Você ainda não registrou nenhuma ocorrência.</div>
        <?php endif; ?>

        <!-- Modal de edição -->
        <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editModalLabel">Editar Ocorrência</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="edit-form">
                            <input type="hidden" id="edit-id">
                            <div class="mb-3">
                                <label for="edit-title" class="form-label">Título</label>
                                <input type="text" class="form-control" id="edit-title" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit-description" class="form-label">Descrição</label>
                                <textarea class="form-control" id="edit-description" rows="4" required></textarea>
                            </div>
                            <button type="button" class="btn btn-primary" onclick="saveEdit()">Salvar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function populateEditModal(id, title, description) {
            document.getElementById('edit-id').value = id;
            document.getElementById('edit-title').value = title;
            document.getElementById('edit-description').value = description;
        }

        function saveEdit() {
            const id = document.getElementById('edit-id').value;
            const title = document.getElementById('edit-title').value;
            const description = document.getElementById('edit-description').value;

            if (!title || !description) {
                alert('Todos os campos são obrigatórios.');
                return;
            }

            fetch('edit_occurrence.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        id: id,
                        title: title,
                        description: description
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const row = document.getElementById(`occurrence-${id}`);
                        row.querySelector('td:nth-child(1)').textContent = title;
                        row.querySelector('td:nth-child(2)').textContent = description;

                        const modal = bootstrap.Modal.getInstance(document.getElementById('editModal'));
                        modal.hide();
                        alert('Alterações salvas com sucesso.');
                    } else {
                        alert(data.message || 'Erro ao salvar alterações.');
                    }
                })
                .catch(error => {
                    console.error('Erro ao salvar alterações:', error);
                    alert('Erro ao salvar alterações.');
                });
        }

        function deleteOccurrence(id) {
            if (confirm('Tem certeza que deseja excluir esta ocorrência?')) {
                fetch('delete_occurrence.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: new URLSearchParams({
                            id: id
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById(`occurrence-${id}`).remove();
                            alert('Ocorrência excluída com sucesso.');
                        } else {
                            alert(data.message || 'Erro ao excluir ocorrência!');
                        }
                    })
                    .catch(error => {
                        console.error('Erro na exclusão:', error);
                        alert('Erro ao tentar excluir a ocorrência.');
                    });
            }
        }
    </script>
</body>

</html>