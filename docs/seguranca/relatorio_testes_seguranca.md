# Relatório de Testes de Segurança — Sistema de Requerimentos IFSC Garopaba

> Documento de registro dos testes e correções de segurança realizados, para
> consolidação em relatório formal e apoio à **Etapa 6 (Varredura de Segurança da DTIC)**
> do [Roteiro de Implantação](../../ROTEIRO_IMPLANTACAO.pdf), conforme a
> [Resolução CGD nº 03/2025](../normas/Resolucao_CGD_03_2025_Politica_Desenvolvimento_Sistemas.md).

| | |
|---|---|
| **Data dos testes** | 22/06/2026 |
| **Responsável técnico** | Prof. Thiago Paes |
| **Ambiente de teste** | PHP 8.4.20 (CLI) · MySQL 8 · Linux (Nobara/fc43) |
| **Servidor usado nos testes** | `php -S` (servidor embutido) com banco real, flags de DEV |
| **Flags de dev** | `ENABLE_TURNSTILE=false`, `ENABLE_EMAILS=false`, bypass `dev123` ativo |
| **Escopo** | `src/` e `public/` (handlers públicos, autenticação, uploads, admin, docente) |

---

## 1. Metodologia

1. **Auditoria estática manual** do código (OWASP Top 10 2021 + falhas específicas de PHP), com leitura dos handlers públicos, autenticação, uploads e telas administrativas.
2. **Correção** das vulnerabilidades encontradas (commits abaixo).
3. **Verificação automatizada**:
   - Análise de sintaxe com `php -l` em todos os arquivos alterados.
   - Subida do servidor embutido (`php -S`) e **testes dinâmicos com `curl`** contra o banco real, exercitando CSRF, validação e autenticação de ponta a ponta.

> **Limitações conhecidas:** os testes foram conduzidos em ambiente local de desenvolvimento. Não substituem a varredura oficial de segurança da DTIC (ferramentas de varredura em aplicações web — exigência da Resolução CGD 03/2025). Itens marcados como *inspeção* foram validados por leitura de código + lint, não por exploração dinâmica.

---

## 2. Vulnerabilidades encontradas e corrigidas

| # | Severidade | Vulnerabilidade | OWASP / CWE | Local | Correção (commit) |
|---|---|---|---|---|---|
| 1 | 🔴 Crítica | Path traversal no manuseio de anexos (`temp_files` controlável → mover/expor arquivos do servidor; potencial RCE) | A01/A03 · CWE-22/434 | `submit_request.php`, `responsaveis/submit_requerimento.php` | `a0cd9bb`, `27a0887` |
| 2 | 🟠 Maior | Ausência de proteção CSRF em ações autenticadas | A01 · CWE-352 | portal responsáveis, `/admin`, portal docente | `27a0887`, `bacd1f0` |
| 3 | 🟠 Maior | Validação de entrada apenas no cliente (form `novalidate`) | A03 · CWE-20 | `submit_request.php` | `613b168` |
| 4 | 🟠 Maior | Vazamento de informação em mensagens de erro (`getMessage()` ao usuário) | A05 · CWE-209 | `submit_request.php`, `config/database.php` | `a0cd9bb` |
| 5 | 🟠 Maior | XSS armazenado (nome do responsável em `onclick` via `addslashes`) | A03 · CWE-79 | `admin/guardian_registrations.php` | `b4594c6` |
| 6 | 🟠 Maior | Sem `session_regenerate_id()` no login (fixação de sessão) | A07 · CWE-384 | `src/Auth.php`, `responsaveis/login.php` | `a0cd9bb` |
| 7 | 🟡 Menor | Upload validado só por extensão (sem checagem de MIME real) | A04 · CWE-434 | `upload_temp.php` | `613b168` |
| 8 | 🟡 Menor | `mkdir(..., 0777)` no diretório temporário | CWE-732 | `upload_temp.php` | `613b168` |
| 9 | 🟡 Menor | Conexão PDO sem `charset=utf8mb4` | — | `config/database.php` | `613b168` |
| 10 | 🟡 Menor | Pasta `public/uploads/` não criada pelo handler → **perda silenciosa de anexos** em implantação sem o diretório (descoberto no teste E2E 3.7) | A04 (disponibilidade/integridade) | `submit_request.php`, `responsaveis/submit_requerimento.php` | (correção + `.gitkeep`) |

### Itens identificados e **adiados** (exigem mais que um patch)
- **AES-256-CBC sem autenticação (HMAC)** em `src/CryptoHelper.php` — migrar para AES-GCM exige **re-criptografar os telefones já gravados** (script de migração). *Pendente.*
- **Rate-limit do login de responsáveis por sessão** (burlável) — um limite por IP exige armazenamento persistente. *Pendente.*

### Itens verificados como **já conformes** (mérito do código existente)
- Uso consistente de **prepared statements (PDO)** — nenhuma SQL injection encontrada.
- `admin/guardian_pdf.php` usa `basename()` + verificação de autorização (sysadmin/Coord. Pedagógica).
- `admin/request_details.php` escapa todas as saídas de dados do usuário (`htmlspecialchars`, `nl2br(htmlspecialchars())`).
- `responsaveis/submit_cadastro.php` valida MIME, tamanho e CPF; CPF com hash e telefone cifrado (LGPD).
- Saídas em `check_status.php` e e-mails usam `htmlspecialchars`.

---

## 3. Testes executados e resultados

