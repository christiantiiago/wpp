<?php
session_start();
require 'db_config.php';

$user_id = $_SESSION['user_id'];
$recipient_id = $_POST['recipient_id'];

$query = $pdo->prepare("
    UPDATE users 
    SET typing_to = :recipient_id 
    WHERE id = :user_id
");
$query->execute([
    ':recipient_id' => $recipient_id,
    ':user_id' => $user_id,
]);

echo json_encode(['success' => true]);
