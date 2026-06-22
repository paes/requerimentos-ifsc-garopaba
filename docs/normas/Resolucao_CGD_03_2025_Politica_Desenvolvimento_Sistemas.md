# Resolução CGD nº 03, de 25 de abril de 2025

> **Transcrição** da norma institucional do IFSC para referência da comissão de implantação.
> O PDF **assinado** original deve ser guardado nesta mesma pasta como
> `Politica_de_Desenvolvimento_e_Sustentacao_de_Sistemas_e_Aplicacoes_assinado.pdf`.
>
> **Base legal citada em:** [ROTEIRO_IMPLANTACAO.pdf](../../ROTEIRO_IMPLANTACAO.pdf), [SIPAC_SUBMISSAO.md](../../SIPAC_SUBMISSAO.md), [docs/sipac/](../sipac/).

---

## Dados da norma

| Campo | Valor |
|---|---|
| Título | Política de Desenvolvimento e Sustentação de Sistemas e Aplicações do IFSC |
| Resolução | CGD nº 03, de 25 de abril de 2025 |
| Apreciado por | Comitê Técnico de TIC (CTTIC) — 20/03/2025 |
| Aprovado por | Comitê de Governança Digital (CGD) — 25/04/2025 |
| Entrada em vigor | 02 de junho de 2025 |
| Revisão | 2 anos após a aprovação/publicação |
| Versão | 1.0 |
| Presidente do CGD | Sabrina Moro Villela Pacheco (Pró-reitora de Desenvolvimento Institucional) |
| Secretário-Executivo / Elaboração | Benoni de Oliveira Pires (Diretor de TIC) |
| Políticas relacionadas | Guia de Projetos de Software com Práticas de Métodos Ágeis para o SISP |
| Armazenamento oficial | Datacenter do IFSC — Portal Institucional |

**Súmula da reunião do CGD:** https://sigrh.ifsc.edu.br/sigrh/downloadArquivo?idArquivo=4096837&key=7d809944af0a90dcd2d7a8d383c4fc7e

---

## Resolução

A Presidente do Comitê de Governança Digital do IFSC, no uso das atribuições conferidas pelo Art. 6º, inciso IV e Art. 9º deste comitê, **RESOLVE**:

- **Art. 1º** Aprovar a Política de Desenvolvimento e Sustentação de Sistemas e Aplicações do IFSC.
- **Art. 2º** Esta resolução entra em vigor na data de 02 de junho de 2025.

---

## Política — Objetivo

Estabelecer diretrizes para o desenvolvimento de software no IFSC, assegurando conformidade com os requisitos do **SISP** (Sistema de Administração dos Recursos de Tecnologia da Informação), garantindo padronização, qualidade, segurança, integração com a infraestrutura de TI institucional e aderência aos processos institucionais — com participação da **DTIC**, aprovação do **CGD** e definições das áreas responsáveis.

## Escopo

Aplica-se a **todos** os projetos de desenvolvimento de software no IFSC — desenvolvimento interno (estudantes, docentes, TAEs) ou qualquer público interno/externo — incluindo sistemas web, apps móveis, sistemas administrativos, plataformas de ensino, etc.

> **Nota (rodapé):** desenvolvimento de sistemas como **atividade puramente acadêmica** não é abrangido. Mas se a atividade ultrapassa o ambiente acadêmico e visa um sistema para uso no IFSC (câmpus ou instituição), **estas regras devem ser observadas**. Para hospedagem de artefatos pedagógicos, observar a Resolução CGD 009/2021.

## Diretrizes Gerais

### Acordo prévio com as áreas envolvidas
Sistemas/aplicações web **não iniciados na DTIC** devem:
1. Ser previamente submetidos às **áreas responsáveis** pelos processos relacionados (PROAD, PROPPI, PROEN, PROEX, PRODIN — conforme a finalidade);
2. As áreas solicitam à **DTIC** a avaliação da demanda, justificando a relevância. A DTIC avalia **viabilidade técnica, alinhamento com a infraestrutura, necessidade de integração com os sistemas SIG e aderência a processos e normas**;
3. Se a DTIC negar, cabe pedido de reconsideração ao **CGD** (cgd@listas.ifsc.edu.br).

> Todos esses passos devem ser **oficializados via cadastro de processo no SIPAC**.

### Aprovação pelo CGD
Firmado o acordo, o projeto é submetido ao **CGD** para avaliação e aprovação — verificando conformidade com as políticas de governança de TI, a estratégia de TI do IFSC e as diretrizes do SISP.

### Alinhamento com o SISP
Seguir o *Guia: Processo de Software para o SISP* e, no que couber, a **Portaria SGD/ME nº 5.651, de 28/06/2022**.

