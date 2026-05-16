# Guia de Testes — Equipes do Câmpus
## Sistema de Requerimentos Acadêmicos — IFSC Câmpus Garopaba

**Versão:** 1.0 | **Data:** 2026-05-16  
**Responsável técnico:** Prof. Thiago Paes — thiago.paes@ifsc.edu.br

---

## 1. Como usar este guia

Este guia é destinado às equipes do câmpus que participarão dos testes de aceitação (UAT)
antes do go-live do sistema. Não é necessário conhecimento técnico — basta seguir os passos
descritos em cada cenário e registrar o resultado.

**Quem deve testar:**
- Secretaria Acadêmica
- Coordenadores de Curso
- Coordenadoria Pedagógica / Assistência Estudantil
- Qualquer servidor que simulará o papel de aluno

**Como registrar:**
- Marque ✓ na coluna resultado se o sistema se comportou conforme esperado
- Marque ✗ se algo falhou ou ficou diferente do esperado
- Anote observações detalhadas na coluna "Obs" (ex: mensagem de erro, comportamento inesperado)
- Registre seu nome e a data na tabela da Seção 4

**O que fazer se algo não funcionar:**
- Anote a descrição do problema (o que fez, o que apareceu na tela)
- Se possível, tire um print da tela
- Encaminhe ao responsável técnico: thiago.paes@ifsc.edu.br

---

## 2. Ambiente de testes

| Item | Valor |
|------|-------|
| URL do formulário público (aluno) | *(preencher antes do UAT)* |
| URL do painel administrativo | *(URL)/admin |
| Credenciais de teste (coordenador) | *(fornecer pelo responsável técnico)* |
| Credenciais de teste (secretaria) | *(fornecer pelo responsável técnico)* |
| E-mail para teste de aluno | *(usar e-mail institucional do testador)* |
| Matrícula fictícia para teste | ex.: 2026001 |
| Navegadores suportados | Chrome, Firefox, Edge (versões recentes) |

> **Atenção:** durante os testes, use dados fictícios — nunca dados reais de alunos.

---

## 3. Cenários de teste por perfil

### 3.1 Perfil: Aluno
*(Pode ser testado por qualquer servidor simulando um aluno)*

| # | Cenário | Passos | Resultado esperado | ✓/✗ | Obs |
|---|---------|--------|--------------------|-----|-----|
| A01 | Acessar o formulário público | Abrir a URL do sistema no navegador | Página carrega corretamente, sem erros | | |
| A02 | Selecionar tipo de requerimento | Na tela de seleção, clicar em qualquer tipo | Descrição do tipo aparece e a página rola automaticamente até ela | | |
| A03 | Preencher e enviar requerimento completo | Preencher todos os campos obrigatórios e clicar em Enviar | Número de protocolo no formato `GPB-AAAA-NNNNN` é exibido na tela de confirmação | | |
| A04 | Receber e-mail de confirmação | Após o envio, verificar a caixa de e-mail usada no formulário | E-mail recebido com número de protocolo e resumo do requerimento | | |
| A05 | Consultar status pelo protocolo | Acessar a página de consulta, informar protocolo e e-mail | Status atual e histórico de tramitação são exibidos | | |
| A06 | Formulário para aluno menor de 18 anos | Marcar a opção "sou menor de idade" | Campos de nome e telefone do responsável legal aparecem e são obrigatórios | | |
| A07 | Tentar enviar sem campo obrigatório | Deixar o campo "Nome completo" em branco e tentar enviar | Formulário bloqueia o envio e exibe mensagem indicando o campo faltante | | |
| A08 | Anexar documento | No formulário, usar o campo de anexo para enviar um PDF ou imagem | Arquivo é aceito e aparece como anexado antes do envio | | |

---

### 3.2 Perfil: Coordenador de Curso

