<?php
require 'db_config.php';
session_start();

if (isset($_SESSION['user_id'])) {
    $current_user_id = $_SESSION['user_id'];

    // Atualiza o status para offline
    $updateOfflineStmt = $pdo->prepare("UPDATE users SET is_online = 0 WHERE id = ?");
    $updateOfflineStmt->execute([$current_user_id]);
}

// Destrói a sessão
session_unset();
session_destroy();

// Redireciona para o login
header("Location: login.php");
exit;

?>
