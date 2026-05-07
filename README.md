# IFSC - Sistema de Requerimentos

Sistema web desenvolvido para facilitar a submissão e o acompanhamento de requerimentos acadêmicos pelos alunos do Instituto Federal de Santa Catarina - Câmpus Canoinhas. O sistema conta com uma área pública para os discentes e um painel administrativo completo para servidores, coordenadores e gestores.

## Autor
**Prof. Eduardo Gomes**

## Tecnologias Utilizadas
- **Linguagem:** PHP 8+
- **Banco de Dados:** MySQL (utilizando PDO)
- **Frontend:** HTML5, Tailwind CSS, JavaScript Vanilla
- **Autenticação Administrativa:** Integração LDAP (Active Directory) + Login de Banco Local (Bypass de Dev)
- **Segurança:** Cloudflare Turnstile (Anti-Bot)
- **E-mails:** PHPMailer + Fila de envio assíncrona (Cron Job)

---

## Arquitetura e Configurações

O sistema está dividido em três diretórios principais:

- `config/`: Contém os arquivos de configuração do ambiente e de conexão com banco de dados.
- `public/`: Contém todos os pontos de entrada acessíveis via web (tanto para os alunos quanto para os administradores em `public/admin`).
- `src/`: Contém as classes e serviços do backend (Autenticação, LDAP, Emails, Utilitários).

### 1. Configurações Necessárias

**`config/config.php`**
Este arquivo controla as variáveis globais do sistema. É necessário configurar:
- Constante `BASE_URL`: Caminho raiz de acesso ao sistema (ex: `https://sites.canoinhas.ifsc.edu.br/requerimentos`).
- Constantes `ENABLE_TURNSTILE` e `ENABLE_EMAILS`: Flags booleanas para habilitar ou desabilitar serviços em produção.
- Constantes `TURNSTILE_SITE_KEY` e `TURNSTILE_SECRET_KEY`: Chaves pública (sitekey) e secreta do Cloudflare Turnstile, utilizadas para a validação Anti-Bot.

**`config/database.php`**
Classe de conexão com o banco de dados via PDO. Requer a configuração de:
- `$host` (ex: localhost)
- `$db_name` (nome do banco de dados)
- `$username` (usuário do MySQL)
- `$password` (senha do MySQL)

**`src/LdapService.php`**
Responsável pela integração com o Active Directory. Requer ajuste de:
- Host LDAP (ex: `ldap://seu-servidor-ad`)
- Base DN (ex: `DC=ifsc,DC=edu,DC=br`)

**Servidor de E-mail (SMTP)**
As credenciais de envio de e-mails são configuradas dinamicamente via painel administrativo (na aba de Configurações de E-mail). O arquivo `src/EmailService.php` as busca diretamente do banco de dados na tabela `email_settings`.

---

## Estrutura de Arquivos e Objetivos

### Pasta `public/` (Área do Aluno / Frontend Público)
- **`index.php`**: Página inicial onde os alunos preenchem o formulário (em múltiplas etapas) para abrir novos requerimentos.
- **`check_status.php`**: Página para consultar o andamento de um requerimento através do número de protocolo e e-mail.
- **`submit_request.php`**: Script que recebe e processa o POST do formulário, salvando no banco.
- **`success.php`**: Página de feedback (sucesso) exibida ao final da submissão.
- **`upload_temp.php`**: Endpoint AJAX que recebe os arquivos anexados antes do envio final do formulário.

### Pasta `public/admin/` (Área Administrativa)
- **`index.php`**: Login de administradores.
- **`dashboard.php`**: Painel inicial contendo atalhos e estatísticas de requerimentos pendentes.
- **`request_details.php`**: Tela principal de operação, onde o servidor visualiza os dados do aluno, baixa os anexos, digita pareceres e tramita o protocolo no fluxo (Workflows).
- **`all_requests.php`**: Listagem completa com filtros avançados de busca.
- **`absence_report.php` / `absence_report_teacher.php` / `schedule_report.php`**: Relatórios gerenciais baseados em tipos específicos de requerimentos (como Faltas e Horários Especiais).
- **`courses.php` / `request_types.php` / `roles.php` / `workflows.php` / `users.php`**: Módulos de cadastro e manutenção (CRUD) para alimentar as tabelas base do sistema.
- **`email_config.php`**: Painel para inserir as credenciais do servidor SMTP.
- **`my_history.php`**: Histórico focado na atuação do usuário logado atual.
- **`profile.php`**: Tela de edição de perfil e senha para usuários locais.
- **`logout.php`**: Destrói a sessão administrativa.

### Pasta `src/` (Classes e Serviços Backend)
- **`Auth.php`**: Lida com a lógica de sessão e autenticação, fazendo ponte com banco ou LDAP.
- **`EmailService.php`**: Abstração do PHPMailer para inserir envios na fila ou enviá-los de imediato.
- **`Helpers.php`**: Métodos estáticos auxiliares (ex: formatação de strings, validação do Turnstile, tradução de status).
- **`LdapService.php`**: Executa as binds e consultas no servidor Active Directory.

### Pasta `public/cron/`
- **`process_queue.php`**: Script que deve ser chamado periodicamente pelo Cron Job (Linux) ou Tarefa Agendada (Windows) para despachar a fila pendente de e-mails, não travando a experiência do usuário durante a gravação dos requerimentos.

---

## Dependências e Bibliotecas Externas
- **Tailwind CSS**: Estilização base do frontend (via CDN ou arquivo compilado).
- **Cloudflare Turnstile**: Alternativa ao reCAPTCHA para prevenção de SPAM.
- **PHPMailer**: (Assumindo uso como classe interna ou via Composer) Biblioteca essencial para envios SMTP autenticados.

## Instalação Local

1. Clone o repositório no seu servidor (Apache/Nginx) com suporte a PHP.
2. Crie o banco de dados e importe o arquivo de dump SQL (não incluído por padrão).
3. Ajuste os arquivos `config/config.php` e `config/database.php` com as variáveis locais.
4. Para testes locais de LDAP sem servidor ativo, mude `ENABLE_TURNSTILE` para `false` no `config.php` e utilize o usuário de banco com a senha mestre de fallback configurada no `Auth.php`.