| # | Cenário | Passos | Resultado esperado | ✓/✗ | Obs |
|---|---------|--------|--------------------|-----|-----|
| C01 | Login no painel administrativo | Acessar `/admin`, inserir e-mail e senha institucional | Acesso liberado; dashboard exibido | | |
| C02 | Visualizar lista de requerimentos | No dashboard, observar a lista | Aparecem apenas requerimentos dos cursos associados ao coordenador | | |
| C03 | Filtrar por status "Pendente" | Usar o filtro de status | Lista exibe apenas requerimentos pendentes | | |
| C04 | Filtrar por tipo de requerimento | Selecionar um tipo no filtro | Lista filtra corretamente | | |
| C05 | Abrir detalhes de um requerimento | Clicar em um requerimento da lista | Página exibe todos os dados do aluno, tipo, descrição, histórico e anexos | | |
| C06 | Visualizar anexo do aluno | Na tela de detalhes, clicar no arquivo anexo | Arquivo abre ou faz download corretamente | | |
| C07 | Deferir requerimento | Clicar em "Deferir", escrever um parecer e confirmar | Status muda para "Deferido"; aluno recebe notificação por e-mail | | |
| C08 | Indeferir requerimento | Clicar em "Indeferir", escrever um parecer e confirmar | Status muda para "Indeferido"; aluno recebe notificação por e-mail | | |
| C09 | Verificar isolamento de cursos | Tentar acessar pelo ID na URL um requerimento de outro curso | Acesso negado ou requerimento não aparece | | |
| C10 | Receber e-mail de novo requerimento | Aguardar ou solicitar que aluno de teste envie requerimento | Coordenador recebe e-mail de notificação com dados e link direto | | |

---

### 3.3 Perfil: Secretaria Acadêmica

| # | Cenário | Passos | Resultado esperado | ✓/✗ | Obs |
|---|---------|--------|--------------------|-----|-----|
| S01 | Login e acesso ao painel | Acessar `/admin` com credenciais da secretaria | Acesso liberado conforme perfil | | |
| S02 | Visualizar todos os requerimentos | Observar o dashboard | Lista exibe requerimentos conforme permissões do perfil | | |
| S03 | Encaminhar requerimento na tramitação | Selecionar um requerimento e avançar a etapa | Próxima etapa registrada; responsável da próxima etapa notificado | | |
| S04 | Verificar histórico de tramitação | Abrir detalhes → seção de histórico | Todas as ações registradas com data, usuário e observação | | |
| S05 | Receber e-mail de novo requerimento | Envio de requerimento por aluno de teste | E-mail de notificação chega para a secretaria (se configurada no workflow) | | |

---

### 3.4 Perfil: Administrador

| # | Cenário | Passos | Resultado esperado | ✓/✗ | Obs |
|---|---------|--------|--------------------|-----|-----|
| ADM01 | Gerenciar tipos de requerimento | Painel → Tipos de Requerimento → editar um tipo | Alteração salva e refletida no formulário público | | |
| ADM02 | Criar novo usuário | Painel → Usuários → Novo | Usuário criado com perfil e curso; consegue fazer login | | |
| ADM03 | Editar associação de curso de usuário | Painel → Usuários → editar | Novo curso associado; requerimentos visíveis conforme mudança | | |
| ADM04 | Acessar sistema sem autenticação | Abrir `/admin` sem estar logado | Redireciona para a tela de login | | |

---

## 4. Registro de execução dos testes

Preencha uma linha por sessão de teste realizada:

| Data | Testador | Perfil testado | Cenários executados | Cenários com falha | Observações gerais |
|------|----------|---------------|---------------------|--------------------|--------------------|
| | | | | | |
| | | | | | |
| | | | | | |

**Versão do sistema testada:** _______________  
**Ambiente:** [ ] Desenvolvimento [ ] Homologação [ ] Produção  
**Resultado geral:** [ ] Aprovado para go-live [ ] Aprovado com ressalvas [ ] Reprovado — corrigir antes do go-live

---

## 5. Tipos de teste — visão geral

O sistema passou ou passará pelos seguintes tipos de teste exigidos pela Resolução CGD nº 03/2025:

| Tipo | Descrição | Status | Onde está documentado |
|------|-----------|--------|-----------------------|
| Testes técnicos (desenvolvedor) | Verificação de funcionalidades, segurança, criptografia, acesso | ✅ Feito | `docs/sipac/backlog.md` — TC01 a TC13 |
| Testes de usabilidade e aceitação (UAT) | Executados pelas equipes do câmpus conforme este guia | ⏳ Fase 2 | Este documento — Seção 3 e 4 |
| Testes unitários / automatizados | Testes de código automatizados | 📋 Fase 3 (avaliar conforme volume do sistema) | — |

> Os testes de usabilidade e aceitação (este guia) são o principal instrumento de validação
> com usuários reais antes do go-live. Devem ser executados em ambiente de homologação com
> dados fictícios, preferencialmente com os servidores que usarão o sistema no dia a dia.
