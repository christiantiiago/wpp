<?php
session_start();
require 'db_config.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// ID do usuário logado
$user_id = $_SESSION['user_id'];

// ID do destinatário
$recipient_id = isset($_GET['recipient_id']) ? intval($_GET['recipient_id']) : null;

if (!$recipient_id) {
    die("Erro: Nenhum destinatário especificado. Certifique-se de acessar a página com o parâmetro recipient_id.");
}

// Obtém informações do destinatário
$recipientQuery = $pdo->prepare("SELECT id, name, profile_picture, is_online FROM users WHERE id = ?");
$recipientQuery->execute([$recipient_id]);
$recipient = $recipientQuery->fetch(PDO::FETCH_ASSOC);

if (!$recipient) {
    die("Erro: Destinatário não encontrado.");
}

// Obtém informações do usuário logado
$userQuery = $pdo->prepare("SELECT id, name, profile_picture FROM users WHERE id = ?");
$userQuery->execute([$user_id]);
$currentUser = $userQuery->fetch(PDO::FETCH_ASSOC);

if (!$currentUser) {
    die("Erro: Usuário não encontrado.");
}

// Busca mensagens entre os usuários
$messagesQuery = $pdo->prepare("
    SELECT m.sender_id, m.message_text, m.message_type, m.file_path, m.created_at, u.name, u.profile_picture 
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE (m.sender_id = ? AND m.receiver_id = ?) 
       OR (m.sender_id = ? AND m.receiver_id = ?)
    ORDER BY m.created_at ASC
");
$messagesQuery->execute([$user_id, $recipient_id, $recipient_id, $user_id]);
$messages = $messagesQuery->fetchAll(PDO::FETCH_ASSOC);

// Filtra mensagens para garantir que tenham conteúdo válido
$messages = array_filter($messages, function ($message) {
    return !empty($message['message_text']) || !empty($message['file_path']);
});

// Marca mensagens como lidas
try {
    $markAsReadQuery = $pdo->prepare("UPDATE messages SET is_read = 1 
                                      WHERE sender_id = :recipient_id 
                                      AND receiver_id = :user_id 
                                      AND is_read = 0");
    $markAsReadQuery->bindParam(':recipient_id', $recipient_id, PDO::PARAM_INT);
    $markAsReadQuery->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $markAsReadQuery->execute();
} catch (PDOException $e) {
    die("Erro ao marcar mensagens como lidas: " . $e->getMessage());
}

// Função para formatar data e hora no padrão brasileiro
function formatDateTime($dateString)
{
    $date = new DateTime($dateString);
    $formattedDate = $date->format('d \d\e F \d\e Y'); // Exemplo: 15 de dezembro de 2024
    $time = $date->format('H:i'); // Exemplo: 14:20
    return ['date' => ucfirst(strftime('%A, %d de %B de %Y', $date->getTimestamp())), 'time' => $time];
}

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat com <?= htmlspecialchars($recipient['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/chat.css">

</head>

<body>
    <div class="chat-container">
        <!-- Cabeçalho do Chat -->
        <div class="chat-header">
            <div class="user-info">
                <div class="profile-picture-wrapper">
                    <img src="<?= htmlspecialchars($recipient['profile_picture'] ?? 'default-profile.png') ?>"
                        alt="Foto do usuário"
                        class="profile-picture"
                        onclick="openImageModal(this.src)">
                    <div class="status-indicator">
                        <i class="fas fa-circle" style="color: <?= $recipient['is_online'] ? 'green' : 'red'; ?>"></i>
                        <?= $recipient['is_online'] ? 'Online' : 'Offline'; ?>
                    </div>
                </div>
                <div class="user-details">
                    <span class="user-name"><?= htmlspecialchars($recipient['name']) ?></span>
                </div>
            </div>





            <div class="actions">
                <button onclick="location.href='fed.php'" title="Voltar ao Feed">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <button onclick="location.href='profile.php?id=<?= htmlspecialchars($recipient_id) ?>'" title="Ir ao Perfil">
                    <i class="fas fa-user"></i>
                </button>
            </div>
        </div>

        <!-- Área de Mensagens -->
        <div class="chat-messages" id="chatMessages"></div>

        <!-- Rodapé do Chat -->
        <div class="chat-footer">
            <label for="fileInput" class="btn btn-outline-secondary">
                <i class="fas fa-paperclip"></i>
            </label>
            <input type="file" id="fileInput" class="d-none">

            <button id="audioRecordButton" class="btn btn-outline-secondary">
                <i class="fas fa-microphone"></i>
            </button>

            <input type="text" id="chatInput" class="form-control" placeholder="Digite sua mensagem">
            <button id="sendButton" class="send-button">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>

    <div id="imageModal" class="modal" onclick="closeImageModal(event)">
        <span class="modal-close" onclick="closeImageModal(event)">&times;</span>
        <img id="modalImage" src="" alt="Imagem Ampliada">
    </div>

    <script>
        const chatMessages = document.getElementById('chatMessages');
        const chatInput = document.getElementById('chatInput');
        const sendButton = document.getElementById('sendButton');
        const fileInput = document.getElementById('fileInput');
        const audioRecordButton = document.getElementById('audioRecordButton');
        const recipientId = <?= json_encode($recipient_id) ?>;
        let mediaRecorder, audioChunks = [];
        let isRecording = false;
        let lastMessageTimestamp = null; // Controla o último timestamp das mensagens carregadas
        let isLoading = false; // Previne múltiplos carregamentos simultâneos

        // Função para formatar data e hora
        function formatDate(dateString) {
            const date = new Date(dateString);
            const options = {
                day: '2-digit',
                month: 'long',
                year: 'numeric'
            };
            return date.toLocaleDateString('pt-BR', options);
        }

        function formatTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleTimeString('pt-BR', {
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // Renderiza mensagens
        function renderMessages(messages) {
            chatMessages.innerHTML = ''; // Limpa mensagens
            let lastDate = null;

            messages.forEach(msg => {
                const msgDate = formatDate(msg.created_at);

                // Adiciona separador de data
                if (lastDate !== msgDate) {
                    const dateDivider = document.createElement('div');
                    dateDivider.className = 'date-divider';
                    dateDivider.textContent = msgDate;
                    chatMessages.appendChild(dateDivider);
                    lastDate = msgDate;
                }

                // Estrutura da mensagem
                const messageDiv = document.createElement('div');
                messageDiv.className = `message ${msg.sender_id == <?= $user_id ?> ? 'sent' : 'received'}`;

                const messageContent = document.createElement('div');
                messageContent.className = 'message-content';
                messageContent.textContent = msg.message_text;

                const timeSpan = document.createElement('span');
                timeSpan.className = 'message-time';
                timeSpan.textContent = formatTime(msg.created_at);

                messageContent.appendChild(timeSpan);
                messageDiv.appendChild(messageContent);
                chatMessages.appendChild(messageDiv);
            });

            // Rola para o final
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }


        // Função para buscar mensagens via fetch
        async function fetchMessages() {
            try {
                const response = await fetch('fetch_messages.php?recipient_id=' + recipient_id);
                const data = await response.json();
                if (data.success) {
                    renderMessages(data.messages);
                }
            } catch (error) {
                console.error('Erro ao buscar mensagens:', error);
            }
        }

        // Atualiza mensagens periodicamente
        setInterval(fetchMessages, 2000);
        fetchMessages(); // Busca mensagens ao carregar a página


        // Função para abrir o modal com imagem
        function openImageModal(src) {
            const modal = document.getElementById('imageModal');
            const modalImage = document.getElementById('modalImage');
            modalImage.src = src;
            modal.style.display = 'flex';
        }

        // Função para fechar o modal
        function closeImageModal(event) {
            const modal = document.getElementById('imageModal');
            if (event.target === modal || event.target.classList.contains('modal-close')) {
                modal.style.display = 'none';
                document.getElementById('modalImage').src = '';
            }
        }

        // Adiciona evento para as imagens da área de mensagens
        document.addEventListener("DOMContentLoaded", () => {
            const images = document.querySelectorAll('.message-content img');
            images.forEach(img => img.addEventListener('click', () => openImageModal(img.src)));
        });

        //status online
        async function updateStatus() {
            try {
                const response = await fetch(`get_user_status.php?recipient_id=<?= $recipient_id ?>`);
                const data = await response.json();

                if (data.success) {
                    const statusElement = document.querySelector('.user-status');
                    const statusIndicator = statusElement.querySelector('.status-indicator');

                    if (data.status === 'online') {
                        statusIndicator.classList.remove('offline');
                        statusIndicator.classList.add('online');
                        statusElement.innerHTML = '<i class="status-indicator online"></i> Online';
                    } else {
                        statusIndicator.classList.remove('online');
                        statusIndicator.classList.add('offline');
                        statusElement.innerHTML = '<i class="status-indicator offline"></i> Offline';
                    }
                }
            } catch (error) {
                console.error('Erro ao atualizar status:', error);
            }
        }

        // Atualiza o status a cada 5 segundos
        setInterval(updateStatus, 5000);

        // Atualiza mensagens
        async function fetchMessages() {
            if (isLoading) return; // Previne múltiplos carregamentos ao mesmo tempo
            isLoading = true;

            try {
                const response = await fetch(`fetch_messages.php?recipient_id=${recipientId}`);
                const data = await response.json();

                if (data.success) {
                    const messages = data.messages;

                    // Verifica se há novas mensagens
                    if (messages.length > 0) {
                        const latestMessage = messages[messages.length - 1];
                        const latestTimestamp = new Date(latestMessage.created_at).getTime();

                        if (lastMessageTimestamp === null || latestTimestamp > lastMessageTimestamp) {
                            chatMessages.innerHTML = ''; // Limpa mensagens antes de renderizar novamente

                            messages.forEach(msg => {
                                const messageDiv = document.createElement('div');
                                messageDiv.classList.add('message', msg.sender_id == <?= $user_id ?> ? 'sent' : 'received');

                                const userImg = document.createElement('img');
                                userImg.src = msg.profile_picture ?? 'default-profile.png';
                                userImg.alt = "Foto do usuário";

                                const messageContent = document.createElement('div');
                                messageContent.classList.add('message-content');

                                if (msg.message_type === 'file' && msg.file_path) {
                                    if (/\.(jpg|jpeg|png|gif)$/i.test(msg.file_path)) {
                                        const img = document.createElement('img');
                                        img.src = msg.file_path;
                                        img.alt = "Imagem";
                                        img.classList.add('file-preview');
                                        messageContent.appendChild(img);
                                    } else if (/\.(mp4|webm)$/i.test(msg.file_path)) {
                                        const video = document.createElement('video');
                                        video.src = msg.file_path;
                                        video.controls = true;
                                        video.classList.add('video-preview');
                                        messageContent.appendChild(video);
                                    } else if (/\.(mp3|wav)$/i.test(msg.file_path)) {
                                        const audio = document.createElement('audio');
                                        audio.src = msg.file_path;
                                        audio.controls = true;
                                        messageContent.appendChild(audio);
                                    } else {
                                        const fileLink = document.createElement('a');
                                        fileLink.href = msg.file_path;
                                        fileLink.target = '_blank';
                                        fileLink.textContent = `📎 ${msg.file_path.split('/').pop()}`;
                                        messageContent.appendChild(fileLink);
                                    }
                                } else if (msg.message_text) {
                                    const textMessage = document.createElement('p');
                                    textMessage.textContent = msg.message_text;
                                    messageContent.appendChild(textMessage);
                                }

                                messageDiv.appendChild(userImg);
                                messageDiv.appendChild(messageContent);
                                chatMessages.appendChild(messageDiv);
                            });

                            // Rola para o final após adicionar as mensagens
                            setTimeout(() => {
                                chatMessages.scrollTop = chatMessages.scrollHeight;
                            }, 100); // Pequeno delay para garantir que o DOM renderize
                        }
                        lastMessageTimestamp = latestTimestamp; // Atualiza o timestamp da última mensagem
                    }
                }
            } catch (error) {
                console.error('Erro ao buscar mensagens:', error);
            } finally {
                isLoading = false; // Libera o bloqueio de carregamento
            }
        }

        // Envia mensagem
        async function sendMessage() {
            const messageText = chatInput.value.trim();
            if (!messageText) return;

            try {
                const response = await fetch('send_message.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        recipient_id: recipientId,
                        message_text: messageText
                    })
                });

                if (response.ok) {
                    chatInput.value = '';
                    fetchMessages(); // Atualiza mensagens após o envio
                }
            } catch (error) {
                console.error('Erro ao enviar mensagem:', error);
            }
        }

        // Gravação de áudio
        audioRecordButton.addEventListener('click', async () => {
            if (isRecording) {
                mediaRecorder.stop();
                isRecording = false;
                audioRecordButton.innerHTML = '<i class="fas fa-microphone"></i>';
            } else {
                const stream = await navigator.mediaDevices.getUserMedia({
                    audio: true
                });
                mediaRecorder = new MediaRecorder(stream);
                audioChunks = [];

                mediaRecorder.ondataavailable = (event) => audioChunks.push(event.data);
                mediaRecorder.onstop = async () => {
                    const audioBlob = new Blob(audioChunks, {
                        type: 'audio/webm'
                    });
                    const formData = new FormData();
                    formData.append('file', audioBlob, 'audio.webm');
                    formData.append('recipient_id', recipientId);

                    await fetch('send_message.php', {
                        method: 'POST',
                        body: formData
                    });
                    fetchMessages();
                };

                mediaRecorder.start();
                isRecording = true;
                audioRecordButton.innerHTML = '<i class="fas fa-stop-circle"></i>';
            }
        });

        // Envia arquivos
        fileInput.addEventListener('change', async () => {
            if (fileInput.files.length > 0) {
                const formData = new FormData();
                formData.append('file', fileInput.files[0]);
                formData.append('recipient_id', recipientId);

                try {
                    const response = await fetch('send_message.php', {
                        method: 'POST',
                        body: formData
                    });

                    if (response.ok) {
                        fetchMessages(); // Atualiza mensagens após envio de arquivo
                    }
                } catch (error) {
                    console.error('Erro ao enviar arquivo:', error);
                }
            }
        });

        // Eventos
        sendButton.addEventListener('click', sendMessage);
        chatInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                sendMessage();
            }
        });

        // Atualiza as mensagens automaticamente a cada 2 segundos
        setInterval(fetchMessages, 2000);
        fetchMessages(); // Busca mensagens ao carregar a página
    </script>
</body>

</html>