# Conteúdo do Processo SIPAC — Submissão à PROEN

> **Como usar:** este é o texto-base para abrir o processo no SIPAC, destinado à **PROEN**
> (Pró-reitoria de Ensino), conforme o **Anexo I da Resolução CGD nº 03/2025**
> ("Acordo Prévio com as áreas envolvidas"). Preencher os campos entre colchetes.
>
> **Observação (orientação da DTIC, 24/06/2026):** o passo atual é **submeter às áreas
> responsáveis** — *não* é necessário que o código já esteja no `git.ifsc.edu.br` para
> esta etapa (a migração ocorre após o aceite/encaminhamento à DTIC).

---

## Dados do processo

| Campo | Valor |
|---|---|
| **Tipo de processo** | Solicitação de Desenvolvimento/Implantação de Sistema |
| **Destinatário** | PROEN — Pró-reitoria de Ensino |
| **Fluxo previsto (Anexo I)** | PROEN → DTIC (viabilidade técnica) → CGD (aprovação) |
| **Câmpus** | Garopaba |
| **Base legal** | Resolução CGD nº 03, de 25/04/2025 |
| **Data de abertura** | [preencher] |

---

## 1. Identificação

**Dono do Produto:** [Diretor(a) de Ensino ou Coord. Pedagógica — definir] — área-cliente do sistema.
**Responsável Técnico:** Prof. Thiago Lipinski Paes — thiago.paes@ifsc.edu.br — Docente de Computação, IFSC Câmpus Garopaba.

**Comissão de Desenvolvimento, Planejamento e Implantação** (instituída pela **Portaria da Direção-Geral do Câmpus Garopaba nº 130, de 15/06/2026**, em anexo):

| Membro | Cargo / Área | Papel na comissão |
|---|---|---|
| Prof. Thiago Paes | Docente Informática | Coordenador / Responsável técnico |
| Prof. Nauber Gavski | Coord. Pedagógico do câmpus | Validação de fluxos e testes |
| Profa. Sabrina Pacheco | Coord. Téc. Integrado em Informática | Testes de aceitação / apoio ao processo |
| Luciane Stein | Registro Acadêmico / Secretaria | Validação de fluxos operacionais |
| Thiago Waltrik | CTIC Câmpus Garopaba | Infraestrutura e LDAP |
| Antonio Luiz Schalata | CTIC (em exercício na Reitoria) | Apoio técnico CTIC / DTIC |

---

## 2. Apresentação e solicitação

A Comissão de Implantação do IFSC Câmpus Garopaba submete à PROEN, para avaliação e encaminhamento à DTIC (conforme o Anexo I da Resolução CGD 03/2025), o **Sistema Web de Gestão de Requerimentos e Gestão de Ensino**, desenvolvido no âmbito do câmpus. A condução segue a referida política, com os **artefatos da fase inicial já elaborados** (Documento de Visão, Regras de Negócio, Plano de Releases e Backlog do Produto).

---

## 3. Descrição do sistema

Plataforma web que **centraliza processos acadêmicos do câmpus** hoje dispersos em e-mails, planilhas e atendimentos presenciais.

**Módulos implementados:**
- **Requerimentos discentes:** protocolo eletrônico pelos alunos (trancamento, 2ª chamada, validação de UC, exercício domiciliar, entre 20+ tipos), com workflow de análise/tramitação pelas coordenações e secretaria, notificações por e-mail e acompanhamento por número de protocolo.
- **Portal de Responsáveis:** pais/responsáveis de alunos menores protocolam e acompanham requerimentos, com cadastro validado pela Coordenação Pedagógica e confirmação por código (OTP).
- **Substituição docente:** o docente solicita substituto; o sistema sugere quem está livre no horário, envia convite por e-mail e encaminha para aprovação da coordenação e do DEPE.
- **Equidade de horários ("Justiceiro do Tempo")** e **importação da grade** (XML do aSc Timetables).

