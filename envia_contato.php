<?php
/**
 * Script de Envio de Formulário via SMTP Seguro (SSL/TLS na Porta 465)
 * Achei no Totem - Lion Force Franchising
 */

// Permite chamadas de subdiretórios (ex: totem/)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Método de requisição inválido."]);
    exit;
}

// Higienização e Coleta dos Dados do Formulário
$nome = strip_tags(trim($_POST['nome'] ?? ''));
$whatsapp = strip_tags(trim($_POST['whatsapp'] ?? ''));
$email = strip_tags(trim($_POST['email'] ?? ''));
$cidade = strip_tags(trim($_POST['cidade'] ?? ''));
$estado = strip_tags(trim($_POST['estado'] ?? ''));
$observacoes = strip_tags(trim($_POST['observacoes'] ?? ''));
$totens = strip_tags(trim($_POST['totens'] ?? ''));

// Validação Básica
if (empty($nome) || empty($whatsapp) || empty($email)) {
    echo json_encode(["status" => "error", "message" => "Por favor, preencha Nome, WhatsApp e E-mail."]);
    exit;
}

// Configurações do Servidor SMTP (Fornecidas pelo Cliente)
$smtpServer = "mail.acheinototem.com.br";
$smtpPort = 465;
$smtpUser = "contatonoreplay@acheinototem.com.br";
$smtpPass = "SvvtiJf0Qev9";

// Destinatário do Lead
$toEmail = "juniortalhaferro@gmail.com"; 

// Assunto do E-mail
$subject = "Novo Lead Comercial - Achei no Totem";

// Construção da Mensagem em Texto Puro
$messageBody = "Você recebeu um novo contato pelo formulário do site:\n\n";
$messageBody .= "--------------------------------------------------\n";
$messageBody .= "Nome: $nome\n";
$messageBody .= "WhatsApp: $whatsapp\n";
$messageBody .= "E-mail: $email\n";
if (!empty($cidade)) $messageBody .= "Cidade: $cidade\n";
if (!empty($estado)) $messageBody .= "Estado: $estado\n";
if (!empty($totens)) $messageBody .= "Quantidade de Totens: $totens\n";
if (!empty($observacoes)) $messageBody .= "Observações: $observacoes\n";
$messageBody .= "--------------------------------------------------\n\n";
$messageBody .= "Enviado em: " . date("d/m/Y H:i:s") . "\n";
$messageBody .= "IP do Visitante: " . ($_SERVER['REMOTE_ADDR'] ?? 'Indefinido') . "\n";

// Disparo via Socket SMTP Puro
try {
    $mailSent = sendSmtpEmail($smtpServer, $smtpPort, $smtpUser, $smtpPass, $smtpUser, $toEmail, $subject, $messageBody, $email, $nome);
    if ($mailSent) {
        echo json_encode(["status" => "success", "message" => "E-mail enviado com sucesso!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Falha ao enviar e-mail."]);
    }
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Erro de envio: " . $e->getMessage()]);
}

/**
 * Função de envio de e-mail via socket SMTP seguro
 */
function sendSmtpEmail($server, $port, $user, $pass, $from, $to, $subject, $body, $replyTo, $replyName) {
    // Conexão via Socket com protocolo SSL
    $socket = fsockopen("ssl://" . $server, $port, $errno, $errstr, 15);
    if (!$socket) {
        throw new Exception("Falha na conexão socket: $errstr ($errno)");
    }
    
    // Lê a mensagem de boas-vindas do servidor
    fgets($socket, 512);
    
    // Handshake EHLO
    fwrite($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost') . "\r\n");
    readSmtpResponse($socket);
    
    // Autenticação SMTP
    fwrite($socket, "AUTH LOGIN\r\n");
    readSmtpResponse($socket);
    
    // Envia usuário base64
    fwrite($socket, base64_encode($user) . "\r\n");
    readSmtpResponse($socket);
    
    // Envia senha base64
    fwrite($socket, base64_encode($pass) . "\r\n");
    $authResponse = readSmtpResponse($socket);
    if (strpos($authResponse, '235') === false) {
        throw new Exception("Falha de autenticação (User/Pass incorretos): " . $authResponse);
    }
    
    // Define remetente (MAIL FROM)
    fwrite($socket, "MAIL FROM: <$from>\r\n");
    readSmtpResponse($socket);
    
    // Define destinatário (RCPT TO)
    fwrite($socket, "RCPT TO: <$to>\r\n");
    readSmtpResponse($socket);
    
    // Comando DATA
    fwrite($socket, "DATA\r\n");
    readSmtpResponse($socket);
    
    // Montagem dos cabeçalhos MIME para suporte a caracteres acentuados (UTF-8)
    $headers = "From: Achei no Totem <$from>\r\n";
    $headers .= "To: <$to>\r\n";
    $headers .= "Reply-To: \"$replyName\" <$replyTo>\r\n";
    $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    
    // Envia cabeçalhos + corpo e finaliza a transmissão de dados com um ponto isolado
    fwrite($socket, $headers . $body . "\r\n.\r\n");
    readSmtpResponse($socket);
    
    // Termina a sessão SMTP
    fwrite($socket, "QUIT\r\n");
    fclose($socket);
    
    return true;
}

/**
 * Lê a resposta do servidor SMTP de forma robusta
 */
function readSmtpResponse($socket) {
    $data = "";
    while ($str = fgets($socket, 512)) {
        $data .= $str;
        // As linhas de resposta SMTP têm 3 dígitos e um espaço no final da mensagem completa
        if (substr($str, 3, 1) == " ") {
            break;
        }
    }
    return $data;
}
