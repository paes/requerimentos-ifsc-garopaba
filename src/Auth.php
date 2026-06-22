<?php
/**
 * Classe responsavel pela autenticacao de usuarios, integrando o login local e o servico LDAP.
 *
 * @author Prof. Eduardo Gomes
 */
session_start();
require_once 'LdapService.php';
require_once 'Csrf.php';

class Auth
{
    public static function login($email, $password, $conn)
    {
        // TODO-PRODUÇÃO: REMOVER este bloco antes de ir ao ar — bypass exclusivo para dev local
        if (!ENABLE_TURNSTILE && $password === 'dev123') {
            $stmtLocal = $conn->prepare("SELECT email FROM users WHERE email = :email OR email LIKE CONCAT(:email, '@%') LIMIT 1");
            $stmtLocal->execute([':email' => $email]);
            $localUser = $stmtLocal->fetch(PDO::FETCH_ASSOC);

            if ($localUser) {
                $authResult = ['success' => true, 'email' => $localUser['email']];
            } else {
                return false; // Usuário não existe no banco local
            }
        }
        // FLUXO NORMAL: Autentica no LDAP
        else {
            if (!class_exists('LdapService')) {
                return false; // Evita erro 500 se o arquivo falhar em carregar ou LDAP não existir
            }
            $ldap = new LdapService();
            $authResult = $ldap->authenticate($email, $password);
        }

        if ($authResult['success']) {
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = :email");
            $stmt->execute([':email' => $authResult['email']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Previne fixação de sessão: novo ID ao elevar privilégio (login)
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role_id'];

                // Busca cursos associados
                $courseStmt = $conn->prepare("SELECT course_id FROM user_courses WHERE user_id = :uid");
                $courseStmt->execute([':uid' => $user['id']]);
                $_SESSION['user_courses'] = $courseStmt->fetchAll(PDO::FETCH_COLUMN);

                return true;
            }
        }

        return false;
    }

    public static function logout()
    {
        session_destroy();
    }

    public static function check()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php');
            exit;
        }
        // CSRF: toda requisição POST autenticada (admin e portal docente) exige token válido
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::check();
        }
    }

    public static function user()
    {
        return $_SESSION;
    }
}