### Processos e metodologias ágeis
A fase inicial de planejamento deve entregar, **no mínimo**:
- **Documento de Visão**
- **Regras de Negócio**
- **Plano de Releases**
- **Sprints e Backlog do Produto**

## Requisitos Técnicos e Qualidade

- Iterações curtas, entregas frequentes, escopos delimitados (preferencialmente processo de software do SISP);
- Adotar **Metodologias Ágeis** (Guia de Métodos Ágeis do SISP);
- Observar, no que couber, a **ABNT NBR 12.207/2021** (ciclo de vida de software);
- Prever diretrizes sobre: fluxo de valor, visão do produto, roadmap, papéis/iteração, **codificação limpa**, **codificação segura**, técnicas ágeis, **testes** (unitários, integração, release, sistema, componentes, desempenho), validação com solicitante, verificação/implantação, sustentação e manutenção.

### Qualidade do software
- **Testes**: unitários, integração, usabilidade e aceitação;
- **Documentação**: técnica e de usuário, mantida atualizada em todas as fases;
- **Código**: claro, modular, reutilizável e bem documentado.

### Segurança da Informação
Garantir segurança da informação desde as fases iniciais: proteção contra acessos não autorizados, gerenciamento de privilégios e **criptografia de dados sensíveis**.
> **A DTIC utilizará ferramentas de varredura em aplicações web para avaliação de segurança.**

### Integração com Infraestrutura de TI
Compatibilidade com a infraestrutura do IFSC (servidores, redes, bancos, etc.), garantindo estabilidade e escalabilidade.

## Gestão de Mudanças e Atualizações

- **Controle de versões:** todo o código-fonte deve ser gerenciado no controle de versão institucional **https://git.ifsc.edu.br**.
- **Atualizações:** planejadas e executadas de forma coordenada com a DTIC e demais áreas, devidamente testadas, minimizando impacto.

## Capacitação e Treinamento
A DTIC capacita a equipe do DSI; a capacitação dos **usuários** dos sistemas é responsabilidade das áreas ligadas aos processos.

## Monitoramento e Avaliação
A DTIC acompanha prazos/orçamento/requisitos; o CGD faz avaliações periódicas (satisfação do usuário, desempenho, incidentes de segurança).
> **Projetos em discordância com esta política não deverão ser hospedados na infraestrutura do IFSC (reitoria ou câmpus).**

## Penalidades e Não Conformidade
- Descumprimento → medidas corretivas (da revisão à suspensão do desenvolvimento).
- Sistemas fora da política **não serão hospedados** na infra da DTIC/CTICs/DTICOM.
- Coordenadores das CTICs e chefe da DTICOM são responsabilizados por hospedagem em desalinhamento em caso de eventos maliciosos de segurança.
- Sistemas já hospedados e desalinhados na data de publicação têm **12 meses para adequação**, sob pena de remoção.

---

## Anexo I — Fluxo do processo (desenvolvedor não lotado na DTIC/DSI)

Todo o trâmite ocorre **via SIPAC**:

1. **Desenvolvedor** submete a demanda às áreas responsáveis (PROAD/PROPPI/PROEN/PROEX/PRODIN, conforme finalidade).
2. **Área responsável** avalia se já é contemplada por sistema vigente; se não e houver interesse, encaminha à DTIC.
   - **3A:** área nega → informa o desenvolvedor e encerra.
   - **3B (DTIC/Reitoria):** avalia viabilidade técnica, alinhamento com infraestrutura, integração com SIG, aderência a normas. Viável → alinha com o desenvolvedor; inviável → devolve recusa às áreas.
3. **4A (DTIC):** encaminha resposta com motivos da recusa.
4. **5 (área):** acata e informa o desenvolvedor, ou **recorre ao CTTIC**.
5. **6–7 (CTTIC):** avalia o recurso e informa a decisão final (aceita → alinhamento DTIC/desenvolvedor; recusada → não pode ser desenvolvido).

### Detalhamento mínimo para análise da DTIC (⚠️ atenção a divergências do nosso projeto)
**Requisitos não funcionais preferenciais da política:**
- Desenvolvimento em **JAVA**
- Banco de dados **preferencialmente PostgreSQL**
- Apps móveis aderentes ao Decreto nº 8.936/2016 e Portaria SGD nº 39/2019
- Segurança baseada em referências da CERT e órgãos federais

Campos a informar: Identificação do **Dono do Produto** e do **Responsável pelo Projeto** (nome, chat, telefone, e-mail).
> **OBS:** se a aplicação já estiver desenvolvida, informar o **link do projeto no controle de versão institucional**.
