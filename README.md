# Sistema de Requerimentos Acadêmicos — IFSC Câmpus Garopaba

Sistema web para submissão e gestão de requerimentos acadêmicos, portal docente de substituições e módulo de equidade de horários. Desenvolvido originalmente pelo Prof. Eduardo Gomes (Câmpus Canoinhas) e adaptado para o Câmpus Garopaba pelo Prof. Thiago Paes.

**Contato:** thiago.paes@ifsc.edu.br

---

## Stack

| Componente | Tecnologia |
|---|---|
| Backend | PHP 8.4 (sem Composer) |
| Banco de dados | MySQL 8 / MariaDB (PDO) |
| Frontend | Tailwind CSS + JavaScript Vanilla |
| Autenticação | LDAP (Active Directory IFSC) + bypass local (dev) |
| Anti-bot | Cloudflare Turnstile |
| E-mail | PHPMailer + fila assíncrona em banco |
| Servidor | Apache + php-fpm |
| Criptografia | AES-256-CBC (telefones de alunos — LGPD) |

---

## Módulos do sistema

O sistema é composto por três portais distintos e um conjunto de módulos de gestão:

### 1. Portal do Aluno (`public/`)

Acesso público, sem autenticação.

| Página | Função |
|---|---|
| `index.php` | Formulário de requerimento (curso, tipo, dados pessoais, anexos, declaração LGPD) |
| `submit_request.php` | Processamento do formulário: validação, protocolo, upload, e-mail |
| `check_status.php` | Consulta de protocolo: histórico de etapas e situação atual |
| `success.php` | Confirmação pós-envio com número de protocolo |
| `upload_temp.php` | Endpoint AJAX para upload de anexos durante o preenchimento |
| `substitute_respond.php` | Página pública para docente aceitar/recusar convite de substituição (via token) |

**Tipos de requerimento suportados (21):**

| # | Tipo |
|---|---|
| 1 | Justificativa de Falta(s) |
| 2 | Avaliação de Segunda Chamada |
| 3 | Trabalhos Domiciliares (NARC) |
| 4 | Trancamento de Curso |
| 5 | Cancelamento de Curso |
| 6 | Transferência para outra instituição |
| 7 | Matrícula em Componente Curricular Isolado |
| 8 | Expedição de Diploma / Certificados |
| 9 | Validação de Unidade Curricular |
| 10 | Retorno de Trancamento |
| 11 | Reingresso de Matrícula Cancelada |
| 12 | Matrícula Especial em Componente Curricular |
| 13 | Apoio Educacional Especializado (NAE) |
| 14 | Ajuste de Matrícula |
| 15 | Planos de Estudo |
| 16 | Extraordinário Aproveitamento de Estudos |
| 17 | Quebra de Pré-requisitos |
| 18 | Colação em Gabinete |
| 19 | Outro (genérico — inativo) |
| 20 | Solicitação de Horário Diferenciado |
| 21 | Assistência Estudantil (IVS, auxílios PNAES) |

Cada tipo tem **texto informativo** e **aviso de atenção** configuráveis (suportam HTML rico — tabelas, links, listas). O tipo 21 inclui tabela completa de auxílios PNAES com valores e critérios de elegibilidade.

---

### 2. Painel Administrativo (`public/admin/`)

Autenticação via LDAP. Controle de acesso por **role** e cursos associados (coordenadores).

#### 2.1 Requerimentos

| Página | Função | Acesso |
|---|---|---|
| `dashboard.php` | Indicadores (total, pendentes, aprovados, rejeitados) + fila "Minhas Tarefas" | Todos |
| `request_details.php` | Análise completa: dados do aluno, anexos, histórico, parecer, tramitação | Todos (filtrado por role) |
| `all_requests.php` | Listagem com filtros avançados (nome, matrícula, protocolo, tipo, status, semestre, datas) | SysAdmin |
| `course_requests.php` | Requerimentos filtrados por curso do coordenador | Coordenadores, SysAdmin |
| `my_history.php` | Histórico de atuações do usuário logado | Todos |

