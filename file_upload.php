<?php
require 'db_config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Usuário não autenticado.']);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $occurrenceId = $_POST['occurrence_id'] ?? null;
    $chunk = $_FILES['file'] ?? null;

    if (!$occurrenceId || !$chunk) {
        echo json_encode(['error' => 'Dados incompletos.']);
        exit;
    }

    $uploadDir = 'uploads/';
    $fileName = $_POST['file_name'];
    $chunkIndex = $_POST['chunk_index'];
    $totalChunks = $_POST['total_chunks'];

    // Define o caminho do arquivo temporário para o chunk
    $filePath = $uploadDir . $fileName . ".part{$chunkIndex}";
    move_uploaded_file($chunk['tmp_name'], $filePath);

    // Verifica se todos os chunks foram enviados
    if ($chunkIndex + 1 == $totalChunks) {
        $finalPath = $uploadDir . $fileName;
        $output = fopen($finalPath, 'wb');

        for ($i = 0; $i < $totalChunks; $i++) {
            $partPath = "{$uploadDir}{$fileName}.part{$i}";
            $chunkData = fopen($partPath, 'rb');
            stream_copy_to_stream($chunkData, $output);
            fclose($chunkData);
            unlink($partPath); // Remove o chunk depois de concatenar
        }
        fclose($output);

        // Salva no banco de dados
        $stmt = $pdo->prepare("INSERT INTO files (occurrence_id, file_name, file_path, file_type, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $occurrenceId,
            $fileName,
            $finalPath,
            mime_content_type($finalPath),
            filesize($finalPath),
            $user_id,
        ]);

        echo json_encode(['success' => true, 'message' => 'Arquivo carregado e salvo com sucesso.']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Chunk recebido com sucesso.']);
    exit;
}
?>