**Módulos planejados (próximas fases):** Coordenação Pedagógica (alunos, notas e atendimentos), orientações e carteirinhas digitais, e autorizações de saídas externas.

> Demonstração visual dos fluxos em **[prints/](prints/)** (ver índice em [prints/README.md](prints/README.md)).

---

## 4. Justificativa de não substituição por sistema existente

O SIGAA e demais sistemas SIG do IFSC não oferecem módulo equivalente para o fluxo **local** de requerimentos acadêmicos com:
- Protocolo eletrônico pelos alunos (e responsáveis) sem presença física na secretaria;
- Workflow customizável por tipo de requerimento e por coordenadoria;
- Notificações automáticas a alunos, responsáveis e coordenadores;
- Geração de protocolo e histórico de tramitação;
- Módulos específicos do câmpus (substituição docente, equidade de horários).

---

## 5. Requisitos não funcionais

- **Linguagem:** PHP 8.4
- **Banco de dados:** MySQL 8
- **Autenticação:** LDAP/AD institucional (servidores); dados institucionais (alunos)
- **Segurança:** HTTPS obrigatório em produção; TLS no SMTP; sem credenciais no código (`.env`); criptografia de dados sensíveis (LGPD)
- **Hospedagem:** servidor da CTIC do Câmpus Garopaba
- **Controle de versão:** git.ifsc.edu.br (migração na etapa subsequente)

> **Sobre a preferência da política por Java/PostgreSQL (Anexo I):** o sistema **já está desenvolvido, funcional e validado** em PHP 8.4 + MySQL. A reconstrução em Java implicaria refazer integralmente a solução, com custo humano desproporcional para uma iniciativa de câmpus. A comissão se coloca à disposição da DTIC para o **alinhamento técnico** quanto a hospedagem, integração e padrões de segurança.

---

## 6. Requisitos funcionais e artefatos (Política CGD 03/2025 — fase inicial)

Artefatos já produzidos, anexos ao processo:
- **Documento de Visão** — [documento_de_visao.md](documento_de_visao.md)
- **Regras de Negócio** — [regras_de_negocio.md](regras_de_negocio.md)
- **Plano de Releases** — [plano_de_releases.md](plano_de_releases.md)
- **Backlog do Produto** — [backlog.md](backlog.md)
- **Guia de Testes (UAT) das equipes** — [guia_de_testes_equipes.md](guia_de_testes_equipes.md)

---

## 7. Segurança e LGPD

O sistema trata dados pessoais de alunos e responsáveis e foi desenvolvido com proteção desde as fases iniciais (controle de acesso, criptografia de dados sensíveis, registros de tramitação). Foi realizada **auditoria de segurança interna** (OWASP Top 10 + específicas de PHP), com correções e testes documentados em **[../seguranca/relatorio_testes_seguranca.md](../seguranca/relatorio_testes_seguranca.md)** — material que apoia a futura **varredura de segurança da DTIC** (etapa exigida pela política antes do go-live).

---

## 8. Documentos a anexar ao processo

- [ ] Este documento (descrição, requisitos e justificativas)
- [ ] **Portaria da Direção-Geral nº 130/2026** (constituição da comissão)
- [ ] Artefatos da fase inicial (Visão, Regras de Negócio, Plano de Releases, Backlog)
- [ ] Relatório de testes de segurança
- [ ] **Demonstração visual (prints) dos fluxos** — pasta [prints/](prints/)
- [ ] Resolução CGD nº 03/2025 (referência)

---

## 9. Solicitação à PROEN

Solicita-se à PROEN a **avaliação da pertinência pedagógica** da demanda e, sendo favorável, o **encaminhamento à DTIC** para análise de viabilidade técnica e demais providências previstas no Anexo I da Resolução CGD 03/2025, com vistas à posterior aprovação pelo CGD e implantação no servidor da CTIC do Câmpus Garopaba.

A comissão permanece à disposição para esclarecimentos, demonstração do sistema e alinhamentos técnicos.
