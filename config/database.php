<?php
/**
 * Classe de conexao com o banco de dados. Utiliza PDO para estabelecer a comunicacao com o MySQL.
 *
 * @author Prof. Eduardo Gomes
 */

// Carrega variáveis do arquivo .env (se existir) — nunca comitar o .env no git
$_envFile = __DIR__ . '/../.env';
if (file_exists($_envFile)) {
    foreach (file($_envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_line) {
        if (str_starts_with(trim($_line), '#') || !str_contains($_line, '=')) continue;
        [$_k, $_v] = explode('=', $_line, 2);
        $_ENV[trim($_k)] = trim($_v);
    }
}

class Database {
    private $host     = '';
    private $db_name  = '';
    private $username = '';
    private $password = '';
    public $conn;

    public function __construct() {
        $this->host     = $_ENV['DB_HOST']     ?? 'localhost';
        $this->db_name  = $_ENV['DB_NAME']     ?? 'ifsc_requests';
        $this->username = $_ENV['DB_USER']     ?? 'ifsc';
        $this->password = $_ENV['DB_PASSWORD'] ?? '';
    }

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            // Loga o detalhe (host/usuário/erro) só no servidor — nunca exibe ao usuário
            error_log('[Database] Falha de conexão: ' . $exception->getMessage());
            http_response_code(500);
            die("Serviço temporariamente indisponível. Tente novamente em instantes.");
        }

        return $this->conn;
    }
}
