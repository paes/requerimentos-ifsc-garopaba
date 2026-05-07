<?php
/**
 * Classe responsavel pelo envio de e-mails do sistema utilizando a biblioteca PHPMailer.
 *
 * @author Prof. Eduardo Gomes
 */

class EmailService
{
    private $conn;
    private $config;

    public function __construct($conn)
    {
        $this->conn = $conn;
        $this->loadConfig();
    }

    private function loadConfig()
    {
        $stmt = $this->conn->query("SELECT * FROM email_config LIMIT 1");
        $this->config = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function queue($toEmail, $toName, $subject, $body, $trigger = true)
    {
        // Se emails estiverem desabilitados via config (ex: localhost), retorna sucesso cego para nao travar fluxos
        if (!defined('ENABLE_EMAILS') || !ENABLE_EMAILS) {
            return ['success' => true, 'message' => 'Simulação: E-mail (bloqueado em localhost) foi despachado virtuamente.'];
        }

        try {
            $stmt = $this->conn->prepare("INSERT INTO email_queue (to_email, to_name, subject, body, status, created_at) VALUES (:to_email, :to_name, :subject, :body, 'pending', NOW())");
            $stmt->execute([
                ':to_email' => $toEmail,
                ':to_name' => $toName,
                ':subject' => $subject,
                ':body' => $body
            ]);

            // Inicia processo em segundo plano (compativel com Windows)
            if ($trigger) {
                $this->triggerBackgroundProcess();
            }

            return ['success' => true, 'message' => 'Email enfileirado com sucesso.'];
        } catch (Exception $e) {
            // Se falhar ao colocar na fila, nos logamos mas nao paramos o fluxo? 
            // Ou retornamos false para quem chamou saber.
            // Por enquanto, vamos retornar false.
            return ['success' => false, 'message' => 'Erro ao enfileirar email: ' . $e->getMessage()];
        }
    }

    public function triggerBackgroundProcess()
    {
        // Caminho para o script worker
        $scriptPath = realpath(__DIR__ . '/../public/cron/process_queue.php');
        $phpBinary = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';

        // Se rodando via CGI, PHP_BINARY pode ser php-cgi.exe
        // Queremos a versao CLI (php.exe) para tarefas em background
        if (stripos($phpBinary, 'php-cgi') !== false) {
            $cliBinary = str_replace('php-cgi', 'php', $phpBinary);
            if (file_exists($cliBinary)) {
                $phpBinary = $cliBinary;
            } else {
                // Fallback para apenas 'php' e torcer para estar no PATH
                $phpBinary = 'php';
            }
        }

        // Log para depuracao
        $logFile = __DIR__ . '/../public/background_process.log';
        $timestamp = date('Y-m-d H:i:s');

        if (!$scriptPath || !file_exists($scriptPath)) {
            file_put_contents($logFile, "[$timestamp] ERROR: Script not found at " . __DIR__ . '/../public/cron/process_queue.php' . "\n", FILE_APPEND);
            return;
        }

        // Comando para rodar em background no Windows ou Linux
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $command = "start /B \"\" \"$phpBinary\" \"$scriptPath\" > NUL 2>&1";
            exec($command);
        } else {
            // No Linux, precisamos garantir que o stdin, stdout e stderr estejam totalmente redirecionados
            // e que o comando seja rodado em uma sub-shell independente
            $command = "nohup \"$phpBinary\" \"$scriptPath\" > /dev/null 2>&1 </dev/null &";
            exec($command);
        }
    }

