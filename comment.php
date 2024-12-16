<?php
require 'db_config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $occurrence_id = $_POST['occurrence_id'] ?? null;
    $comment = $_POST['comment'] ?? '';

    if (empty($occurrence_id) || empty($comment)) {
        echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
        exit;
    }

    // Salva o comentário no banco de dados
    try {
        $stmt = $pdo->prepare("INSERT INTO comments (occurrence_id, user_id, comment) VALUES (?, ?, ?)");
        $stmt->execute([$occurrence_id, $user_id, htmlspecialchars($comment)]);

        // Retorna o comentário e as informações do usuário
        echo json_encode([
            'success' => true,
            'user_name' => htmlspecialchars($_SESSION['user_name'], ENT_QUOTES, 'UTF-8'),
            'profile_pic' => htmlspecialchars($_SESSION['profile_pic'] ?? '/assets/default-avatar.png', ENT_QUOTES, 'UTF-8'),
            'comment' => htmlspecialchars($comment, ENT_QUOTES, 'UTF-8'),
            'created_at' => date('d/m/Y H:i')
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar comentário: ' . $e->getMessage()]);
    }
    exit;
}