#### 2.2 Relatórios

| Página | Função |
|---|---|
| `absence_report.php` | Unifica Justificativas de Falta (tipo 1) + Segunda Chamada com "justificar falta também" (tipo 2). Exibe datas com dia da semana e quadro resumo de faltas por dia |
| `segunda_chamada_report.php` | Requerimentos de Segunda Chamada deferidos, com UC e professor obtidos via extra_fields (JSON) |
| `schedule_report.php` | Requerimentos de Horário Diferenciado (tipo 20) com horários de chegada/saída |
| `student_report.php` | Histórico completo de requerimentos por aluno (busca por nome ou matrícula) |
| `class_report.php` | Relatório por turma — todos os requerimentos agrupados por aluno (versão printável para Conselho de Classe) |

#### 2.3 Substituições de Docentes

| Página | Função |
|---|---|
| `teacher_requests.php` | Lista e analisa solicitações de substituição. Coordenador/DEPE aprovam ou rejeitam |
| `teacher_status.php` | Dashboard de substituições filtrado por semestre, status e docente |

#### 2.4 Cronograma e Equidade de Horários

| Página | Função |
|---|---|
| `schedule_slots.php` | Importa horário do aSc Timetables (XML). Exibe grade semanal por docente com aulas em azul (normal) ou laranja (slot pesado: Segunda/Sexta) |
| `schedule_justice.php` | **Ranking de equidade de horários** — ver seção 5 |
| `coordinators.php` | Gestão de coordenadores por semestre (coordenações de curso + outros cargos do câmpus) |

#### 2.5 Cadastros (CRUD)

| Página | Objeto |
|---|---|
| `courses.php` | Cursos (nome, nível, ativo/inativo) |
| `subjects.php` | Unidades Curriculares por curso e período |
| `request_types.php` | Tipos de requerimento (nome, texto informativo, aviso, destaque) |
| `workflows.php` | Fluxo de aprovação por tipo: sequência de roles e etapas |
| `users.php` | Usuários administrativos (nome, e-mail, role, cursos associados) |
| `roles.php` | Perfis de acesso (13 roles: SysAdmin, Coordenador, DEPE, Secretaria, etc.) |
| `teachers.php` | Docentes (nome, e-mail, ativo/inativo) |
| `email_config.php` | Configuração SMTP (host, porta, usuário, senha, criptografia) |

#### 2.6 Utilitários

| Página | Função |
|---|---|
| `email_log.php` | Visualiza e-mails salvos em arquivo (modo dev) |
| `process_queue_manual.php` | Força processamento da fila de e-mails (SysAdmin) |
| `profile.php` | Perfil do usuário logado |

---

### 3. Portal Docente (`public/docentes/`)

Autenticação LDAP separada para professores.

| Página | Função |
|---|---|
| `dashboard.php` | Lista todas as solicitações de substituição do docente com status |
| `novo.php` | Nova solicitação: seleciona curso, turma, UC, datas de ausência, horários e candidatos a substituto. Busca automática de docentes livres no slot via AJAX |
| `submit.php` | Processa a solicitação, gera protocolo e enfileira e-mails com token único para cada candidato |
| `detalhes.php` | Detalhes de uma solicitação: histórico de aprovações e respostas dos candidatos |
| `api_suggest.php` | Endpoint AJAX: retorna docentes livres em determinado day_of_week + time_slot |

---

### 4. Fluxo de Trabalho

#### 4.1 Requerimento de aluno

```
Aluno preenche formulário
    → Validação (Turnstile + dados)
    → Gera protocolo (AAAA-S-NNNN)
    → Salva requerimento (status: pending, step: 1)
    → Enfileira e-mail de confirmação ao aluno
    → E-mail de aviso ao primeiro revisor do workflow

Revisor 1 (ex: Coordenador) abre request_details
    → Analisa, adiciona parecer
    → Aprova → step avança para próximo revisor
    → Rejeita → status: rejected, e-mail ao aluno

... (repete por cada etapa do workflow) ...

Último revisor aprova
    → status: concluded
    → E-mail de conclusão ao aluno
```

