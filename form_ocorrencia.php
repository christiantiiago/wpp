text/x-generic form_ocorrencia.php ( PHP script, UTF-8 Unicode text, with CRLF line terminators )
<?php
require 'db_config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Usuário não autenticado.']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Busca informações do usuário logado
$userStmt = $pdo->prepare("SELECT name, profile_picture FROM users WHERE id = ?");
$userStmt->execute([$user_id]);
$loggedInUser = $userStmt->fetch(PDO::FETCH_ASSOC);

// Busca categorias do banco de dados
$categories = [];
try {
    $categoriesStmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
    $categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Erro ao buscar categorias: ' . $e->getMessage()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $occurrenceId = $_POST['occurrence_id'] ?? null;
    $chunk = $_FILES['file'] ?? null;

    if (!$occurrenceId || !$chunk) {
        echo json_encode(['error' => 'Dados incompletos.']);
        exit;
    }

    $uploadDir = 'uploads/';
    $fileName = $_POST['file_name'];
    $chunkIndex = $_POST['chunk_index'];
    $totalChunks = $_POST['total_chunks'];

    $filePath = $uploadDir . $fileName . ".part{$chunkIndex}";
    if (!move_uploaded_file($chunk['tmp_name'], $filePath)) {
        echo json_encode(['error' => 'Falha ao salvar o arquivo.']);
        exit;
    }

    // Verifica se todos os chunks foram enviados
    if ($chunkIndex + 1 == $totalChunks) {
        $finalPath = $uploadDir . $fileName;
        $output = fopen($finalPath, 'wb');

        for ($i = 0; $i < $totalChunks; $i++) {
            $partPath = "{$uploadDir}{$fileName}.part{$i}";
            $chunkData = fopen($partPath, 'rb');
            stream_copy_to_stream($chunkData, $output);
            fclose($chunkData);
            unlink($partPath); // Remove o chunk depois de concatenar
        }
        fclose($output);

        // Salva no banco de dados
        $stmt = $pdo->prepare("INSERT INTO files (occurrence_id, file_name, file_path, file_type, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $occurrenceId,
            $fileName,
            $finalPath,
            mime_content_type($finalPath),
            filesize($finalPath),
            $user_id,
        ]);
    }

    echo json_encode(['success' => true]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Ocorrência</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/reg.css">
    <style>
        .progress {
            height: 25px;
        }
    </style>
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
    <div class="container mt-5">
        <h1 class="mb-4">Registrar Ocorrência</h1>
        <form id="upload-form">
            <div class="mb-3">
                <label for="title" class="form-label">Título</label>
                <input type="text" class="form-control" id="title" name="title" required>
            </div>
            <div class="mb-3">
                <label for="description" class="form-label">Descrição</label>
                <textarea class="form-control" id="description" name="description" rows="4"></textarea>
            </div>
            <div class="mb-3">
                <label for="category" class="form-label">Categoria</label>
                <select class="form-select" id="category" name="category_id" required>
                    <option value="">Selecione uma categoria</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= htmlspecialchars($category['id']); ?>"><?= htmlspecialchars($category['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="files" class="form-label">Anexar Arquivos</label>
                <input type="file" class="form-control" id="files" multiple>
            </div>
            <div id="progress-container" class="mb-3 d-none">
                <label for="upload-progress" class="form-label">Progresso</label>
                <div class="progress">
                    <div id="upload-progress" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar"></div>
                </div>
            </div>
            <button type="button" id="submit-btn" class="btn btn-primary">Registrar</button>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const uploadForm = document.getElementById('upload-form');
        const submitBtn = document.getElementById('submit-btn');
        const progressContainer = document.getElementById('progress-container');
        const uploadProgress = document.getElementById('upload-progress');

        submitBtn.addEventListener('click', async () => {
            const files = document.getElementById('files').files;
            if (files.length === 0) {
                alert('Por favor, selecione um arquivo.');
                return;
            }

            const title = document.getElementById('title').value;
            const description = document.getElementById('description').value;
            const categoryId = document.getElementById('category').value;

            if (!title || !description || !categoryId) {
                alert('Por favor, preencha todos os campos obrigatórios.');
                return;
            }

            // Envia os dados iniciais
            const occurrenceId = await createOccurrence(title, description, categoryId);
            if (!occurrenceId) return;

            progressContainer.classList.remove('d-none');

            // Envia os arquivos em partes
            const CHUNK_SIZE = 5 * 1024 * 1024; // 5 MB
            let totalSize = 0,
                uploadedSize = 0;

            for (let file of files) {
                totalSize += file.size;
                const totalChunks = Math.ceil(file.size / CHUNK_SIZE);

                for (let i = 0; i < totalChunks; i++) {
                    const chunk = file.slice(i * CHUNK_SIZE, (i + 1) * CHUNK_SIZE);
                    const formData = new FormData();
                    formData.append('occurrence_id', occurrenceId);
                    formData.append('file', chunk);
                    formData.append('file_name', file.name);
                    formData.append('chunk_index', i);
                    formData.append('total_chunks', totalChunks);

                    await fetch('file_upload.php', {
                        method: 'POST',
                        body: formData
                    });
                    uploadedSize += chunk.size;
                    const progress = Math.min((uploadedSize / totalSize) * 100, 100);
                    uploadProgress.style.width = `${progress}%`;
                    uploadProgress.textContent = `${Math.round(progress)}%`;
                }
            }
            alert('Ocorrência registrada com sucesso!');
            uploadForm.reset();
            progressContainer.classList.add('d-none');
        });

        async function createOccurrence(title, description, categoryId) {
            const response = await fetch('create_occurrence.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    title,
                    description,
                    category_id: categoryId
                }),
            });
            const data = await response.json();
            if (data.error) {
                alert(data.error);
                return null;
            }
            return data.occurrence_id;
        }
    </script>
</body>

</html>