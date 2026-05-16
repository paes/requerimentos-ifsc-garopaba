# Backlog do Produto
## Sistema de Requerimentos Acadêmicos — IFSC Câmpus Garopaba

**Versão:** 1.0 | **Data:** 2026-05-16

---

## Épicos

| Código | Épico |
|--------|-------|
| EP01 | Protocolo de Requerimento (aluno) |
| EP02 | Análise e Tramitação (coordenador/secretaria) |
| EP03 | Notificações por E-mail |
| EP04 | Conformidade e Segurança (LGPD, CGD) |
| EP05 | Infraestrutura e Implantação |

---

## EP01 — Protocolo de Requerimento

| ID | User Story | Critérios de Aceitação | Status |
|----|-----------|----------------------|--------|
| US01 | Como **aluno**, quero selecionar o tipo de requerimento e ver uma explicação sobre ele, para entender o que estou solicitando | Ao clicar no tipo, a descrição aparece e a página rola automaticamente até ela | ✅ Feito |
| US02 | Como **aluno**, quero preencher meus dados pessoais uma única vez por formulário | Campos de nome, e-mail, matrícula e curso são únicos no formulário | ✅ Feito |
| US03 | Como **aluno menor de idade**, quero informar os dados do meu responsável legal | Campos de responsável aparecem condicionalmente quando aluno marca que é menor | ✅ Feito |
| US04 | Como **aluno**, quero anexar documentos comprobatórios ao meu requerimento | Upload de arquivos funcional; arquivos visíveis no painel admin | ✅ Feito |
| US05 | Como **aluno**, quero receber um número de protocolo para acompanhar meu requerimento | Número gerado automaticamente e enviado por e-mail de confirmação | ✅ Feito |
| US06 | Como **aluno**, quero consultar o status do meu requerimento pelo número de protocolo | Página de consulta pública funcional com status e histórico | ✅ Feito |

## EP02 — Análise e Tramitação

| ID | User Story | Critérios de Aceitação | Status |
|----|-----------|----------------------|--------|
| US10 | Como **coordenador**, quero ver todos os requerimentos da minha área em uma lista filtrada | Dashboard com filtros por status, tipo e curso; apenas requerimentos do(s) curso(s) do coordenador | ✅ Feito |
| US11 | Como **coordenador**, quero visualizar todos os dados e anexos de um requerimento antes de analisar | Tela de detalhes exibe dados do aluno, tipo, descrição, anexos e histórico | ✅ Feito |
| US12 | Como **coordenador**, quero deferir ou indeferir um requerimento com um parecer | Botões de ação com campo de observação obrigatória; status atualizado no banco | ✅ Feito |
| US13 | Como **secretaria**, quero encaminhar requerimentos entre etapas do workflow | Tramitação seguindo `workflow_steps` configurado por tipo | ✅ Feito |
| US14 | Como **administrador**, quero gerenciar os tipos de requerimento e seus textos informativos | CRUD de `request_types` no painel admin | ✅ Feito |
| US15 | Como **administrador**, quero gerenciar usuários e seus perfis de acesso | CRUD de usuários com associação a cursos e roles | ✅ Feito |

## EP03 — Notificações por E-mail

| ID | User Story | Critérios de Aceitação | Status |
|----|-----------|----------------------|--------|
| US20 | Como **aluno**, quero receber e-mail de confirmação ao protocolar o requerimento | E-mail enviado com protocolo, tipo, curso e status inicial; template IFSC | ✅ Feito |
| US21 | Como **coordenador**, quero ser notificado por e-mail quando um requerimento chegar para minha análise | E-mail enviado ao(s) responsável(is) da etapa com dados do requerimento e link direto | ✅ Feito |
| US22 | Como **aluno**, quero ser notificado sobre mudanças no status do meu requerimento | E-mail enviado a cada tramitação com novo status e parecer | ✅ Feito |

## EP04 — Conformidade e Segurança