#### 4.2 Substituição de docente

```
Docente solicita substituto em /docentes/novo.php
    → Candidatos sugeridos via /api_suggest.php (livres no slot)
    → Cada candidato recebe e-mail com link único (token)

Candidato acessa /substitute_respond.php?token=XXX
    → Aceita ou recusa
    → Coordenador é notificado por e-mail

Coordenador aprova em /admin/teacher_requests.php
    → DEPE recebe para análise final
    → concluded / rejected
```

---

### 5. Módulo de Equidade de Horários (`schedule_justice.php`)

Ferramenta para apoiar decisões de distribuição de horários entre os docentes de forma equânime.

#### Conceito

Slots de Segunda-feira e Sexta-feira têm **pesos negativos** (quanto mais extremo, maior o peso):

| Slot | Peso |
|---|---|
| Seg Manhã 12 | 3,0 |
| Seg Manhã 34 | 2,5 |
| Seg Tarde 12 | 2,0 |
| Seg Tarde 34 | 1,5 |
| Seg/Sex Noite | 1,0–3,0 |
| Sex Tarde 34 | 2,5 |
| Sex Noite 12/34 | 3,0 |

#### Cálculo do score por docente

```
badAllocScore = Σ (peso_slot × fator_terms × fator_ead) para aulas em Seg/Sex
baseScore     = Σ (0,05 × fator_terms × fator_ead) para aulas em Ter/Qua/Qui
coordScore    = pontos fixos pelo cargo de coordenação:
                  Direção / Chefe DEPE = 10,0
                  Assessoria DEPE / Coord. Pedagógica = 5,0
                  Coord. INF / ADM / LAZ = 5,0
                  Coord. demais cursos / outros cargos = 2,0

score      = badAllocScore + baseScore
scoreTotal = score + coordScore     ← usado para ordenar o ranking
```

- **fator_terms**: 1,0 se aula ocorre o semestre inteiro; 0,5 se só primeiro quarto; etc.
- **fator_ead**: 0,5 para disciplinas EAD; 1,0 para presencial

#### Abas do módulo

| Aba | Conteúdo |
|---|---|
| **Diagnóstico** | Ranking de docentes com colunas por grupo de slot (Seg M/T/N, Sex M/T/N), score base, score de coordenação e score total. Grade individual expansível por docente |
| **Histórico** | Comparativo de scores por semestre (até 4 semestres mais recentes). Score acumulado médio ponderado |
| **Recomendações** | Docentes ordenados por menor carga acumulada, indicando prioridade para horários bons na próxima montagem |

#### Coordenadores por semestre

`coordinators.php` permite cadastrar quem ocupa cada cargo por semestre:
- Drag-and-drop ou digitação com autocomplete de docentes
- Coordenações de curso (grades por curso)
- Outros cargos: Direção, Chefe do DEPE, Assessoria DEPE, Coord. Pedagógica, Secretaria, Extensão, Pesquisa, NEAD, NAE, Biblioteca, CTIC

---

### 6. Importação de Horário (aSc Timetables)

`schedule_slots.php` faz o parse do XML exportado pelo aSc Timetables (formato `asctt2012`) e armazena em `schedule_slots`:

- Docente, turma, disciplina, dia da semana, slot de tempo
- Campo `terms` (bitmask): duração real da aula no semestre (`1111` = completo, `1100` = 1ª metade, etc.)
- Normalização de nomes: docentes com grafias divergentes entre o XML e a tabela `teachers` são corrigidos via SQL em `dump/`

---

## Banco de Dados

### Tabelas principais

