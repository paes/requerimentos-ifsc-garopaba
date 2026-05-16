# Regras de Negócio
## Sistema de Requerimentos Acadêmicos — IFSC Câmpus Garopaba

**Versão:** 1.0 | **Data:** 2026-05-16

---

## Protocolo e Identificação

| Código | Regra |
|--------|-------|
| RN001 | Todo requerimento recebe um número de protocolo único, gerado automaticamente no formato `GPB-AAAA-NNNNN` |
| RN002 | O número de protocolo é imutável após a criação do requerimento |
| RN003 | O aluno deve informar nome completo, e-mail, matrícula e curso para protocolar qualquer requerimento |
| RN004 | Se o aluno for menor de 18 anos, é obrigatório informar nome e telefone do responsável legal |
| RN005 | O aluno deve aceitar a declaração de veracidade das informações para enviar o requerimento |

## Tipos de Requerimento

| Código | Regra |
|--------|-------|
| RN010 | O sistema oferece 23 tipos de requerimento, todos baseados no RDP IFSC (Resolução CONSUP nº 20/2018) |
| RN011 | Cada tipo de requerimento pode ter texto informativo (`information`) e aviso de atenção (`attention`) exibidos ao aluno antes do envio |
| RN012 | Tipos marcados como `featured` aparecem em destaque na tela de seleção |
| RN013 | O tipo "Outro" permite ao aluno descrever livremente situações não cobertas pelos tipos cadastrados |
| RN014 | Cada tipo de requerimento tem um workflow de tramitação próprio, definido em `workflow_steps` |

## Workflow e Tramitação

| Código | Regra |
|--------|-------|
| RN020 | Todo requerimento inicia com status `pending` (pendente) |
| RN021 | A tramitação segue a ordem de etapas definida em `workflow_steps.step_order` |
| RN022 | Cada etapa requer um perfil (`role`) específico para análise (coordenador, secretaria, pedagógico, etc.) |
| RN023 | O responsável pela etapa atual pode deferir, indeferir, encaminhar ou solicitar complementação |
| RN024 | Ao concluir todas as etapas com deferimento, o status muda para `approved` |
| RN025 | O indeferimento em qualquer etapa encerra o fluxo com status `rejected` |
| RN026 | O status `concluded` indica encerramento administrativo após aprovação |
| RN027 | Apenas usuários com permissão no perfil da etapa atual podem avançar na tramitação |
| RN028 | Cada ação de tramitação gera registro em `request_history` com usuário, data e observação |

## Notificações

| Código | Regra |
|--------|-------|
| RN030 | O aluno recebe e-mail de confirmação imediatamente após o protocolo, com número de protocolo e resumo |
| RN031 | Os coordenadores/responsáveis pela etapa atual recebem e-mail de notificação quando um requerimento chega para análise |
| RN032 | O aluno recebe notificação por e-mail a cada mudança de status no seu requerimento |
| RN033 | Os e-mails usam template institucional com identidade visual do IFSC |
| RN034 | Em ambiente de desenvolvimento (`ENABLE_EMAILS=false`), os e-mails são gravados em arquivo local em vez de enviados |

## Anexos e Documentos

| Código | Regra |
|--------|-------|
| RN040 | O aluno pode anexar documentos comprobatórios ao requerimento |
| RN041 | Os arquivos são armazenados em `public/uploads/` com nome anonimizado |
| RN042 | Apenas usuários autenticados no painel administrativo podem visualizar os anexos |

## Dados Pessoais e LGPD

| Código | Regra |
|--------|-------|
| RN050 | Os dados pessoais do aluno são tratados com base em obrigação legal (LGPD, Art. 7º, II) |
| RN051 | Campos de telefone (aluno e responsável) são armazenados criptografados com AES-256-CBC |
| RN052 | Os logs de e-mail gerados em desenvolvimento são apagados automaticamente após 90 dias |
| RN053 | O aluno é informado sobre o tratamento de seus dados por aviso de privacidade exibido no formulário e no rodapé |
| RN054 | O acesso ao painel administrativo (dados dos alunos) requer autenticação via LDAP/AD institucional |

## Autenticação e Perfis

| Código | Regra |
|--------|-------|
| RN060 | O acesso à área administrativa requer autenticação via Active Directory (LDAP) do câmpus |
| RN061 | Os perfis disponíveis são: Administrador, Coordenador de Curso, Secretaria, Coordenador Pedagógico, Assistência Estudantil e outros |
| RN062 | Cada usuário administrativo é associado a um ou mais cursos, limitando sua visibilidade de requerimentos |
| RN063 | O administrador tem acesso irrestrito a todos os requerimentos e configurações do sistema |
| RN064 | O formulário público (aluno) não requer autenticação — identificação por dados cadastrais e número de matrícula |

## Acompanhamento pelo Aluno

| Código | Regra |
|--------|-------|
| RN070 | O aluno pode consultar o status do requerimento via `check_status.php` informando o número de protocolo e e-mail |
| RN071 | A consulta pública exibe apenas status, histórico de tramitação e mensagens — sem dados de outros alunos |
