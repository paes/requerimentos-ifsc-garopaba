<?php
/**
 * Classe responsavel pela integracao com o Active Directory (LDAP) para autenticacao unificada dos servidores.
 *
 * @author Prof. Eduardo Gomes
 */

class LdapService
{
    // TODO: preencher com os dados do servidor LDAP do campus Garopaba (fornecido pela DTIC/CTIC)
    private $host = "TODO_LDAP_HOST_GAROPABA";
    private $port = 389;
    private $baseDns = [
        "TODO_BASE_DN_GAROPABA_1", // Primeira prioridade — ex: ou=Garopaba,ou=Usuarios,dc=cefetsc,dc=edu,dc=br
        "TODO_BASE_DN_GAROPABA_2"  // Segunda prioridade (se houver OU alternativa)
    ];
    private $conn;

    public function connect()
    {
        if (!function_exists('ldap_connect')) {
            return false;
        }
        $this->conn = ldap_connect($this->host, $this->port);
        if (!$this->conn) {
            return false;
        }

        ldap_set_option($this->conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($this->conn, LDAP_OPT_REFERRALS, 0);

        return true;
    }

    public function authenticate($username, $password)
    {
        if (!$this->connect()) {
            return ['success' => false, 'message' => 'Erro de conexão com servidor LDAP.'];
        }

        // 1. Bind anonimo para encontrar o DN do usuario
        $bindAnon = @ldap_bind($this->conn);
        if (!$bindAnon) {
            return ['success' => false, 'message' => 'Erro: Servidor recusou conexão anônima.'];
        }

        $userInfo = null;
        $foundBase = null;

        // 2. Busca pelo usuario em cada DN base
        foreach ($this->baseDns as $baseDn) {
            $filter = "(uid=" . ldap_escape($username, "", LDAP_ESCAPE_FILTER) . ")";
            $attributes = ['dn', 'cn', 'mail', 'uid'];

            $search = @ldap_search($this->conn, $baseDn, $filter, $attributes);
            if ($search) {
                $info = ldap_get_entries($this->conn, $search);
                if ($info['count'] > 0) {
                    $userInfo = $info[0];
                    break;
                }
            }
        }

        if (!$userInfo) {
            return ['success' => false, 'message' => 'Usuário não encontrado na base LDAP.'];
        }

        $userDn = $userInfo['dn'];
        $userMail = $userInfo['mail'][0] ?? null;
        $userName = $userInfo['cn'][0] ?? $username;

        // 3. Bind real com DN do Usuario e Senha
        $auth = @ldap_bind($this->conn, $userDn, $password);

        if ($auth) {
            return [
                'success' => true,
                'email' => $userMail,
                'name' => $userName,
                'uid' => $userInfo['uid'][0] ?? $username
            ];
        } else {
            return ['success' => false, 'message' => 'Senha incorreta.'];
        }
    }

    public function close()
    {
        if ($this->conn) {
            ldap_unbind($this->conn);
        }
    }
}
