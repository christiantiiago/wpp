<?php
require 'db_config.php';
session_start();

$response = ['success' => false];

if (isset($_SESSION['user_id']) && isset($_POST['followed_id'])) {
    $follower_id = $_SESSION['user_id'];
    $followed_id = intval($_POST['followed_id']);

    try {
        // Verifica se a relação já existe
        $stmt = $pdo->prepare("SELECT 1 FROM followers WHERE follower_id = ? AND following_id = ?");
        $stmt->execute([$follower_id, $followed_id]);

        if (!$stmt->fetch()) {
            // Adiciona a relação de seguimento
            $stmt = $pdo->prepare("INSERT INTO followers (follower_id, following_id) VALUES (?, ?)");
            $stmt->execute([$follower_id, $followed_id]);

            // Adiciona uma notificação para o usuário seguido
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, message, extra_data, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            $extraData = json_encode(['follower_id' => $follower_id]);
            $message = "Você tem um novo seguidor!";
            $stmt->execute([$followed_id, $message, $extraData]);

            $response['success'] = true;
            $response['message'] = 'Agora você está seguindo este usuário.';
        } else {
            $response['message'] = 'Você já segue este usuário.';
        }
    } catch (Exception $e) {
        $response['error'] = 'Erro ao seguir o usuário: ' . $e->getMessage();
    }
} else {
    $response['error'] = 'Dados insuficientes para processar a solicitação.';
}

echo json_encode($response);
?>
