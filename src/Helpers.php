<?php
/**
 * Classe com funcoes utilitarias (helpers) usadas em todo o sistema, como formatacao, validacao e tratamento de arquivos.
 *
 * @author Prof. Eduardo Gomes
 */

class Helpers {
    public static function generateProtocolCode($conn) {
        $year = date('Y');
        $month = date('n');
        $semester = ($month <= 7) ? 1 : 2;
        
        // Busca o ultimo protocolo deste semestre
        $prefix = "{$year}-{$semester}-";
        $query = "SELECT protocol_code FROM requests WHERE protocol_code LIKE :prefix ORDER BY id DESC LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bindValue(':prefix', $prefix . '%');
        $stmt->execute();
        
        $lastProtocol = $stmt->fetchColumn();
        
        if ($lastProtocol) {
            // Extrai o numero de sequencia
            $parts = explode('-', $lastProtocol);
            $sequence = intval(end($parts)) + 1;
        } else {
            $sequence = 1;
        }
        
        // Formato: AAAA-S-NNNN
        return $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    public static function uploadFile($file, $protocolCode, $sequence = 1) {
        $targetDir = __DIR__ . '/../public/uploads/';
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Evitamos o glob() para performance em diretórios grandes
        $newFilename = "{$protocolCode}-" . str_pad($sequence, 2, '0', STR_PAD_LEFT) . ".{$extension}";
        $targetFilePath = $targetDir . $newFilename;

        if (move_uploaded_file($file['tmp_name'], $targetFilePath)) {
            return $newFilename;
        }
        return false;
    }

    public static function translateStatus($status) {
        $translations = [
            'pending' => 'Aberto',
            'approved' => 'Deferido',
            'rejected' => 'Indeferido',
            'concluded' => 'Concluído'
        ];
        return $translations[$status] ?? ucfirst($status);
    }

    public static function getCurrentSemester() {
        $year = date('Y');
        $month = date('n');
        $semester = ($month <= 7) ? 1 : 2;
        return "{$year}/{$semester}";
    }

    public static function verifyTurnstile($response, $secret, $remoteIp = null) {
        if (empty($response)) {
            return ['success' => false, 'message' => 'Token de verificação ausente'];
        }

        $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
        $data = [
            'secret' => $secret,
            'response' => $response
        ];
        
        if ($remoteIp) {
            $data['remoteip'] = $remoteIp;
        }

        $debugInfo = [];

        // Estrategia 1: Tenta cURL se disponivel
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Melhor esforco
            
            $result = curl_exec($ch);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($result !== false) {
                $json = json_decode($result);
                if ($json && $json->success) {
                    return ['success' => true];
                }
                if ($json && isset($json->error_codes)) {
                    $debugInfo[] = 'CF Error: ' . implode(', ', $json->error-codes);
                }
            } else {
                $debugInfo[] = 'cURL Error: ' . $curlError;
            }
        } else {
            $debugInfo[] = 'cURL not available';
        }

        // Estrategia 2: file_get_contents (fallback)
        if (ini_get('allow_url_fopen')) {
            $options = [
                'http' => [
                    'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method'  => 'POST',
                    'content' => http_build_query($data),
                    'ignore_errors' => true // Determina sucesso pelo conteudo, nao pelo cabecalho
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ];
            $context  = stream_context_create($options);
            $result = @file_get_contents($url, false, $context);
            
            if ($result !== false) {
                $json = json_decode($result);
                if ($json && isset($json->success) && $json->success) {
                    return ['success' => true];
                }
                 if ($json && isset($json->{'error-codes'})) {
                    $debugInfo[] = 'CF Error: ' . implode(', ', $json->{'error-codes'});
                }
            } else {
                 $debugInfo[] = 'FGC Error: ' . (error_get_last()['message'] ?? 'Unknown');
            }
        } else {
             $debugInfo[] = 'allow_url_fopen disabled';
        }
        return ['success' => false, 'message' => 'Falha na verificação de segurança.'];
    }

    public static function validatePassword($password) {
        if (strlen($password) < 8) {
            return "A senha deve ter pelo menos 8 caracteres.";
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return "A senha deve conter pelo menos uma letra maiúscula.";
        }
        if (!preg_match('/[a-z]/', $password)) {
            return "A senha deve conter pelo menos uma letra minúscula.";
        }
        if (!preg_match('/[0-9]/', $password)) {
            return "A senha deve conter pelo menos um número.";
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            return "A senha deve conter pelo menos um caractere especial.";
        }
        return true;
    }
}
