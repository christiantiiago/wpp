<?php
require 'db_config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$occurrence_id = $_GET['id'] ?? null;

if (!$occurrence_id) {
    echo json_encode(['success' => false, 'message' => 'Ocorrência inválida.']);
    exit;
}

try {
    // Verificar se o usuário já curtiu esta ocorrência
    $checkStmt = $pdo->prepare("SELECT * FROM likes WHERE user_id = ? AND occurrence_id = ?");
    $checkStmt->execute([$user_id, $occurrence_id]);
    $likeExists = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($likeExists) {
        // Remover a curtida
        $deleteStmt = $pdo->prepare("DELETE FROM likes WHERE user_id = ? AND occurrence_id = ?");
        $deleteStmt->execute([$user_id, $occurrence_id]);
    } else {
        // Adicionar a curtida
        $insertStmt = $pdo->prepare("INSERT INTO likes (user_id, occurrence_id) VALUES (?, ?)");
        $insertStmt->execute([$user_id, $occurrence_id]);
    }

    // Contar todas as curtidas para a ocorrência
    $countStmt = $pdo->prepare("SELECT COUNT(*) as total_likes FROM likes WHERE occurrence_id = ?");
    $countStmt->execute([$occurrence_id]);
    $likeCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total_likes'];

    // Atualizar a tabela de ocorrências com o número de curtidas
    $updateStmt = $pdo->prepare("UPDATE occurrences SET likes = ? WHERE id = ?");
    $updateStmt->execute([$likeCount, $occurrence_id]);

    echo json_encode(['success' => true, 'likes' => $likeCount]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar curtidas.']);
}
?>
