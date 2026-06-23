# E-mail à DTIC — Hospedagem do código no git.ifsc.edu.br

> Rascunho para envio pela comissão. Preencher os campos entre colchetes antes de enviar.

---

**Para:** DTIC — Diretoria de Tecnologia da Informação e Comunicação (Diretor: Sérgio Nicolau da Silva) — dtic@ifsc.edu.br
**Com cópia (Cc):** membros da comissão de implantação
- Prof. Thiago Paes — thiago.paes@ifsc.edu.br
- Prof. Nauber Gavski — nauber.gavski@ifsc.edu.br
- Profa. Sabrina Pacheco — `[e-mail]`
- Luciane Stein (Registro Acadêmico) — `[e-mail]`
- Thiago Waltrik (CTIC Garopaba) — thiago.waltrik@ifsc.edu.br
- Antonio Luiz Schalata (CTIC/DTIC) — `[e-mail]`

**Assunto:** Orientações para hospedagem de código no git.ifsc.edu.br — Sistema de Requerimentos (Câmpus Garopaba)

**Anexos:** Portaria da Direção-Geral do Câmpus Garopaba nº 130, de 15/06/2026 (institui a comissão); Resolução CGD nº 03/2025 (referência)

---

Prezado Diretor de Tecnologia da Informação e Comunicação, Sérgio Nicolau da Silva, prezada equipe da DTIC,

Somos a **Comissão de Desenvolvimento, Planejamento e Implantação do Sistema Web de Gestão de Requerimentos e Gestão de Ensino** do IFSC Câmpus Garopaba, formalmente instituída pela **Portaria da Direção-Geral do Câmpus Garopaba nº 130, de 15 de junho de 2026** (em anexo).

Estamos conduzindo a implantação do sistema **em conformidade com a Resolução CGD nº 03, de 25/04/2025** (Política de Desenvolvimento e Sustentação de Sistemas e Aplicações). Já elaboramos os artefatos da fase inicial previstos na política (Documento de Visão, Regras de Negócio, Plano de Releases e Backlog do Produto) e estamos na etapa de migração do código-fonte para o controle de versão institucional.

**Sobre o sistema (resumo):** é uma plataforma web que centraliza processos acadêmicos do câmpus hoje dispersos em e-mails, planilhas e atendimentos presenciais — protocolo eletrônico de requerimentos pelos alunos (trancamento, 2ª chamada, validação de UC, exercício domiciliar, entre outros), com fluxo de análise e tramitação pelas coordenações e secretaria, além de módulos de substituição docente e equidade de horários. É desenvolvido em **PHP 8.4 com banco de dados MySQL**, e a **autenticação dos servidores está prevista para usar o LDAP/AD institucional**.

**Nossa solicitação:** gostaríamos de **orientações sobre como hospedar o código-fonte no `git.ifsc.edu.br`**, conforme exigido pela Resolução CGD 03/2025, especificamente:

1. Como **obter acesso e criar um repositório (privado)** no `git.ifsc.edu.br` para o projeto, e quais permissões/perfis são necessários para os membros da comissão.
2. Como **acessar o `git.ifsc.edu.br` fora da rede interna do câmpus**. Já instalamos e conectamos o **FortiClient VPN da Reitoria (`vpn.reitoria.ifsc.edu.br`)**; outros serviços internos funcionam normalmente pela VPN (ex.: `dgp.ifsc.edu.br`), porém o `git.ifsc.edu.br` (191.36.0.206) permanece inacessível mesmo com a VPN conectada — a porta 443 não responde pelo túnel. Há alguma liberação adicional necessária, perfil de VPN específico, ou outra forma de acesso recomendada?

Adicionalmente, como pretendemos integrar a **autenticação dos servidores ao LDAP/AD institucional**, agradecemos também orientações (ou a indicação do procedimento/abertura de chamado adequado) para obtenção dos dados de conexão LDAP do câmpus — caso isso deva ser tratado em processo separado, por favor nos oriente.

Permanecemos à disposição para fornecer qualquer documentação adicional (incluindo os artefatos já produzidos) e para os alinhamentos técnicos necessários.

Atenciosamente,

**Prof. Thiago Paes**
Responsável técnico — Comissão de Implantação do Sistema de Requerimentos
IFSC Câmpus Garopaba
thiago.paes@ifsc.edu.br
