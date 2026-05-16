# CLAUDE.md — Instruções para o assistente de IA

## O projeto

Sistema de requerimentos acadêmicos do IFSC Câmpus Garopaba.  
PHP 8.4 + MySQL 8 + Tailwind CSS. Sem Composer. Apache + php-fpm.  
Desenvolvido por Prof. Eduardo Gomes (Canoinhas), adaptado por Prof. Thiago Paes (Garopaba).  
Contato: thiago.paes@ifsc.edu.br

---

## O que NUNCA comitar no git

| Arquivo/padrão | Motivo |
|----------------|--------|
| `.env` | Credenciais do banco de dados |
| `storage/email_log/*.html` | Dados pessoais de alunos (LGPD) |
| `public/uploads/*` | Arquivos enviados pelos alunos |
| `public/temp/*` | Arquivos temporários |
| `.claude/` | Memória e planos locais do assistente IA |

Esses padrões já estão no `.gitignore`. Verificar antes de qualquer `git add .`.

---

## Credenciais e configuração

- Credenciais do banco ficam em `.env` (nunca no código)
- Template sem valores: `.env.example` — este SIM pode ser commitado
- Em dev: `ENABLE_TURNSTILE=false`, `ENABLE_EMAILS=false` (definido em `config/config.php`)
- Em dev: bypass de login com senha `dev123` está ativo — **remover antes de produção** (marcado com `TODO-PRODUÇÃO` em `src/Auth.php`)

---

## Banco de dados (dev local)

```
Host: localhost  DB: ifsc_requests  User: ifsc  Pass: ifsc1234
mysql -u ifsc -pifsc1234 ifsc_requests
```

---

## Conformidade institucional — status

### Fase 1 — FEITA (código, sem produção)
- [x] Credenciais fora do código (`.env`)
- [x] Apache: listagem de diretórios desabilitada
- [x] Bypass dev123 marcado para remoção
- [x] Aviso LGPD no formulário e declaração
- [x] `EMAIL_LDAP_DTIC.md` atualizado (pedido LDAP + hospedagem CTIC)
- [x] `SIPAC_SUBMISSAO.md` rascunhado (processo formal)
- [x] Script de limpeza de logs de e-mail (`scripts/limpar_logs_email.php`)
- [ ] TODO-LGPD: criptografia do campo `student_phone` (varbinary sem encrypt)

### Fase 2 — Aguardando comissão de implantação
- [ ] Abrir processo SIPAC → PROEN → DTIC → CGD (usar `SIPAC_SUBMISSAO.md`)
- [ ] Migrar código para git.ifsc.edu.br
- [ ] Enviar `EMAIL_LDAP_DTIC.md` para a CTIC
- [ ] Configurar LDAP Garopaba em `src/LdapService.php`
- [ ] Preencher TODOs de produção em `config/config.php`
- [ ] Remover bypass dev123 de `src/Auth.php`
- [ ] Configurar SMTP Garopaba no painel admin

---

## Arquivos-chave

| Arquivo | O que é |
|---------|---------|
| `public/index.php` | Formulário de requerimento (aluno) |
| `public/admin/` | Painel administrativo |
| `public/submit_request.php` | Processa o envio do formulário |
| `src/Auth.php` | Autenticação (LDAP + bypass dev) |
| `src/LdapService.php` | Integração com AD (TODO: configurar Garopaba) |
| `src/EmailService.php` | Envio de e-mails via PHPMailer |
| `src/EmailTemplate.php` | Template institucional IFSC para e-mails |
| `config/database.php` | Conexão PDO (lê credenciais do .env) |
| `config/config.php` | Constantes globais (BASE_URL, flags de produção) |
| `dump/ifsc_requests.sql` | Schema e dados do banco |

---

## RDP — Regulamento Didático-Pedagógico

Resolução CONSUP nº 20/2018. Referência para textos descritivos dos tipos de requerimento.  
Os textos em `request_types.information` foram baseados nos artigos do RDP com citação explícita.
