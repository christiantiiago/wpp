<?php
session_start();
require 'db_config.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Função para recarregar configurações do usuário
function reloadUserSettings($pdo, $user_id)
{
    $query = $pdo->prepare("SELECT language, theme, notifications, privacy, timezone FROM users WHERE id = ?");
    $query->execute([$user_id]);
    $userSettings = $query->fetch(PDO::FETCH_ASSOC);

    if ($userSettings) {
        $_SESSION['user_settings'] = $userSettings;
    }
}

// Carrega as configurações atuais do usuário (da sessão ou banco de dados)
if (!isset($_SESSION['user_settings'])) {
    reloadUserSettings($pdo, $user_id);
}
$userSettings = $_SESSION['user_settings'];

// Atualiza configurações do usuário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $language = $_POST['language'] ?? 'pt-br';
    $theme = $_POST['theme'] ?? 'light';
    $notifications = isset($_POST['notifications']) ? 1 : 0;
    $privacy = $_POST['privacy'] ?? 'public';
    $timezone = $_POST['timezone'] ?? 'America/Sao_Paulo';

    try {
        $updateQuery = $pdo->prepare("UPDATE users SET language = ?, theme = ?, notifications = ?, privacy = ?, timezone = ? WHERE id = ?");
        $updateQuery->execute([$language, $theme, $notifications, $privacy, $timezone, $user_id]);

        // Atualiza as configurações na sessão após salvar no banco de dados
        reloadUserSettings($pdo, $user_id);

        $successMessage = "Configurações atualizadas com sucesso!";
    } catch (PDOException $e) {
        die("Erro ao atualizar configurações: " . $e->getMessage());
    }
}

?>

<!DOCTYPE html>
<html lang="<?= $userSettings['language'] ?? 'pt-br'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="<?= $userSettings['theme'] === 'dark' ? 'bg-dark text-light' : ''; ?>">
    <div class="container mt-5">
        <h2 class="mb-4">Configurações</h2>
        <?php if (isset($successMessage)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
        <?php endif; ?>
        <form method="POST" class="row g-3">

            <!-- Idioma -->
            <div class="col-md-6">
                <label for="language" class="form-label">Idioma</label>
                <select name="language" id="language" class="form-select">
                    <option value="pt-br" <?= $userSettings['language'] === 'pt-br' ? 'selected' : ''; ?>>Português</option>
                    <option value="en" <?= $userSettings['language'] === 'en' ? 'selected' : ''; ?>>Inglês</option>
                </select>
            </div>

            <!-- Tema -->
            <div class="col-md-6">
                <label for="theme" class="form-label">Tema</label>
                <select name="theme" id="theme" class="form-select">
                    <option value="light" <?= $userSettings['theme'] === 'light' ? 'selected' : ''; ?>>Claro</option>
                    <option value="dark" <?= $userSettings['theme'] === 'dark' ? 'selected' : ''; ?>>Escuro</option>
                </select>
            </div>

            <!-- Notificações -->
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="notifications" id="notifications" <?= $userSettings['notifications'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="notifications">
                        Ativar Notificações
                    </label>
                </div>
            </div>

            <!-- Privacidade -->
            <div class="col-md-6">
                <label for="privacy" class="form-label">Privacidade</label>
                <select name="privacy" id="privacy" class="form-select">
                    <option value="public" <?= $userSettings['privacy'] === 'public' ? 'selected' : ''; ?>>Público</option>
                    <option value="private" <?= $userSettings['privacy'] === 'private' ? 'selected' : ''; ?>>Privado</option>
                </select>
            </div>

            <!-- Fuso Horário -->
            <div class="col-md-6">
                <label for="timezone" class="form-label">Fuso Horário</label>
                <select name="timezone" id="timezone" class="form-select">
                    <option value="America/Sao_Paulo" <?= $userSettings['timezone'] === 'America/Sao_Paulo' ? 'selected' : ''; ?>>São Paulo</option>
                    <option value="America/New_York" <?= $userSettings['timezone'] === 'America/New_York' ? 'selected' : ''; ?>>Nova York</option>
                    <option value="Europe/London" <?= $userSettings['timezone'] === 'Europe/London' ? 'selected' : ''; ?>>Londres</option>
                </select>
            </div>

            <!-- Botão Salvar -->
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Salvar Configurações</button>
            </div>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