| Tabela | Descrição |
|---|---|
| `courses` | Cursos ativos (10 cursos em Garopaba) |
| `subjects` | Unidades curriculares por curso e período |
| `request_types` | 21 tipos de requerimento com HTML de informação e aviso |
| `requests` | Requerimentos dos alunos (protocolo, status, etapa, campos específicos) |
| `request_history` | Auditoria: cada ação de aprovação/rejeição/comentário |
| `request_files` | Anexos de requerimento |
| `users` | Usuários administrativos |
| `roles` | 13 perfis de acesso |
| `user_courses` | Associação coordenador ↔ cursos |
| `workflow_steps` | Fluxo de aprovação por tipo de requerimento |
| `teachers` | Cadastro de docentes |
| `email_config` | Configuração SMTP (1 registro) |
| `email_queue` | Fila de e-mails (pending → sending → sent / failed) |
| `schedule_slots` | Grade de horários parseada do XML aSc |
| `schedule_uploads` | Histórico de imports de cronograma |
| `course_coordinators` | Coordenadores por semestre (curso e outros cargos) |
| `teacher_requests` | Solicitações de substituição de docentes |
| `teacher_request_history` | Auditoria de substituições |
| `request_candidate_substitutes` | Candidatos a substituto com token de resposta |
| `teacher_inactive_semesters` | Docentes inativados por semestre (afastamentos, licenças) |

### Credenciais de desenvolvimento

```
Host: localhost
DB:   ifsc_requests
User: ifsc
Pass: ifsc1234
```

```bash
mysql -u ifsc -pifsc1234 ifsc_requests
```

---

## Configuração

### `.env` (nunca comitar)

```env
DB_HOST=localhost
DB_NAME=ifsc_requests
DB_USER=ifsc
DB_PASSWORD=ifsc1234
PHONE_ENCRYPTION_KEY=chave-aes-256-bits-aqui
```

### `config/config.php`

```php
define('BASE_URL', '/requerimentos');          // ajustar para produção
define('ENABLE_TURNSTILE', false);             // true em produção
define('ENABLE_EMAILS', false);               // true em produção
define('TURNSTILE_SITE_KEY', '...');
define('TURNSTILE_SECRET_KEY', '...');
```

### `src/LdapService.php`

Configurar host e base DN do Active Directory do câmpus Garopaba (pendente: ver `EMAIL_LDAP_DTIC.md`).

---

## Instalação

```bash
# 1. Clone o repositório
git clone <url> /var/www/html/requerimentos

# 2. Configure o .env
cp .env.example .env
nano .env   # preencher credenciais

# 3. Crie o banco e importe o schema
mysql -u ifsc -pifsc1234 -e "CREATE DATABASE ifsc_requests"
mysql -u ifsc -pifsc1234 ifsc_requests < dump/ifsc_requests.sql

# 4. Permissões de escrita
chmod 775 public/uploads public/temp storage/email_log storage/schedules

# 5. Configure o cron para processar fila de e-mails
# Adicionar no crontab (crontab -e):
* * * * * php /var/www/html/requerimentos/public/cron/process_queue.php
```

### Apache (`.htaccess` ou VirtualHost)

```apache
DirectoryIndex index.php
Options -Indexes
```

---

## Segurança

| Mecanismo | Descrição |
|---|---|
| **Autenticação** | LDAP (prod) + senha local (dev, marcado TODO-PRODUÇÃO) |
| **Criptografia** | AES-256-CBC para `student_phone` e `guardian_phone` |
| **Anti-bot** | Cloudflare Turnstile no formulário público |
| **SQL Injection** | PDO prepared statements em todas as queries |
| **Listagem de diretórios** | Desabilitada via Apache |
| **Dados pessoais** | Logs de e-mail com dados de alunos nunca vão a git (`.gitignore`) |
| **Limpeza LGPD** | `scripts/limpar_logs_email.php` remove logs com >90 dias |

### O que NUNCA comitar

```
.env                        # credenciais do banco
storage/email_log/*.html    # dados pessoais de alunos (LGPD)
public/uploads/*            # arquivos enviados por alunos
.claude/                    # memória local do assistente IA
```

---

## Perfis de Acesso

