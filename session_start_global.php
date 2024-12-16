<?php
// session_start_global.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'db_config.php'; // Conexão com o banco de dados

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Função para recarregar as configurações do banco de dados
function reloadUserSettings($pdo, $user_id)
{
    try {
        $query = $pdo->prepare("SELECT language, theme, notifications, privacy, timezone FROM users WHERE id = ?");
        $query->execute([$user_id]);
        $userSettings = $query->fetch(PDO::FETCH_ASSOC);

        if ($userSettings) {
            $_SESSION['user_settings'] = $userSettings;

            // Define o fuso horário
            date_default_timezone_set($userSettings['timezone'] ?? 'America/Sao_Paulo');
        } else {
            throw new Exception("Configurações do usuário não encontradas.");
        }
    } catch (PDOException $e) {
        die("Erro ao carregar configurações: " . $e->getMessage());
    }
}

// Recarrega as configurações se elas ainda não existirem na sessão
if (!isset($_SESSION['user_settings'])) {
    reloadUserSettings($pdo, $user_id);
}

// Função para aplicar o tema
function applyTheme()
{
    $theme = $_SESSION['user_settings']['theme'] ?? 'light';
    return $theme === 'dark' ? 'bg-dark text-light' : '';
}

// Função para trocar o idioma
function loadLanguage()
{
    $language = $_SESSION['user_settings']['language'] ?? 'pt-br';
    return $language === 'en' ? 'en' : 'pt-br';
}

// Função para verificar se o perfil é privado
function isProfilePrivate($pdo, $profile_user_id)
{
    try {
        $stmt = $pdo->prepare("SELECT privacy FROM users WHERE id = ?");
        $stmt->execute([$profile_user_id]);
        $privacy = $stmt->fetchColumn();
        return $privacy === 'private';
    } catch (PDOException $e) {
        die("Erro ao verificar privacidade do perfil: " . $e->getMessage());
    }
}

// Função para verificar se um usuário pode visualizar um perfil
function canViewProfile($pdo, $current_user_id, $profile_user_id)
{
    try {
        // Se o perfil não for privado, qualquer usuário pode visualizar
        if (!isProfilePrivate($pdo, $profile_user_id)) {
            return true;
        }

        // Verifica se o usuário logado segue o perfil
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM followers WHERE follower_id = ? AND following_id = ?");
        $stmt->execute([$current_user_id, $profile_user_id]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        die("Erro ao verificar permissão de visualização: " . $e->getMessage());
    }
}

// Função para bloquear um perfil
function blockProfile($pdo, $current_user_id, $profile_user_id)
{
    try {
        $stmt = $pdo->prepare("INSERT INTO blocked_users (blocker_id, blocked_id) VALUES (?, ?)");
        $stmt->execute([$current_user_id, $profile_user_id]);
        return true;
    } catch (PDOException $e) {
        die("Erro ao bloquear o perfil: " . $e->getMessage());
    }
}

// Função para verificar se o perfil está bloqueado
function isProfileBlocked($pdo, $current_user_id, $profile_user_id)
{
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM blocked_users WHERE blocker_id = ? AND blocked_id = ?");
        $stmt->execute([$current_user_id, $profile_user_id]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        die("Erro ao verificar se o perfil está bloqueado: " . $e->getMessage());
    }
}
?>
