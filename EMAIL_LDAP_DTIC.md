# Email para CTIC — Dados LDAP + Hospedagem (Garopaba)

> **Nota:** Este email é de caráter técnico/operacional, direcionado à CTIC do câmpus.
> O processo formal de aprovação institucional (SIPAC → PROEN → DTIC → CGD) é separado
> e será aberto pela comissão de implantação — ver `SIPAC_SUBMISSAO.md`.

---

**Para:** ctic.garopaba@ifsc.edu.br  
**CC:** thiago.paes@ifsc.edu.br  
**Assunto:** Solicitação de dados LDAP e hospedagem — Sistema de Requerimentos Câmpus Garopaba

---

Prezados,

Estamos finalizando a implantação do **Sistema de Requerimentos Acadêmicos do IFSC Câmpus Garopaba** — sistema web que permite aos alunos protocolarem requerimentos (trancamento, 2ª chamada, validação de UC, entre outros) com workflow de aprovação pela coordenação pedagógica.

O sistema utiliza autenticação via **Active Directory (LDAP)** para acesso dos servidores à área administrativa. Uma comissão de implantação está sendo formada no câmpus para conduzir o processo institucional junto à PROEN e DTIC.

Para avançar na configuração técnica, precisamos das seguintes informações:

**1. Integração LDAP/AD:**
1. Endereço do servidor LDAP — ex.: `ldap.garopaba.ifsc.edu.br` (ou IP)
2. Porta (padrão: 389 ou 636 para LDAPS)
3. Base DN dos usuários — as UOs onde os servidores estão cadastrados no AD
4. Se bind anônimo não for permitido: usuário e senha de conta de serviço para leitura do diretório
5. Regras de firewall necessárias para liberar acesso do servidor web ao AD

**2. Hospedagem local:**
6. Existe servidor disponível na CTIC Garopaba para hospedar uma aplicação web PHP + MySQL?
7. Se sim: endereço/hostname do servidor, versões disponíveis (PHP 8.x, MySQL/MariaDB), e processo para implantação

Agradecemos desde já.

Atenciosamente,  
Prof. Thiago Paes  
thiago.paes@ifsc.edu.br  
IFSC Câmpus Garopaba
