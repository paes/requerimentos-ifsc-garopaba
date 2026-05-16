# Documento de Visão
## Sistema de Requerimentos Acadêmicos — IFSC Câmpus Garopaba

**Versão:** 1.0  
**Data:** 2026-05-16  
**Responsável:** Prof. Thiago Paes — thiago.paes@ifsc.edu.br  
**Câmpus:** Garopaba

---

## 1. Introdução

### 1.1 Propósito

Este documento descreve a visão do Sistema de Requerimentos Acadêmicos do IFSC Câmpus Garopaba, estabelecendo o problema que o sistema resolve, os stakeholders envolvidos, as necessidades dos usuários e as principais funcionalidades do produto.

### 1.2 Escopo

O sistema permite que alunos matriculados no Câmpus Garopaba protocolem requerimentos acadêmicos eletronicamente, acompanhem o andamento de suas solicitações e recebam notificações automáticas. Servidores da área pedagógica e administrativa analisam, tramitam e concluem os requerimentos dentro de um workflow configurável.

---

## 2. Definição do Problema

| Campo | Descrição |
|-------|-----------|
| **Problema** | Alunos precisam comparecer fisicamente à secretaria para protocolar requerimentos, gerando filas, perda de documentos em papel, ausência de rastreamento e dificuldade de comunicação sobre o andamento |
| **Afeta** | Alunos matriculados; coordenadores de curso; equipe pedagógica; secretaria acadêmica |
| **Impacto** | Demora no atendimento, falta de transparência no processo, retrabalho administrativo, risco de perda de prazos |
| **Solução** | Plataforma web de protocolo eletrônico com workflow de aprovação, notificações automáticas por e-mail e histórico de tramitação auditável |

---

## 3. Stakeholders e Usuários

### 3.1 Stakeholders

| Stakeholder | Interesse |
|-------------|-----------|
| Direção de Ensino — Câmpus Garopaba | Eficiência administrativa e conformidade institucional |
| Coordenadoria Pedagógica | Centralização e rastreabilidade dos requerimentos |
| PROEN — Pró-reitoria de Ensino | Conformidade com RDP e políticas institucionais |
| DTIC | Conformidade com Resolução CGD 03/2025 |
| Comissão de Implantação | Viabilização e sustentação do sistema no câmpus |

### 3.2 Usuários

| Perfil | Descrição | Papel no sistema |
|--------|-----------|-----------------|
| Aluno | Estudante matriculado em qualquer curso do câmpus | Protocola requerimentos, acompanha status |
| Coordenador de Curso | Docente responsável pela coordenadoria | Analisa e defere/indefere requerimentos da sua área |
| Secretaria Acadêmica | TAE responsável pelo registro acadêmico | Encaminha, registra e conclui tramitação |
| Coordenador Pedagógico | Responsável pela Coordenadoria Pedagógica | Analisa requerimentos pedagógicos específicos |
| Administrador | Responsável técnico pelo sistema | Gerencia usuários, tipos de requerimento e workflow |

---

## 4. Visão Geral do Produto

### 4.1 Perspectiva do Produto

Sistema web standalone, hospedado em servidor local da CTIC Câmpus Garopaba. Integrado ao Active Directory (LDAP) institucional para autenticação dos servidores. Não substitui sistemas SIG (SIGAA), mas complementa com fluxo de requerimentos local.

### 4.2 Funcionalidades Principais

| # | Funcionalidade | Benefício |
|---|---------------|-----------|
| F01 | Protocolo eletrônico de requerimentos | Elimina papel e presença física |
| F02 | 23 tipos de requerimento configurados (RDP IFSC) | Cobre todos os processos acadêmicos do câmpus |
| F03 | Workflow de tramitação multi-etapas | Rastreabilidade e conformidade com RDP |
| F04 | Notificações automáticas por e-mail | Transparência para aluno e coordenadores |
| F05 | Número de protocolo único | Identificação e acompanhamento |
| F06 | Upload de documentos anexos | Digitalização do processo |
| F07 | Painel administrativo com filtros | Gestão eficiente do volume de requerimentos |
| F08 | Histórico de tramitação auditável | Conformidade e rastreabilidade |
| F09 | Autenticação via LDAP/AD institucional | Segurança e integração com identidade digital IFSC |
| F10 | Proteção de dados pessoais (LGPD) | Conformidade legal — criptografia AES-256 para dados sensíveis |

### 4.3 Suposições e Dependências

- Servidor web local disponibilizado pela CTIC Câmpus Garopaba
- Acesso ao Active Directory (LDAP) do câmpus para autenticação
- PHP 8.4 e MySQL 8 disponíveis no servidor
- Servidor SMTP institucional ou conta de e-mail do câmpus para envio de notificações

### 4.4 Restrições

- O sistema não integra diretamente com o SIGAA (sem API disponível)
- Acesso público ao formulário de protocolo não requer autenticação (aluno usa dados cadastrais)
- A área administrativa requer autenticação via LDAP institucional

---

## 5. Requisitos Não Funcionais

| Código | Requisito | Detalhamento |
|--------|-----------|-------------|
| RNF01 | Linguagem | PHP 8.4 |
| RNF02 | Banco de dados | MySQL 8 |
| RNF03 | Autenticação administrativa | Active Directory (LDAP) |
| RNF04 | Segurança — transporte | HTTPS obrigatório em produção; TLS no SMTP |
| RNF05 | Segurança — credenciais | Fora do código-fonte, em variáveis de ambiente (.env) |
| RNF06 | Segurança — dados sensíveis | Telefones criptografados com AES-256-CBC em repouso |
| RNF07 | Segurança — injeção | Queries via PDO com prepared statements |
| RNF08 | Controle de versão | git.ifsc.edu.br (após aprovação DTIC) |
| RNF09 | Hospedagem | Infraestrutura local CTIC Câmpus Garopaba |
| RNF10 | Conformidade LGPD | Base legal: Art. 7º, II — obrigação legal; aviso de privacidade no formulário |
| RNF11 | Conformidade CGD | Resolução CGD nº 03/2025 — processo SIPAC/PROEN/DTIC/CGD |
