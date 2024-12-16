<?php
session_start();
require 'db_config.php';

$user_id = $_SESSION['user_id'];

$query = $pdo->prepare("
    SELECT typing_to 
    FROM users 
    WHERE id = :user_id
");
$query->execute([':user_id' => $user_id]);
$row = $query->fetch(PDO::FETCH_ASSOC);

echo json_encode(['isTyping' => $row['typing_to'] != null]);
