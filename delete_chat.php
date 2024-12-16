<?php
session_start();
require 'db_config.php';

header('Content-Type: application/json');

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Usuário não autenticado']);
    exit;
}

$user_id = $_SESSION['user_id'];
$recipient_id = intval($_POST['recipient_id'] ?? 0);

if (!$recipient_id) {
    echo json_encode(['error' => 'Destinatário inválido']);
    exit;
}

try {
    // Exclui as mensagens entre os dois usuários
    $stmt = $pdo->prepare("
        DELETE FROM messages
        WHERE (sender_id = ? AND receiver_id = ?)
        OR (sender_id = ? AND receiver_id = ?)
    ");
    $stmt->execute([$user_id, $recipient_id, $recipient_id, $user_id]);

    echo json_encode(['success' => true, 'message' => 'Conversa excluída com sucesso']);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Erro ao excluir conversa']);
}