| Role | is_sysadmin | is_course_bound | Acesso |
|---|---|---|---|
| SysAdmin | ✓ | — | Tudo |
| Coordenador de Curso | — | ✓ | Requerimentos dos seus cursos |
| Registro Acadêmico | — | — | Tramitação de etapas específicas |
| DEPE | — | — | Equidade de horários, substituições |
| Assessoria DEPE | — | — | Equidade de horários, substituições |
| Direção do Câmpus | — | — | Visualização |
| Secretaria Acadêmica | — | — | Tramitação |
| Coord. Pedagógica | — | — | Tramitação |
| NAE | — | — | Requerimentos tipo Apoio Educacional |
| Biblioteca | — | — | Acesso específico |
| CTIC | — | — | Acesso específico |
| CAE | — | — | Assistência estudantil |
| Docente | — | — | Portal de substituições (`/docentes/`) |

---

## Cursos (Garopaba — 2026.1)

| Abrev. | Curso | Nível |
|---|---|---|
| ADM | Técnico em Administração | Concomitante |
| INF | Técnico em Informática | Concomitante |
| LAZ | Técnico em Lazer | Concomitante |
| PROEJA RB | PROEJA em Serviços de Restauração e Bar | PROEJA |
| PROEJA ADM | PROEJA em Administração | PROEJA |
| GUIA | Técnico em Guia de Turismo | Concomitante |
| BTC | Técnico em Biotecnologia | Concomitante |
| GA | CST em Gestão Ambiental | Graduação |
| SI | CST em Sistemas para Internet | Graduação |
| FIC ING | FIC em Inglês | Formação Continuada |

---

## E-mails (fila assíncrona)

O sistema nunca envia e-mail de forma síncrona. Todo envio é inserido em `email_queue` e processado pelo cron:

```
/public/cron/process_queue.php   ← executado a cada minuto pelo cron
```

Em **desenvolvimento** (`ENABLE_EMAILS=false`): e-mails são salvos como `.html` em `storage/email_log/` e podem ser visualizados em `admin/email_log.php`.

**Ocasiões de disparo:**
- Aluno envia requerimento → confirmação para aluno + aviso para 1º revisor
- Revisor aprova etapa → aviso para próximo revisor
- Requerimento concluído ou rejeitado → notificação para aluno
- Docente solicita substituição → e-mail com token único para cada candidato
- Candidato responde → notificação para coordenador/DEPE

---

## Roadmap (pendente para produção)

### Fase 2 — Implantação institucional
- [ ] Abrir processo SIPAC → PROEN → DTIC → CGD (ver `SIPAC_SUBMISSAO.md`)
- [ ] Migrar código para `git.ifsc.edu.br`
- [ ] Enviar `EMAIL_LDAP_DTIC.md` para a CTIC
- [ ] Configurar LDAP Garopaba em `src/LdapService.php`
- [ ] Remover bypass dev123 de `src/Auth.php` (marcado `TODO-PRODUÇÃO`)
- [ ] Configurar SMTP Garopaba no painel admin
- [ ] UAT com secretaria e coordenadores (ver `docs/sipac/guia_de_testes_equipes.md`)

### Fase 3 — Pós go-live
- [ ] Ativar cron de limpeza de logs (`scripts/limpar_logs_email.php`)
- [ ] Documentar política de retenção de dados (LGPD)
- [ ] Treinamento dos servidores
- [ ] Varredura de segurança pela DTIC (CGD 03/2025)
- [ ] Revisão bienal (junho/2027)

---

## Autores

- **Prof. Eduardo Gomes** — desenvolvimento original (Câmpus Canoinhas)
- **Prof. Thiago Paes** — adaptação para Câmpus Garopaba • thiago.paes@ifsc.edu.br

---

## Referência normativa

**RDP** — Resolução CONSUP nº 20/2018 (Regulamento Didático-Pedagógico do IFSC)
Os textos descritivos dos tipos de requerimento foram baseados nos artigos do RDP com citação explícita nos campos `information` da tabela `request_types`.
