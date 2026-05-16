# Rascunho — Processo SIPAC para Implantação do Sistema de Requerimentos

> **Quando usar:** Após a comissão de implantação estar formada.  
> Abrir processo no SIPAC destinado à **PROEN** (Pró-reitoria de Ensino).  
> Fluxo: PROEN → avalia → DTIC → avalia viabilidade técnica → CGD → aprova.  
> Base legal: Resolução CGD nº 03, de 25 de abril de 2025.

---

## Dados do Processo

**Tipo de processo:** Solicitação de Desenvolvimento/Implantação de Sistema  
**Destinatário inicial:** PROEN — Pró-reitoria de Ensino  
**Câmpus:** Garopaba  
**Data de abertura:** [preencher]

---

## Descrição do Sistema

**Nome:** Sistema de Requerimentos Acadêmicos — IFSC Câmpus Garopaba  
**Finalidade:** Plataforma web para protocolo eletrônico de requerimentos acadêmicos pelos alunos (trancamento de curso, avaliação de 2ª chamada, validação de unidade curricular, exercício domiciliar, entre outros), com workflow de análise e aprovação pela Coordenadoria de Curso e Coordenadoria Pedagógica.  
**Público-alvo:** Alunos matriculados no Câmpus Garopaba; servidores da área pedagógica/administrativa.  
**Origem:** Adaptação e expansão do sistema desenvolvido pelo Prof. Eduardo Gomes (Câmpus Canoinhas), com autorização e customizações específicas para o Câmpus Garopaba realizadas pelo Prof. Thiago Paes.

---

## Justificativa de Não Substituição por Sistema Existente

O SIGAA e demais sistemas SIG do IFSC não oferecem módulo equivalente para o fluxo local de requerimentos acadêmicos com as seguintes características:
- Protocolo eletrônico pelos alunos sem necessidade de presença física na secretaria
- Workflow customizável por tipo de requerimento e coordenadoria
- Notificações automáticas por e-mail a alunos e coordenadores
- Geração de número de protocolo para acompanhamento
- Histórico de tramitação por requerimento

---

## Requisitos Não Funcionais

- **Linguagem:** PHP 8.4  
  *Justificativa:* O sistema já está desenvolvido e funcional; migração para Java implicaria reconstrução completa com custo humano desproporcional para uma solução campus.
- **Banco de dados:** MySQL 8  
  *Justificativa:* idem
- **Autenticação:** Active Directory (LDAP) do IFSC para servidores; e-mail/dados institucionais para alunos
- **Segurança:** HTTPS obrigatório em produção; TLS no SMTP; sem credenciais hardcoded no código
- **Hospedagem:** Servidor local da CTIC Câmpus Garopaba (infraestrutura institucional)
- **Controle de versão:** [preencher após migração] git.ifsc.edu.br/[repositório]

---

## Identificação do Dono do Produto

- **Área responsável pelo processo:** Coordenadoria Pedagógica / Direção de Ensino — Câmpus Garopaba
- **Nome do responsável:** [preencher — Diretor(a) de Ensino]
- **E-mail:** [preencher]

---

## Identificação do Responsável Técnico pelo Projeto

- **Nome:** Prof. Thiago Paes
- **E-mail:** thiago.paes@ifsc.edu.br
- **Câmpus:** Garopaba

---

## Comissão de Implantação

[Listar membros e portaria/ato de constituição após formação]

---

## Documentos a Anexar ao Processo SIPAC

- [ ] Este documento (requisitos funcionais e não funcionais)
- [ ] Ata ou portaria de constituição da comissão de implantação
- [ ] Link do repositório no git.ifsc.edu.br (após migração)
- [ ] Print/demonstração do sistema em funcionamento (opcional, facilita avaliação)
