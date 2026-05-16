# Plano de Releases
## Sistema de Requerimentos Acadêmicos — IFSC Câmpus Garopaba

**Versão:** 1.0 | **Data:** 2026-05-16

---

## Release 1.0 — MVP (Concluída)

**Status:** ✅ Desenvolvida e testada em ambiente local  
**Base:** Sistema original do Câmpus Canoinhas (Prof. Eduardo Gomes) + customizações Garopaba (Prof. Thiago Paes)

### Funcionalidades entregues

- Formulário público multi-step para protocolo de requerimentos
- 23 tipos de requerimento configurados conforme RDP IFSC (Resolução CONSUP 20/2018)
- Descrições informativas para todos os tipos, com citação do artigo do RDP
- Scroll automático para descrição ao selecionar tipo de requerimento
- Geração de número de protocolo único (`GPB-AAAA-NNNNN`)
- Upload de documentos anexos
- Painel administrativo com listagem, filtros e detalhamento de requerimentos
- Workflow de tramitação configurável por tipo
- Histórico de tramitação auditável
- Notificações por e-mail (aluno + coordenadores) com template institucional IFSC
- Autenticação administrativa via LDAP/AD
- Conformidade CGD 03/2025 — Fase 1: credenciais em `.env`, Apache seguro, aviso LGPD
- Criptografia AES-256-CBC para campos de telefone (LGPD)
- Documentação SIPAC completa (Visão, Regras de Negócio, Backlog, Releases)

---

## Release 1.1 — Implantação em Produção (Garopaba)

**Status:** ⏳ Aguardando aprovação institucional (Fase 2)  
**Estimativa:** após aprovação CGD + disponibilização de servidor CTIC

### Pré-requisitos

- [ ] Aprovação do processo SIPAC (PROEN → DTIC → CGD)
- [ ] Servidor web disponibilizado pela CTIC Câmpus Garopaba (PHP 8.4 + MySQL 8)
- [ ] Dados LDAP do Active Directory do câmpus fornecidos pela CTIC
- [ ] Migração do repositório para git.ifsc.edu.br

### Atividades de implantação

| # | Atividade |
|---|-----------|
| 1 | Configurar LDAP Garopaba em `src/LdapService.php` |
| 2 | Preencher variáveis de produção em `config/config.php` (URL, Turnstile, flags) |
| 3 | Configurar SMTP com conta institucional do câmpus |
| 4 | Remover bypass de desenvolvimento (`src/Auth.php`) |
| 5 | Implantar certificado SSL/HTTPS |
| 6 | Popular banco com dados reais (cursos, usuários, workflow) |
| 7 | Treinamento dos servidores (secretaria e coordenadores) |
| 8 | Testes de aceitação com usuários reais |
| 9 | Go-live supervisionado |

---

## Release 1.2 — Melhorias Pós-Implantação (Planejada)

**Status:** 📋 Backlog futuro  
**Estimativa:** 3–6 meses após go-live

### Funcionalidades planejadas

| Funcionalidade | Justificativa |
|----------------|---------------|
| Varredura de segurança DTIC | Requisito da Resolução CGD 03/2025 |
| Script cron de limpeza de logs ativo | LGPD — logs de e-mail expiram em 90 dias |
| Política formal de retenção de dados documentada | LGPD — prazo de guarda dos requerimentos |
| Relatórios gerenciais (quantidade por tipo, tempo médio de análise) | Gestão e monitoramento |
| Integração futura com SIGAA (se API disponível) | Eliminação de retrabalho manual |
