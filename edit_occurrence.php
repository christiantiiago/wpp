<?php
require 'db_config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$id = $_POST['id'] ?? null;
$title = $_POST['title'] ?? null;
$description = $_POST['description'] ?? null;

if (!$id || !$title || !$description) {
    echo json_encode(['success' => false, 'message' => 'Todos os campos são obrigatórios.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT user_id FROM occurrences WHERE id = ?");
    $stmt->execute([$id]);
    $occurrence = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$occurrence || $occurrence['user_id'] != $user_id) {
        echo json_encode(['success' => false, 'message' => 'Você não tem permissão para editar esta ocorrência.']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE occurrences SET title = ?, description = ? WHERE id = ?");
    if ($stmt->execute([$title, $description, $id])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar alterações.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no servidor: ' . $e->getMessage()]);
}
?>