### 3.1 Análise estática (`php -l`)
**Resultado: APROVADO.** Sem erros de sintaxe em todos os arquivos alterados:
`src/Csrf.php`, `src/Auth.php`, `config/database.php`, `public/submit_request.php`, `public/index.php`,
`public/upload_temp.php`, `public/responsaveis/*` (login, guard, cadastro, submit_cadastro,
novo_requerimento, confirmar_otp, submit_requerimento, trocar_senha), `public/admin/*.php` (16 arquivos),
`public/docentes/novo.php`.

### 3.2 Disponibilidade das páginas públicas (GET)
**Resultado: APROVADO.**

| Página | HTTP |
|---|---|
| `index.php` | 200 |
| `check_status.php` | 200 |
| `responsaveis/login.php` | 200 |
| `responsaveis/cadastro.php` | 200 |

### 3.3 Proteção CSRF — Portal de Responsáveis
**Resultado: APROVADO.**

| Caso de teste | Esperado | Obtido |
|---|---|---|
| Token `csrf_token` presente no HTML do formulário de login | presente | ✅ presente |
| `POST` de login **sem** token | bloquear (403) | ✅ **403** |
| `POST` de login **com** token válido (credencial inválida) | passar do CSRF (≠ 403) | ✅ **200** (segue para validação de credencial) |

### 3.4 Proteção CSRF — Painel Admin (verificação central em `Auth::check()`)
**Resultado: APROVADO.**

| Caso de teste | Esperado | Obtido |
|---|---|---|
| Login admin com `dev123` → acesso ao dashboard | logado (200) | ✅ **200** |
| `POST` em `courses.php` **sem** token | bloquear (403) | ✅ **403** |
| `POST` em `courses.php` **com** token (sem `action`) | passar (200), sem mutação | ✅ **200**, sem mutação |

> Comprova que a verificação central protege todos os handlers POST autenticados **e** que tokens válidos passam (o painel não foi quebrado).

### 3.5 Validação server-side — Formulário do aluno
**Resultado: APROVADO.**

| Caso de teste | Esperado | Obtido |
|---|---|---|
| `POST` com nome curto + e-mail inválido + campos faltando | redirecionar (303) ao formulário | ✅ **303** → `index.php?erro=validacao` |
| Banner de erro exibido na `index.php` (mesma sessão) | listar os campos inválidos | ✅ exibe todos (nome, e-mail, matrícula, curso, tipo, responsável) |
| Banner é "one-shot" (some no recarregamento) | sumir no 2º GET | ✅ 0 ocorrências no reload |

### 3.6 Stepper / página de consulta (regressão pós-responsividade)
**Resultado: APROVADO.** Consulta com protocolo real (`2026-1-0013`) renderizou "Fluxo do Requerimento", a "Linha do Tempo" e o wrapper de rolagem — **sem erros PHP** (nenhum *Fatal/Parse/Warning/Notice*).

### 3.7 Submissão completa de requerimento + bloqueio de path traversal (E2E)
**Resultado: APROVADO** (com 1 achado de robustez corrigido — ver item #10).

Fluxo real exercitado: upload de PDF via `upload_temp.php` → submissão em `submit_request.php` com **dois** anexos — um legítimo (`[32hex].pdf`) e um **payload malicioso** (`../../config/config.php`) — gravação real no banco e no disco, seguida de remoção dos dados de teste.

| Caso de teste | Esperado | Obtido |
|---|---|---|
| Upload de PDF válido (`upload_temp.php`, com checagem de MIME) | aceito | ✅ aceito (`application/pdf`) |
| Submissão válida cria o requerimento | request gravado + redirect `success.php?protocol=` | ✅ request criado, redirect 303 com protocolo |
| Anexo **legítimo** movido para `uploads/` e registrado | 1 arquivo `PROTO-01.pdf` em `request_files` | ✅ salvo e registrado (1 linha) |
| Payload **`../../config/config.php`** | bloqueado (não movido/registrado) | ✅ `config/config.php` intacto (md5 inalterado); **nenhum `.php`** em `uploads/` |
| Total de anexos gravados | 1 (só o legítimo) | ✅ **1** |

> **Achado:** na 1ª execução, o anexo legítimo **não** foi salvo porque `public/uploads/` não existia e o handler não a criava (perda silenciosa). Corrigido (item #10): handlers passam a criar a pasta (`mkdir 0755`) e foi adicionado `public/uploads/.gitkeep`. Reexecução: **tudo aprovado**.
>
> **Limpeza:** request de teste, linhas de `request_files`, arquivo em `uploads/`, PDF temporário e logs de e-mail de dev gerados foram **removidos** após o teste (banco e disco retornados ao estado anterior).

---

## 4. Não testado dinamicamente (cobertura futura)
- **XSS de `guardian_registrations.php`** — validado por **inspeção de código + lint** (codificação `htmlspecialchars(addslashes(...), ENT_QUOTES)` no contexto atributo-HTML › string-JS); não explorado dinamicamente.
- **Varredura oficial da DTIC** (Etapa 6) — pendente, exigência da Resolução CGD 03/2025.

---

## 5. Histórico de commits (referência)

```
a0cd9bb  Correcao: path traversal (anexos), vazamento de erro, fixacao de sessao
27a0887  CSRF no portal de responsaveis (+ path traversal no handler do portal)
bacd1f0  CSRF no painel admin e portal docente (central em Auth::check)
613b168  Validacao server-side do form do aluno + endurecimentos menores (MIME, perms, charset)
b4594c6  Correcao de XSS armazenado em guardian_registrations.php
<novo>   Cria public/uploads/ nos handlers (+ .gitkeep); documenta teste E2E (secao 3.7)
```

*(Repositório local; após a migração para o git.ifsc.edu.br, atualizar com os links permanentes dos commits.)*