| ID | User Story | Critérios de Aceitação | Status |
|----|-----------|----------------------|--------|
| US30 | Como **instituição**, quero que credenciais nunca estejam no código-fonte | Credenciais em `.env`; `.env` no `.gitignore` | ✅ Feito |
| US31 | Como **aluno**, quero ser informado sobre o uso dos meus dados pessoais (LGPD) | Aviso de privacidade no rodapé e na declaração do formulário | ✅ Feito |
| US32 | Como **instituição**, quero que dados sensíveis sejam protegidos em repouso | Telefones criptografados AES-256-CBC no banco | ✅ Feito |
| US33 | Como **administrador**, quero que o acesso ao painel exija autenticação institucional | Login via LDAP/AD; sem acesso sem autenticação | ✅ Feito (LDAP pendente config. Garopaba) |
| US34 | Como **instituição**, quero que logs com dados pessoais expirem automaticamente | Script cron para apagar logs de e-mail com mais de 90 dias | ✅ Script criado (cron pendente produção) |
| US35 | Como **DTIC**, quero que o sistema esteja registrado no git.ifsc.edu.br | Repositório migrado para git.ifsc.edu.br | ⏳ Fase 2 |

## EP05 — Infraestrutura e Implantação

| ID | User Story | Critérios de Aceitação | Status |
|----|-----------|----------------------|--------|
| US40 | Como **CTIC**, quero hospedar o sistema em servidor local do câmpus | Sistema implantado em servidor CTIC Garopaba com PHP 8.4 + MySQL 8 | ⏳ Fase 2 |
| US41 | Como **administrador**, quero que o sistema use HTTPS em produção | Certificado SSL configurado; HTTP redireciona para HTTPS | ⏳ Fase 2 |
| US42 | Como **servidor**, quero receber treinamento para usar o painel administrativo | Capacitação realizada com coordenadores e secretaria | ⏳ Fase 2/3 |

---

## Cenários de Teste (atendimento à Resolução CGD 03/2025 — Qualidade do Software)

> A Resolução exige que o software "seja sujeito a testes unitários, de integração, de usabilidade e de aceitação." Os cenários abaixo documentam os testes realizados manualmente.

### Testes de Usabilidade e Aceitação

| ID | Cenário | Resultado esperado | Validado |
|----|---------|-------------------|----------|
| TC01 | Aluno seleciona tipo de requerimento | Descrição exibe e página rola automaticamente | ✅ |
| TC02 | Aluno submete requerimento com todos os campos obrigatórios | Protocolo gerado, e-mail enviado, redirecionamento para confirmação | ✅ |
| TC03 | Aluno submete sem preencher campo obrigatório | Formulário bloqueia envio e exibe validação | ✅ |
| TC04 | Aluno menor de 18 anos: campos de responsável são exigidos | Campos aparecem e são validados | ✅ |
| TC05 | Aluno consulta status pelo protocolo | Status, tipo e histórico exibidos corretamente | ✅ |
| TC06 | Coordenador faz login via LDAP | Acesso liberado apenas para e-mail cadastrado no sistema | ✅ (dev bypass) |
| TC07 | Coordenador defere requerimento com parecer | Status atualizado, aluno notificado por e-mail | ✅ |
| TC08 | Coordenador tenta acessar requerimento de outro curso | Acesso negado ou requerimento não aparece na lista | ✅ |
| TC09 | Telefone do aluno é criptografado no banco | Campo `student_phone` no DB exibe formato `iv:cipher`, não texto plano | ✅ |
| TC10 | Telefone decriptado corretamente no painel admin | Número de telefone legível na tela de detalhes | ✅ |
| TC11 | Campo telefone vazio não gera erro | Nulo é aceito sem exceção | ✅ |
| TC12 | Tentativa de acesso à admin sem login | Redirecionamento para tela de login | ✅ |
| TC13 | Apache não lista conteúdo de diretório | Acesso a `/requerimentos/` retorna 403 ou index, não listagem | ✅ |
