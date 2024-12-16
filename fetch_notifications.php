<?php
require 'db_config.php';
session_start();

$response = ['success' => false, 'notifications' => []];

if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];

    try {
        $stmt = $pdo->prepare("
            SELECT id, message, created_at, extra_data 
            FROM notifications 
            WHERE user_id = ? AND is_read = 0 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$userId]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($notifications as &$notification) {
            $notification['extra_data'] = json_decode($notification['extra_data'], true);
        }

        $response['success'] = true;
        $response['notifications'] = $notifications;
    } catch (Exception $e) {
        $response['error'] = 'Erro ao buscar notificações: ' . $e->getMessage();
    }
} else {
    $response['error'] = 'Usuário não autenticado';
}

echo json_encode($response);
?>
