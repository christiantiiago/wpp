<?php
require 'db_config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'] ?? null;
    $comment_id = $_POST['comment_id'] ?? null;
    $new_comment = $_POST['comment'] ?? '';

    if (!$user_id || !$comment_id || !$new_comment) {
        echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
        exit;
    }

    try {
        // Verifica se o comentário pertence ao usuário
        $stmt = $pdo->prepare("SELECT user_id FROM comments WHERE id = ?");
        $stmt->execute([$comment_id]);
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$comment || $comment['user_id'] != $user_id) {
            echo json_encode(['success' => false, 'message' => 'Você não tem permissão para editar este comentário.']);
            exit;
        }

        // Atualiza o comentário
        $updateStmt = $pdo->prepare("UPDATE comments SET comment = ? WHERE id = ?");
        $updateStmt->execute([$new_comment, $comment_id]);

        echo json_encode(['success' => true, 'message' => 'Comentário atualizado com sucesso.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar comentário: ' . $e->getMessage()]);
    }
}
?>
