# Email para CTIC/DTIC — Dados LDAP Garopaba

**Para:** ctic.garopaba@ifsc.edu.br (ou contato da CTIC de Garopaba)
**CC:** dtic@ifsc.edu.br
**Assunto:** Solicitação de dados LDAP para integração de sistema web — Câmpus Garopaba

---

Prezados,

Estamos implantando no Câmpus Garopaba o **Sistema de Requerimentos do IFSC**, desenvolvido pelo Prof. Eduardo Gomes (Câmpus Canoinhas) e disponível em https://github.com/paes/requisicoes_ifsc_gpb. O sistema utiliza autenticação via **Active Directory (LDAP)** para acesso dos servidores à área administrativa.

Para realizar a integração com o AD do campus Garopaba, precisamos das seguintes informações:

1. **Endereço do servidor LDAP** — ex.: `ldap.garopaba.ifsc.edu.br`
2. **Porta** (padrão: 389 ou 636 para LDAPS)
3. **Base DN dos usuários** — as UOs (Unidades Organizacionais) onde os servidores estão cadastrados no AD
4. Se o bind anônimo não for permitido: **usuário e senha de uma conta de serviço** para leitura do diretório
5. Se necessário, regras de firewall para liberar acesso do servidor web ao AD

Agradecemos desde já a atenção.

Atenciosamente,
[Seu nome] — IFSC Câmpus Garopaba
