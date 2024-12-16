<?php
session_start();
require 'db_config.php';

header('Content-Type: application/json');

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Não autorizado
    echo json_encode(['error' => 'Usuário não autenticado']);
    exit;
}

$user_id = $_SESSION['user_id'];
$recipient_id = intval($_GET['recipient_id'] ?? 0);

// Verifica se o destinatário foi especificado
if (!$recipient_id) {
    http_response_code(400); // Requisição inválida
    echo json_encode(['error' => 'Destinatário não especificado']);
    exit;
}

try {
    // Busca as mensagens mais recentes, excluindo mensagens de usuários bloqueados
    $messagesQuery = $pdo->prepare("
        SELECT 
            m.sender_id, 
            m.message_text, 
            m.message_type, 
            m.file_path, 
            m.created_at, 
            u.name, 
            u.profile_picture
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE (
            (m.sender_id = ? AND m.receiver_id = ?)
            OR (m.sender_id = ? AND m.receiver_id = ?)
        )
        AND NOT EXISTS (
            SELECT 1
            FROM blocked_users
            WHERE blocked_users.user_id = ?
            AND blocked_users.blocked_user_id = m.sender_id
        )
        ORDER BY m.created_at ASC
    ");
    $messagesQuery->execute([$user_id, $recipient_id, $recipient_id, $user_id, $user_id]);
    $messages = $messagesQuery->fetchAll(PDO::FETCH_ASSOC);

    // Formata a data e hora das mensagens no padrão brasileiro
    foreach ($messages as &$message) {
        $datetime = new DateTime($message['created_at']);
        $message['formatted_date'] = $datetime->format('d/m/Y'); // Ex: 15/12/2024
        $message['formatted_time'] = $datetime->format('H:i');   // Ex: 14:30
    }

    echo json_encode(['success' => true, 'messages' => $messages]);
    exit;
} catch (PDOException $e) {
    http_response_code(500); // Erro interno do servidor
    echo json_encode(['error' => 'Erro ao buscar mensagens', 'details' => $e->getMessage()]);
    exit;
}
