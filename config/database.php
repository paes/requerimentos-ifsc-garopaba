<?php
/**
 * Classe de conexao com o banco de dados. Utiliza PDO para estabelecer a comunicacao com o MySQL.
 *
 * @author Prof. Eduardo Gomes
 */

class Database {
    private $host = 'localhost';
    private $db_name = 'ifsc_requests';
    private $username = 'ifsc';
    private $password = 'ifsc1234';
    public $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            die("Erro de Conexão: " . $exception->getMessage() . "<br>Verifique se a extensão 'pdo_mysql' está habilitada no seu php.ini.");
        }

        return $this->conn;
    }
}
