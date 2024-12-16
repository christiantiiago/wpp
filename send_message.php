<?php
session_start();
require 'db_config.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$recipient_id = isset($_POST['recipient_id']) ? intval($_POST['recipient_id']) : null;

if (!$recipient_id) {
    echo json_encode(['success' => false, 'message' => 'Nenhum destinatário especificado.']);
    exit;
}

// Processa mensagens de texto e arquivos
$message_text = isset($_POST['message_text']) ? trim($_POST['message_text']) : null;
$message_type = 'text';
$file_path = null;

if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['file'];
    $upload_dir = 'uploads/';
    $file_name = time() . '_' . basename($file['name']);
    $file_path = $upload_dir . $file_name;

    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        echo json_encode(['success' => false, 'message' => 'Falha ao enviar o arquivo.']);
        exit;
    }

    $message_type = 'file';
}

try {
    $query = $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, message_text, message_type, file_path, created_at) 
        VALUES (:sender_id, :receiver_id, :message_text, :message_type, :file_path, NOW())
    ");
    $query->execute([
        ':sender_id' => $user_id,
        ':receiver_id' => $recipient_id,
        ':message_text' => $message_text,
        ':message_type' => $message_type,
        ':file_path' => $file_path,
    ]);

    echo json_encode(['success' => true, 'message' => 'Mensagem enviada com sucesso.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao enviar mensagem: ' . $e->getMessage()]);
}
