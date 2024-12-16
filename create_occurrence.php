<?php
require 'db_config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Usuário não autenticado.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$title = $data['title'] ?? '';
$description = $data['description'] ?? '';

if (!$title || !$description) {
    echo json_encode(['error' => 'Dados incompletos.']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO occurrences (user_id, title, description) VALUES (?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $title, $description]);
    echo json_encode(['occurrence_id' => $pdo->lastInsertId()]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Erro ao criar ocorrência: ' . $e->getMessage()]);
}
?>