    public function processQueue()
    {
        // Busca e-mails pendentes
        // Em ambiente simples, pegamos apenas os 'pendentes'.
        // Para evitar concorrencia em alta carga, talvez queiramos atualizar o status para 'enviando' primeiro.

        // 1. Seleciona pendentes
        $stmt = $this->conn->prepare("SELECT * FROM email_queue WHERE status = 'pending' OR (status = 'failed' AND attempts < 3) LIMIT 10");
        $stmt->execute();
        $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($emails as $email) {
            // Atualiza to sending
            $updateStmt = $this->conn->prepare("UPDATE email_queue SET status = 'sending', last_attempt = NOW(), attempts = attempts + 1 WHERE id = :id");
            $updateStmt->execute([':id' => $email['id']]);

            // Envia
            $result = $this->sendImmediate(
                $email['to_email'],
                $email['to_name'],
                $email['subject'],
                $email['body']
            );

            // Atualiza status based on result
            $finalStatus = $result['success'] ? 'sent' : 'failed';
            $errorMsg = $result['success'] ? null : $result['message'];

            $finishStmt = $this->conn->prepare("UPDATE email_queue SET status = :status, error_message = :error WHERE id = :id");
            $finishStmt->execute([
                ':status' => $finalStatus,
                ':error' => $errorMsg,
                ':id' => $email['id']
            ]);
        }
    }

    private function sendImmediate($toEmail, $toName, $subject, $body)
    {
        if (!$this->config) {
            return ['success' => false, 'message' => 'Configuração de email não encontrada.'];
        }

        $host = $this->config['host'];
        $port = $this->config['port'];
        $username = $this->config['username'];
        $password = $this->config['password'];
        $encryption = $this->config['encryption'];
        $fromEmail = $this->config['from_email'];
        $fromName = $this->config['from_name'];

        if ($encryption === 'ssl') {
            $host = "ssl://" . $host;
        }

        try {
            $maxRetries = 3;
            $attempt = 0;
            $socket = null;
            $connectError = '';

            while ($attempt < $maxRetries) {
                $attempt++;
                try {
                    $socket = fsockopen($host, $port, $errno, $errstr, 30);
                    if ($socket) {
                        break; // Connected successfully
                    }
                    $connectError = "Attempt $attempt: $errstr ($errno)";
                } catch (Exception $e) {
                    $connectError = "Attempt $attempt: " . $e->getMessage();
                }
                sleep(1); // Wait 1 second before retrying
            }

            if (!$socket) {
                throw new Exception("Could not connect to SMTP host after $maxRetries attempts. Last error: $connectError");
            }

            $this->readResponse($socket);

            $this->sendCommand($socket, "EHLO " . gethostname());

            if ($encryption === 'tls') {
                $this->sendCommand($socket, "STARTTLS");
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->sendCommand($socket, "EHLO " . gethostname());
            }

            if (!empty($username) && !empty($password)) {
                $this->sendCommand($socket, "AUTH LOGIN");
                $this->sendCommand($socket, base64_encode($username));
                $this->sendCommand($socket, base64_encode($password));
            }

            $this->sendCommand($socket, "MAIL FROM: <$fromEmail>");
            $this->sendCommand($socket, "RCPT TO: <$toEmail>");
            $this->sendCommand($socket, "DATA");

            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: $fromName <$fromEmail>\r\n";
            $headers .= "To: $toName <$toEmail>\r\n";
            $headers .= "Subject: $subject\r\n";

            $message = "$headers\r\n$body\r\n.\r\n";

            fwrite($socket, $message);
            $this->readResponse($socket);

            $this->sendCommand($socket, "QUIT");
            fclose($socket);

            return ['success' => true, 'message' => 'Email enviado com sucesso.'];

        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Mantem o metodo original para retrocompatibilidade se necessario, 
    // ou o chama de queue() se quisermos forcar a fila em todo lugar.
    // Mas o usuario pediu envio em background, entao vamos fazer send() chamar queue() 
    // OU apenas renomear send para sendImmediate e fazer send() chamar queue().
    // No entanto, o plano disse "Refatorar o envio para usar fila em vez de imediato".
    // Entao vamos fazer send() se comportar como queue().

    public function send($toEmail, $toName, $subject, $body, $trigger = true)
    {
        return $this->queue($toEmail, $toName, $subject, $body, $trigger);
    }

    private function sendCommand($socket, $command)
    {
        fwrite($socket, $command . "\r\n");
        return $this->readResponse($socket);
    }

    private function readResponse($socket)
    {
        $response = "";
        while ($str = fgets($socket, 515)) {
            $response .= $str;
            if (substr($str, 3, 1) == " ") {
                break;
            }
        }
        return $response;
    }
}
