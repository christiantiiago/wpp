<?php
require 'db_config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$id = $_POST['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID da ocorrência não fornecido.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT user_id FROM occurrences WHERE id = ?");
    $stmt->execute([$id]);
    $occurrence = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$occurrence || $occurrence['user_id'] != $user_id) {
        echo json_encode(['success' => false, 'message' => 'Você não tem permissão para excluir esta ocorrência.']);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM occurrences WHERE id = ?");
    if ($stmt->execute([$id])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir ocorrência.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no servidor: ' . $e->getMessage()]);
}
?>
