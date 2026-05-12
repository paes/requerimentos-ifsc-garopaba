/*
SQLyog Community v13.2.1 (64 bit)
MySQL - 8.0.31 : Database - ifsc_requests
*********************************************************************
*/

/*!40101 SET NAMES utf8 */;

/*!40101 SET SQL_MODE=''*/;

/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
CREATE DATABASE /*!32312 IF NOT EXISTS*/`ifsc_requests` /*!40100 DEFAULT CHARACTER SET latin1 */ /*!80016 DEFAULT ENCRYPTION='N' */;

USE `ifsc_requests`;

/*Table structure for table `courses` */

DROP TABLE IF EXISTS `courses`;

CREATE TABLE `courses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `level` enum('Técnico Integrado','Técnico Concomitante','Graduação','Pós Graduação','Formação Continuada','PROEJA') COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `courses` */

insert  into `courses`(`id`,`name`,`level`,`active`) values
(1,'Técnico em Administração','Técnico Integrado',1),
(2,'Técnico em Lazer','Técnico Integrado',1),
(3,'Técnico em Informática','Técnico Integrado',1),
(4,'Técnico em Biotecnologia','Técnico Concomitante',1),
(7,'CST em Sistemas para Internet','Graduação',1),
(8,'CST em Gestão Ambiental','Graduação',1),
(15,'FIC em Inglês','Formação Continuada',1),
(16,'Técnico em Guia de Turismo','Técnico Concomitante',1),
(17,'PROEJA em Serviços de Restauração e Bar','PROEJA',1);

/*Table structure for table `email_config` */

DROP TABLE IF EXISTS `email_config`;

CREATE TABLE `email_config` (
  `id` int NOT NULL AUTO_INCREMENT,
  `host` varchar(255) NOT NULL,
  `port` int NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `encryption` varchar(50) NOT NULL,
  `from_email` varchar(255) NOT NULL,
  `from_name` varchar(255) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;

/*Data for the table `email_config` */

insert  into `email_config`(`id`,`host`,`port`,`username`,`password`,`encryption`,`from_email`,`from_name`,`updated_at`) values 
(1,'smtp.gmail.com',587,'secretaria.can@ifsc.edu.br',' ','tls','secretaria.can@ifsc.edu.br','IFSC Requisições','2026-05-07 19:53:00');

/*Table structure for table `email_queue` */

DROP TABLE IF EXISTS `email_queue`;

CREATE TABLE `email_queue` (
  `id` int NOT NULL AUTO_INCREMENT,
  `to_email` varchar(255) NOT NULL,
  `to_name` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `status` enum('pending','sending','sent','failed') DEFAULT 'pending',
  `attempts` int DEFAULT '0',
  `last_attempt` datetime DEFAULT NULL,
  `error_message` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

/*Data for the table `email_queue` */

/*Table structure for table `request_files` */

DROP TABLE IF EXISTS `request_files`;

CREATE TABLE `request_files` (
  `id` int NOT NULL AUTO_INCREMENT,
  `request_id` int NOT NULL,
  `filepath` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `request_id` (`request_id`),
  CONSTRAINT `request_files_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `request_files` */

/*Table structure for table `request_history` */

DROP TABLE IF EXISTS `request_history`;

CREATE TABLE `request_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `request_id` int NOT NULL,
  `user_id` int NOT NULL,
  `action` enum('approve','reject','comment') COLLATE utf8mb4_unicode_ci NOT NULL,
  `observation` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `request_id` (`request_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `request_history_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `request_history_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `request_history` */

/*Table structure for table `request_types` */

DROP TABLE IF EXISTS `request_types`;

CREATE TABLE `request_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `information` text COLLATE utf8mb4_unicode_ci,
  `attention` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `request_types` */

insert  into `request_types`(`id`,`name`,`active`,`information`,`attention`) values 
(1,'Justificativa de Falta(s)',1,'Use esta opção para encaminhar pedidos de justificativas de faltas. Informe o período da(s) falta(s) e unidades curriculares. Se for mais de uma atestado em períodos diferentes, faça uma requisição por atestado.','Anexe os comprovantes, tais como: Atestados médicos, comprovante de convocação militar, certidões, etc.'),
(2,'Avaliação de Segunda Chamada',1,NULL,NULL),
(3,'Trabalhos Domiciliares',1,NULL,NULL),
(4,'Trancamento de Curso',1,'',''),
(5,'Cancelamento de Curso',1,'',''),
(6,'Transferência para outra instituição',1,'Pedido de transferência para outra instituição.',''),
(7,'Matrícula em Componente Curricular Isolado',1,NULL,NULL),
(8,'Expedição de Diploma / Certificados',1,NULL,NULL),
(9,'Validação de Unidade Curricular',1,NULL,NULL),
(10,'Retorno de Trancamento',1,NULL,NULL),
(11,'Reingresso de Matricula Cancelada',1,NULL,NULL),
(12,'Matrícula Especial em Componente Curricular',1,NULL,NULL),
(13,'Apoio Educacional Especializado',1,NULL,NULL),
(14,'Ajuste de Matrícula',1,'Use esta opção para solicitar:\r\n- Matrícula em unidade curricular. \r\n- Cancelar matrícula de unidade curricular. \r\n- Troca de turma.','Fique atento aos prazos de ajuste de matrícula.'),
(15,'Planos de Estudo',1,NULL,NULL),
(16,'Extraordinário Aproveitamento de Estudos',1,NULL,NULL),
(17,'Quebra de Pré-requisitos',1,NULL,NULL),
(18,'Colação em Gabinete',1,NULL,NULL),
(19,'Outro: (Especifique nas observações)',0,'Usado para pedidos ao Coordenador do Curso que não estejam listados nas opções acima.','::::ATENÇÃO::::::\r\nUse esta opção SOMENTE se NÃO encontrar o tipo de requisição adequado nas opções acima.'),
(20,'Solicitação de Horário Diferenciado',1,NULL,NULL),
(21,'Assistência Estudantil',1,'<style>\r\n    /* Reset de estilos básicos para integração no Moodle */\r\n    .ifsc-wrapper {\r\n        font-family: \'Inter\', -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif;\r\n        color: #445566;\r\n        max-width: 1000px;\r\n        margin: 0 auto;\r\n        padding: 10px;\r\n        background-color: transparent;\r\n    }\r\n\r\n    /* Cabeçalho estilo SIGAA */\r\n    .ifsc-header-main {\r\n        display: flex;\r\n        align-items: center;\r\n        margin-bottom: 20px;\r\n        padding-bottom: 15px;\r\n        border-bottom: 1px solid #e2e8f0;\r\n    }\r\n\r\n    .ifsc-step-number {\r\n        background-color: #5bb29b;\r\n        color: white;\r\n        width: 32px;\r\n        height: 32px;\r\n        border-radius: 50%;\r\n        display: flex;\r\n        align-items: center;\r\n        justify-content: center;\r\n        font-weight: bold;\r\n        margin-right: 15px;\r\n        flex-shrink: 0;\r\n    }\r\n\r\n    .ifsc-header-main h1 {\r\n        font-size: 20px;\r\n        font-weight: 600;\r\n        color: #2c3e50;\r\n        margin: 0;\r\n    }\r\n\r\n    /* Alerta de leitura com maior destaque */\r\n    .ifsc-critical-alert {\r\n        background-color: #fff5f5;\r\n        border: 2px solid #feb2b2;\r\n        border-left: 6px solid #e53e3e;\r\n        padding: 20px;\r\n        border-radius: 8px;\r\n        margin-bottom: 25px;\r\n        box-shadow: 0 4px 6px rgba(229, 62, 62, 0.05);\r\n    }\r\n\r\n    .ifsc-critical-alert h2 {\r\n        color: #c53030;\r\n        font-size: 18px;\r\n        margin: 0 0 10px 0;\r\n        text-transform: uppercase;\r\n        font-weight: 800;\r\n        letter-spacing: 0.5px;\r\n    }\r\n\r\n    .ifsc-critical-alert p {\r\n        margin: 0;\r\n        font-weight: 700;\r\n        color: #2d3748;\r\n        line-height: 1.5;\r\n        font-size: 15px;\r\n    }\r\n\r\n    /* Seções e Cards */\r\n    .ifsc-section {\r\n        background: #ffffff;\r\n        border: 1px solid #e2e8f0;\r\n        border-radius: 8px;\r\n        padding: 20px;\r\n        margin-bottom: 20px;\r\n    }\r\n\r\n    .ifsc-section-title {\r\n        color: #2c3e50;\r\n        font-size: 16px;\r\n        font-weight: 700;\r\n        margin-bottom: 15px;\r\n        display: flex;\r\n        align-items: center;\r\n        text-transform: uppercase;\r\n        border-bottom: 2px solid #edf2f7;\r\n        padding-bottom: 8px;\r\n    }\r\n\r\n    .ifsc-card-link {\r\n        background-color: #f8fafc;\r\n        border: 1px solid #e2e8f0;\r\n        border-radius: 8px;\r\n        padding: 12px 15px;\r\n        display: flex;\r\n        align-items: center;\r\n        text-decoration: none;\r\n        color: #445566;\r\n        font-size: 14px;\r\n        transition: all 0.2s;\r\n        margin-bottom: 10px;\r\n    }\r\n\r\n    .ifsc-card-link:hover {\r\n        background-color: #f1f5f9;\r\n        border-color: #cbd5e1;\r\n    }\r\n\r\n    .ifsc-card-link svg {\r\n        margin-right: 12px;\r\n        color: #64748b;\r\n    }\r\n\r\n    /* TABELA MODERNA */\r\n    .ifsc-table-container {\r\n        overflow-x: auto;\r\n        margin-top: 15px;\r\n        border-radius: 8px;\r\n        border: 1px solid #e2e8f0;\r\n        box-shadow: 0 1px 3px rgba(0,0,0,0.02);\r\n    }\r\n\r\n    .ifsc-table {\r\n        width: 100%;\r\n        border-collapse: collapse;\r\n        font-size: 13.5px;\r\n        color: #334155;\r\n        background-color: white;\r\n    }\r\n\r\n    .ifsc-table th {\r\n        background-color: #f1f5f9;\r\n        color: #475569;\r\n        font-weight: 700;\r\n        text-align: left;\r\n        padding: 14px 16px;\r\n        text-transform: uppercase;\r\n        letter-spacing: 0.025em;\r\n        border-bottom: 2px solid #e2e8f0;\r\n    }\r\n\r\n    .ifsc-table td {\r\n        padding: 14px 16px;\r\n        border-bottom: 1px solid #f1f5f9;\r\n        vertical-align: top;\r\n        line-height: 1.5;\r\n    }\r\n\r\n    /* Zebra Striping */\r\n    .ifsc-table tbody tr:nth-child(even) {\r\n        background-color: #fcfcfd;\r\n    }\r\n\r\n    .ifsc-table tbody tr:hover {\r\n        background-color: #f8fafc;\r\n    }\r\n\r\n    /* Botões de Download */\r\n    .ifsc-attachment-grid {\r\n        display: grid;\r\n        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));\r\n        gap: 12px;\r\n        margin-top: 15px;\r\n    }\r\n\r\n    .ifsc-btn-download {\r\n        background: #ffffff;\r\n        border: 2px solid #5bb29b;\r\n        border-radius: 8px;\r\n        padding: 15px;\r\n        display: flex;\r\n        align-items: center;\r\n        justify-content: space-between;\r\n        text-decoration: none;\r\n        color: #319795;\r\n        font-weight: 700;\r\n        font-size: 14px;\r\n        transition: all 0.2s;\r\n    }\r\n\r\n    .ifsc-btn-download:hover {\r\n        background-color: #f0fdfa;\r\n        transform: translateY(-1px);\r\n        box-shadow: 0 4px 6px rgba(0,0,0,0.05);\r\n    }\r\n\r\n    .ifsc-check-icon {\r\n        background: #5bb29b;\r\n        color: white;\r\n        width: 20px;\r\n        height: 20px;\r\n        border-radius: 50%;\r\n        display: inline-flex;\r\n        align-items: center;\r\n        justify-content: center;\r\n        font-size: 10px;\r\n    }\r\n\r\n    .ifsc-contact-item {\r\n        display: flex;\r\n        align-items: center;\r\n        margin-bottom: 8px;\r\n        font-size: 14px;\r\n    }\r\n\r\n    .ifsc-contact-item svg {\r\n        margin-right: 10px;\r\n        color: #64748b;\r\n        flex-shrink: 0;\r\n    }\r\n\r\n    @media (max-width: 768px) {\r\n        .ifsc-table td, .ifsc-table th { padding: 12px; font-size: 12px; }\r\n    }\r\n</style>\r\n\r\n<div class=\"ifsc-wrapper\">\r\n    <!-- Cabeçalho Principal -->\r\n    <div class=\"ifsc-header-main\">\r\n        <div class=\"ifsc-step-number\">!</div>\r\n        <h1>Detalhes da Solicitação: Assistência Estudantil</h1>\r\n    </div>\r\n\r\n    <!-- Bloco de Alerta Destacado -->\r\n    <div class=\"ifsc-critical-alert\">\r\n        <h2>ATENÇÃO, ESTUDANTES!</h2>\r\n        <p>LEIA ATENTAMENTE AS INFORMAÇÕES ABAIXO ANTES DE REALIZAR O SEU REQUERIMENTO</p>\r\n    </div>\r\n\r\n    <!-- Seção 1: IVS -->\r\n    <div class=\"ifsc-section\">\r\n        <div class=\"ifsc-section-title\">1. SOLICITAR O ÍNDICE DE VULNERABILIDADE SOCIAL (IVS)</div>\r\n        <p style=\"margin-bottom: 12px; font-size: 14.5px;\">- Enviar cópia da Folha resumo do Cadúnico, emitida nas Secretarias de Assistência Social ou nos Centros de Referência da Assistência Social (CRAS), ou pelo portal DATAPREV:</p>\r\n        <a href=\"https://cadunico.dataprev.gov.br/portal/\" target=\"_blank\" class=\"ifsc-card-link\">\r\n            <svg width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><circle cx=\"12\" cy=\"12\" r=\"10\"></circle><line x1=\"2\" y1=\"12\" x2=\"22\" y2=\"12\"></line><path d=\"M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z\"></path></svg>\r\n            <strong>https://cadunico.dataprev.gov.br/portal/</strong>\r\n        </a>\r\n        <p style=\"margin-top: 15px; line-height: 1.6; font-size: 14px;\">- <strong>Ingressante por cotas de renda inferior a 1 salário-mínimo:</strong> caso tenha ingressado há menos de 6 meses, pode requerer sua pontuação junto à comissão de análise de renda ou enviar Folha resumo do Cadúnico.</p>\r\n    </div>\r\n\r\n    <!-- Seção 2: Auxílios e Tabela -->\r\n    <div class=\"ifsc-section\">\r\n        <div class=\"ifsc-section-title\">2. SOLICITAR AUXÍLIOS</div>\r\n        <p style=\"margin-bottom: 12px; font-size: 14.5px;\">- Consultar editais diponíveis em:</p>\r\n        <a href=\"https://www.ifsc.edu.br/web/campus-canoinhas/assistencia-estudantil\" target=\"_blank\" class=\"ifsc-card-link\">\r\n            <svg width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><path d=\"M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71\"></path><path d=\"M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71\"></path></svg>\r\n            <strong>https://www.ifsc.edu.br/web/campus-canoinhas/assistencia-estudantil</strong>\r\n        </a>\r\n\r\n        <div class=\"ifsc-table-container\">\r\n            <table class=\"ifsc-table\">\r\n                <thead>\r\n                    <tr>\r\n                        <th style=\"width: 25%;\">AUXÍLIO</th>\r\n                        <th style=\"width: 40%;\">QUEM PODE ACESSAR</th>\r\n                        <th style=\"width: 35%;\">DOCUMENTOS NECESSÁRIOS</th>\r\n                    </tr>\r\n                </thead>\r\n                <tbody>\r\n                    <tr>\r\n                        <td><strong>Auxílio Ingressante Cotista</strong></td>\r\n                        <td>Estudantes matriculados em curso presencial que entraram no IFSC há menos de 6 meses por meio da cota de escola pública com renda inferior a 1 salário mínimo.</td>\r\n                        <td>• Anexo C</td>\r\n                    </tr>\r\n                    <tr>\r\n                        <td><strong>Auxílio Permanência</strong></td>\r\n                        <td>Estudantes matriculados em cursos presenciais, com renda bruta per capita de até 1 salário mínimo.</td>\r\n                        <td>• IVS válido<br>• Anexo A<br>• Anexo C (após ser contemplado)</td>\r\n                    </tr>\r\n                    <tr>\r\n                        <td><strong>Auxílio Compulsório</strong></td>\r\n                        <td>Estudantes, com renda bruta per capita de até 1 salário mínimo, inscritos no CadÚnico, os matriculados em cursos Proeja e os matriculados em cursos que façam parte de ações voltadas a públicos estratégicos.</td>\r\n                        <td>• Folha resumo CadÚnico<br>• Anexo C</td>\r\n                    </tr>\r\n                    <tr>\r\n                        <td><strong>Auxílio Emergencial</strong></td>\r\n                        <td>De caráter eventual, destina-se a estudantes matriculados em curso presencial, em situação financeira adversa e não previsível que impossibilite a permanência no curso.</td>\r\n                        <td>• Entrevista com Assistente Social do câmpus</td>\r\n                    </tr>\r\n                    <tr>\r\n                        <td>\r\n                            <strong>Apoio a Eventos</strong><br>\r\n                            <strong>Auxílio Moradia</strong><br>\r\n                            <strong>Auxílio Transporte</strong>\r\n                        </td>\r\n                        <td style=\"text-align: center; vertical-align: middle; background-color: #f8fafc;\">Consultar edital próprio</td>\r\n                        <td style=\"text-align: center; vertical-align: middle; background-color: #f8fafc;\">Consultar edital próprio</td>\r\n                    </tr>\r\n                </tbody>\r\n            </table>\r\n        </div>\r\n\r\n        <!-- Bloco de Downloads em Destaque -->\r\n        <div style=\"margin-top: 25px;\">\r\n            <p style=\"font-weight: bold; font-size: 14px; margin-bottom: 12px; color: #2c3e50; text-transform: uppercase; letter-spacing: 0.5px;\">Download dos Formulários (Anexos):</p>\r\n            <div class=\"ifsc-attachment-grid\">\r\n                <a href=\"/requerimentos/files/ANEXO_A_Requerimento_de_Inscricao_Auxilio_Permanencia.pdf\" target=\"_blank\" class=\"ifsc-btn-download\">\r\n                    ANEXO A - Auxílio Permanência\r\n                    <span class=\"ifsc-check-icon\">✓</span>\r\n                </a>\r\n                <a href=\"/requerimentos/files/ANEXO_B_Requerimento_de_Inscricao_Auxilio_Emergencial.pdf\" target=\"_blank\" class=\"ifsc-btn-download\">\r\n                    ANEXO B - Auxílio Emergencial\r\n                    <span class=\"ifsc-check-icon\">✓</span>\r\n                </a>\r\n                <a href=\"/requerimentos/files/ANEXO_C_Termo_de_Compromisso_Auxilios_PAEVS.pdf\" target=\"_blank\" class=\"ifsc-btn-download\">\r\n                    ANEXO C - Termo de Compromisso\r\n                    <span class=\"ifsc-check-icon\">✓</span>\r\n                </a>\r\n            </div>\r\n        </div>\r\n    </div>\r\n\r\n    <!-- Seção 3: PAEVS -->\r\n    <div class=\"ifsc-section\">\r\n        <div class=\"ifsc-section-title\">3. INFORMAÇÕES SOBRE SITUAÇÃO DE PAGAMENTOS E VALIDADE DO PAEVS</div>\r\n        <p style=\"margin-bottom: 12px; font-size: 14.5px;\">- Consultar primeiramente o sistema PAEVS:</p>\r\n        <a href=\"https://paevs.ifsc.edu.br/\" target=\"_blank\" class=\"ifsc-card-link\">\r\n            <svg width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><path d=\"M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6\"></path><polyline points=\"15 3 21 3 21 9\"></polyline><line x1=\"10\" y1=\"14\" x2=\"21\" y2=\"3\"></line></svg>\r\n            <strong>https://paevs.ifsc.edu.br/</strong>\r\n        </a>\r\n        <div style=\"margin-top: 15px;\">\r\n            <div class=\"ifsc-contact-item\">\r\n                <svg width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><path d=\"M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z\"></path><polyline points=\"22,6 12,13 2,6\"></polyline></svg>\r\n                <span style=\"line-height: 1.5;\">Se permanecer com dúvidas, entre em contato com a <strong>Assistente Social</strong> na Coordenadoria Pedagógica ou pelo e-mail: <br>\r\n                <a href=\"mailto:assistenciaestudantil.can@ifsc.edu.br\" style=\"color: #5bb29b; font-weight: 700; text-decoration: none;\">assistenciaestudantil.can@ifsc.edu.br</a></span>\r\n            </div>\r\n        </div>\r\n    </div>\r\n\r\n    <!-- Seção 4: Demais Informações -->\r\n    <div class=\"ifsc-section\">\r\n        <div class=\"ifsc-section-title\">4. DEMAIS INFORMAÇÕES</div>\r\n        <a href=\"https://www.ifsc.edu.br/en/editais-ivs\" target=\"_blank\" class=\"ifsc-card-link\">\r\n            <svg width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><path d=\"M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z\"></path><polyline points=\"14 2 14 8 20 8\"></polyline><line x1=\"16\" y1=\"13\" x2=\"8\" y2=\"13\"></line><line x1=\"16\" y1=\"17\" x2=\"8\" y2=\"17\"></line><polyline points=\"10 9 9 9 8 9\"></polyline></svg>\r\n            <strong>https://www.ifsc.edu.br/en/editais-ivs</strong>\r\n        </a>\r\n        <div class=\"ifsc-contact-item\" style=\"margin-top: 12px;\">\r\n            <svg width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><path d=\"M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z\"></path><circle cx=\"12\" cy=\"10\" r=\"3\"></circle></svg>\r\n            <span>Coordenadoria Pedagógica do câmpus</span>\r\n        </div>\r\n    </div>\r\n</div>','');

/*Table structure for table `requests` */

DROP TABLE IF EXISTS `requests`;

CREATE TABLE `requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `protocol_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `student_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `student_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `student_phone` varbinary(255) DEFAULT NULL,
  `student_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `course_id` int NOT NULL,
  `class_info` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_minor` tinyint(1) DEFAULT '0',
  `guardian_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guardian_phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_type_id` int NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `current_step_order` int DEFAULT '1',
  `status` enum('pending','approved','rejected','concluded') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `schedule_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `arrival_time_1` time DEFAULT NULL,
  `arrival_time_2` time DEFAULT NULL,
  `departure_time_1` time DEFAULT NULL,
  `departure_time_2` time DEFAULT NULL,
  `declaration_accepted` tinyint(1) DEFAULT '0',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `course_units` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `protocol_code` (`protocol_code`),
  KEY `course_id` (`course_id`),
  KEY `request_type_id` (`request_type_id`),
  CONSTRAINT `requests_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`),
  CONSTRAINT `requests_ibfk_2` FOREIGN KEY (`request_type_id`) REFERENCES `request_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `requests` */

/*Table structure for table `roles` */

DROP TABLE IF EXISTS `roles`;

CREATE TABLE `roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_course_bound` tinyint(1) DEFAULT '0',
  `is_sysadmin` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `roles` */

insert  into `roles`(`id`,`name`,`is_course_bound`,`is_sysadmin`) values 
(1,'Administrador do Sistema',0,1),
(2,'Coordenador de Curso',1,0),
(3,'Registro Acadêmico',0,0),
(4,'Biblioteca',0,0),
(5,'Secretaria',0,0),
(6,'DEPE',0,0),
(7,'Direção',0,0),
(9,'Núcleo de Acessibilidade Educacional',0,0),
(10,'Coordenadoria Pedagógica',0,0),
(11,'Coordenadoria de Estágio',0,0),
(12,'Coordenadoria de Assuntos Educacionais',0,0),
(13,'Assistência Estudantil',0,0);

/*Table structure for table `user_courses` */

DROP TABLE IF EXISTS `user_courses`;

CREATE TABLE `user_courses` (
  `user_id` int NOT NULL,
  `course_id` int NOT NULL,
  PRIMARY KEY (`user_id`,`course_id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `user_courses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_courses_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `user_courses` */

/* user_courses limpo — reatribuir via painel admin após configurar cursos do câmpus Garopaba */

/*Table structure for table `users` */

DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role_id` int NOT NULL,
  `receive_email` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `users` */

insert  into `users`(`id`,`name`,`email`,`password`,`role_id`,`receive_email`) values 
(6,'Eduardo Luis Gomes','eduardo.gomes@ifsc.edu.br','LDAP_AUTH',1,1),
(7,'Cléber Stange','cleber.stange@ifsc.edu.br','LDAP_AUTH',1,1),
(8,'Silvia Davet','silvia.davet@ifsc.edu.br','LDAP_AUTH',1,1),
(9,'Secretaria','secretaria.can@ifsc.edu.br','LDAP_AUTH',5,0),
(10,'Coordenação ADS','ads.tecnol.can@ifsc.edu.br','LDAP_AUTH',2,1),
(12,'Registro Acadêmico','registro.academico.can@ifsc.edu.br','',3,1),
(13,'Coordenadoria de Assuntos Estudantis','cae.can@ifsc.edu.br','',12,1),
(14,'Coordenadoria Pedagógica','pedagogico.can@ifsc.edu.br','',10,1),
(15,'Coordenadoria de Estágio','estagio.can@ifsc.edu.br','',11,1),
(16,'Biblioteca','biblioteca.canoinhas@ifsc.edu.br','',4,1),
(17,'Coordenadoria do Curso de Bacharelado em Agronomia','agronomia.can@ifsc.edu.br','',2,1),
(18,'Coordenadoria do Curso Superior de Tecnologia em Alimentos','coorcsta.can@ifsc.edu.br','',2,1),
(19,'Coordenadoria do Curso Técnico em Alimentos Integrado ao Ensino Médio','alimentos.tec.can@ifsc.edu.br','',2,1),
(20,'Coordenadoria do Curso Técnico em Edificações Integrado ao Ensino Médio','coorcte.can@ifsc.edu.br','',2,1),
(21,'Coordenadoria do Curso Técnico em Informática Integrado ao Ensino Médio','informatica.tec.can@ifsc.edu.br','',2,1),
(22,'Coordenadoria do Curso PROEJA em Agroecologia','agroecologia.proeja.can@ifsc.edu.br','',2,1),
(23,'Coordenadoria do Curso Técnico em Manutenção e Suporte em Informática:','msi.can@ifsc.edu.br','',2,1),
(25,'Coordenadoria do Núcleo de Acessibilidade Educacional - NAE','naed.can@ifsc.edu.br','',9,1),
(26,'Coordenadoria do Curso de Pós-graduação em Educação e Diversidade','franciscleyton.santos@ifsc.edu.br','',2,1),
(27,'Coordenadoria do Curso de Pós-graduação em Ciência e Tecnologia de Alimentos','pos.cta.can@ifsc.edu.br','',2,1),
(28,'Assistência Estudantil','assistenciaestudantil.can@ifsc.edu.br','',13,1),
(29,'Laura Campos de Borba','laura.borba@ifsc.edu.br','',2,1),
(30,'Andreia Hoepers','andreia.hoepers@ifsc.edu.br','',1,0),
(31,'SILVANA DE NAZARETH MESQUITA','silvana.mesquita@ifsc.edu.br','',1,0),
(32,'Partiu IF','partiuif.can@ifsc.edu.br','',2,1);

/*Table structure for table `workflow_steps` */

DROP TABLE IF EXISTS `workflow_steps`;

CREATE TABLE `workflow_steps` (
  `id` int NOT NULL AUTO_INCREMENT,
  `request_type_id` int NOT NULL,
  `role_id` int NOT NULL,
  `step_order` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `request_type_id` (`request_type_id`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `workflow_steps_ibfk_1` FOREIGN KEY (`request_type_id`) REFERENCES `request_types` (`id`),
  CONSTRAINT `workflow_steps_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `workflow_steps` */

insert  into `workflow_steps`(`id`,`request_type_id`,`role_id`,`step_order`) values 
(1,1,2,1),
(4,3,2,1),
(6,2,2,1),
(12,14,2,1),
(13,14,3,2),
(16,13,9,1),
(18,5,10,2),
(23,5,3,5),
(24,18,2,1),
(25,8,3,1),
(26,8,5,2),
(27,16,2,1),
(28,7,2,1),
(29,7,3,2),
(30,12,2,1),
(31,12,5,2),
(32,12,3,3),
(34,15,2,1),
(36,17,2,1),
(37,11,2,1),
(38,11,3,2),
(39,11,5,3),
(40,10,2,1),
(41,10,3,2),
(42,10,5,3),
(44,4,10,2),
(47,4,3,5),
(48,6,10,1),
(49,6,4,2),
(50,6,2,3),
(51,6,3,4),
(52,9,2,1),
(53,9,3,2),
(54,20,2,1),
(57,19,5,1),
(58,17,3,2),
(62,21,10,1),
(63,21,13,2),
(64,6,13,5),
(65,5,2,1),
(66,5,4,3),
(67,5,11,4),
(68,5,13,6),
(69,4,2,1),
(70,4,4,3),
(71,4,11,4),
(72,4,13,6);

/*Table structure for table `subjects` */

DROP TABLE IF EXISTS `subjects`;

CREATE TABLE `subjects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `course_id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `period` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `subjects_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `subjects` */

insert into `subjects`(`course_id`,`name`,`period`,`active`) values
-- Técnico em Administração (id=1) — 1º Ano
(1,'Fundamentos da Administração','1º Ano',1),(1,'Informática Aplicada','1º Ano',1),(1,'Orientação ao Ensino Técnico','1º Ano',1),(1,'Matemática I','1º Ano',1),(1,'Física I','1º Ano',1),(1,'Química I','1º Ano',1),(1,'Biologia I','1º Ano',1),(1,'História I','1º Ano',1),(1,'Geografia I','1º Ano',1),(1,'Filosofia I','1º Ano',1),(1,'Educação Física I','1º Ano',1),(1,'Língua Portuguesa e Literatura I','1º Ano',1),(1,'Espanhol I','1º Ano',1),
-- ADM — 2º Ano
(1,'Administração de Marketing','2º Ano',1),(1,'Administração da Qualidade','2º Ano',1),(1,'Administração da Produção e Logística','2º Ano',1),(1,'Matemática para Administradores','2º Ano',1),(1,'Matemática II','2º Ano',1),(1,'Física II','2º Ano',1),(1,'Química II','2º Ano',1),(1,'Biologia II','2º Ano',1),(1,'História II','2º Ano',1),(1,'Geografia II','2º Ano',1),(1,'Filosofia II','2º Ano',1),(1,'Educação Física II','2º Ano',1),(1,'Língua Portuguesa e Literatura II','2º Ano',1),(1,'Sociologia I','2º Ano',1),(1,'Artes I','2º Ano',1),
-- ADM — 3º Ano
(1,'Administração de Pessoas','3º Ano',1),(1,'Administração Financeira','3º Ano',1),(1,'Inglês','3º Ano',1),(1,'Empreendedorismo','3º Ano',1),(1,'Projeto Integrador II','3º Ano',1),(1,'Responsabilidade Socioambiental e Sustentabilidade','3º Ano',1),(1,'Matemática III','3º Ano',1),(1,'Física III','3º Ano',1),(1,'Química III','3º Ano',1),(1,'História III','3º Ano',1),(1,'Geografia III','3º Ano',1),(1,'Língua Portuguesa e Literatura III','3º Ano',1),(1,'Sociologia II','3º Ano',1),(1,'Artes II','3º Ano',1),
-- Técnico em Lazer (id=2) — 1º Ano
(2,'Arte I','1º Ano',1),(2,'Biologia I','1º Ano',1),(2,'Cultura, Identidade e Patrimônio','1º Ano',1),(2,'Educação Ambiental e Lazer','1º Ano',1),(2,'Educação Física I','1º Ano',1),(2,'Espanhol I','1º Ano',1),(2,'Filosofia I','1º Ano',1),(2,'Física I','1º Ano',1),(2,'Geografia I','1º Ano',1),(2,'História I','1º Ano',1),(2,'Inglês I','1º Ano',1),(2,'Lazer e Turismo: Inclusão Social e Interseccionalidades','1º Ano',1),(2,'Língua Portuguesa e Literatura I','1º Ano',1),(2,'Matemática I','1º Ano',1),(2,'Oficina de Integração de Ensino','1º Ano',1),(2,'Química I','1º Ano',1),(2,'Sociologia I','1º Ano',1),(2,'Sociedade, Trabalho e Tempo Livre','1º Ano',1),(2,'Teorias do Lazer','1º Ano',1),(2,'Teorias do Turismo e Hospitalidade','1º Ano',1),
-- Lazer — 2º Ano
(2,'Atividades Esportivas e Recreativas','2º Ano',1),(2,'Arte II','2º Ano',1),(2,'Espanhol Aplicado','2º Ano',1),(2,'Ecoturismo e Turismo de Base Comunitária','2º Ano',1),(2,'Educação Física II','2º Ano',1),(2,'Filosofia II','2º Ano',1),(2,'Física II','2º Ano',1),(2,'Geografia II','2º Ano',1),(2,'História II','2º Ano',1),(2,'Inglês II','2º Ano',1),(2,'Lazer e Turismo: Cultura Digital','2º Ano',1),(2,'Língua Portuguesa e Literatura II','2º Ano',1),(2,'Matemática II','2º Ano',1),(2,'Mobilidade e Planejamento Urbano em Lazer e Turismo','2º Ano',1),(2,'Oficina de Integração II (Pesquisa)','2º Ano',1),(2,'Políticas Públicas e Terceiro Setor do Lazer e Turismo','2º Ano',1),(2,'Química II','2º Ano',1),(2,'Sociologia II','2º Ano',1),(2,'Sociologia do Esporte','2º Ano',1),
-- Lazer — 3º Ano
(2,'Atividades de Aventura em Lazer','3º Ano',1),(2,'Biologia II','3º Ano',1),(2,'Empreendimentos Solidários','3º Ano',1),(2,'Espanhol II','3º Ano',1),(2,'Física III','3º Ano',1),(2,'Geografia III','3º Ano',1),(2,'História III','3º Ano',1),(2,'Inglês Aplicado ao Lazer','3º Ano',1),(2,'Introdução à Estética e Filosofia da Arte','3º Ano',1),(2,'Linguagens Artísticas e Lazer','3º Ano',1),(2,'Língua Portuguesa e Literatura III','3º Ano',1),(2,'Matemática III','3º Ano',1),(2,'Organização de Eventos','3º Ano',1),(2,'Oficina de Integração III (Extensão)','3º Ano',1),(2,'Química III','3º Ano',1),
-- Técnico em Informática (id=3) — 1º Ano
(3,'Algoritmos e Lógica de Programação','1º Ano',1),(3,'Biologia I','1º Ano',1),(3,'Educação Física I','1º Ano',1),(3,'Filosofia I','1º Ano',1),(3,'Física I','1º Ano',1),(3,'História I','1º Ano',1),(3,'Inglês','1º Ano',1),(3,'Introdução à Informática','1º Ano',1),(3,'Língua Portuguesa e Literatura I','1º Ano',1),(3,'Matemática I','1º Ano',1),(3,'Química I','1º Ano',1),(3,'Redes de Computadores','1º Ano',1),
-- Informática — 2º Ano
(3,'Arte I','2º Ano',1),(3,'Banco de Dados','2º Ano',1),(3,'Biologia II','2º Ano',1),(3,'Educação Física II','2º Ano',1),(3,'Filosofia II','2º Ano',1),(3,'Física II','2º Ano',1),(3,'Geografia II','2º Ano',1),(3,'Inglês Aplicado','2º Ano',1),(3,'Língua Portuguesa e Literatura II','2º Ano',1),(3,'Manutenção e Configuração de Computadores','2º Ano',1),(3,'Matemática II','2º Ano',1),(3,'Programação e Engenharia de Software','2º Ano',1),(3,'Sociologia I','2º Ano',1),
-- Informática — 3º Ano
(3,'Espanhol','3º Ano',1),(3,'Física III','3º Ano',1),(3,'Língua Portuguesa e Literatura III','3º Ano',1),(3,'Matemática III','3º Ano',1),(3,'Programação para Dispositivos Móveis','3º Ano',1),(3,'Programação Web','3º Ano',1),(3,'Projeto Integrador II','3º Ano',1),(3,'Química III','3º Ano',1),(3,'Responsabilidade Socioambiental e Sustentabilidade','3º Ano',1),(3,'Sociologia II','3º Ano',1),(3,'Tópicos Avançados em Informática','3º Ano',1),
-- Técnico em Biotecnologia (id=4)
(4,'Biologia Aplicada','1º Semestre',1),(4,'Gestão de Laboratório','1º Semestre',1),(4,'Linguagem e Comunicação','1º Semestre',1),(4,'Ambientação Profissional I','2º Semestre',1),(4,'Bioética','3º Semestre',1),(4,'Espanhol Aplicado','3º Semestre',1),(4,'Técnicas de Citologia','3º Semestre',1),(4,'Técnicas de Microbiologia','3º Semestre',1),(4,'Bioprospecção','4º Semestre',1),(4,'Biotecnologia Ambiental','4º Semestre',1),(4,'Bioestatística','5º Semestre',1),(4,'Projeto Integrador II','5º Semestre',1),(4,'Técnicas de Bioquímica','5º Semestre',1),(4,'Empreendedorismo','6º Semestre',1),
-- CST em Sistemas para Internet (id=7)
(7,'Algoritmos e Técnicas de Programação','1º Semestre',1),(7,'Fundamentos de Matemática','1º Semestre',1),(7,'Fundamentos de Sistemas para Internet','1º Semestre',1),(7,'Inglês Aplicado','1º Semestre',1),(7,'Banco de Dados I','2º Semestre',1),(7,'Banco de Dados II','3º Semestre',1),(7,'Engenharia de Software I','3º Semestre',1),(7,'Estrutura de Dados','3º Semestre',1),(7,'Interfaces e Experiência do Usuário','3º Semestre',1),(7,'Linguagem e Comunicação','3º Semestre',1),(7,'Programação para Internet I','3º Semestre',1),(7,'Engenharia de Software II','4º Semestre',1),(7,'Ciência de Dados I','5º Semestre',1),(7,'Empreendedorismo e Inovação','5º Semestre',1),(7,'Gerenciamento de Serviços para Internet','5º Semestre',1),(7,'Programação para Dispositivos Móveis I','5º Semestre',1),(7,'Ciência de Dados II','6º Semestre',1),
-- CST em Gestão Ambiental (id=8)
(8,'Biologia Aplicada','1º Semestre',1),(8,'Fundamentos de Administração','1º Semestre',1),(8,'Geociências Ambientais','1º Semestre',1),(8,'História da Gestão Ambiental','1º Semestre',1),(8,'Linguagem e Comunicação I','1º Semestre',1),(8,'Metodologia da Pesquisa','1º Semestre',1),(8,'Segurança e Saúde Ocupacional','1º Semestre',1),(8,'Educação Ambiental','2º Semestre',1),(8,'Geomática','2º Semestre',1),(8,'Ecologia Aplicada','3º Semestre',1),(8,'Estatística Aplicada','3º Semestre',1),(8,'Gestão Costeira','3º Semestre',1),(8,'Legislação Ambiental','3º Semestre',1),(8,'Topografia Aplicada ao Georeferenciamento','3º Semestre',1),(8,'Ferramentas de Gestão Ambiental','4º Semestre',1),(8,'Gestão de Áreas Protegidas','4º Semestre',1),(8,'Gestão de Qualidade','4º Semestre',1),(8,'Gestão de Recursos Hídricos','4º Semestre',1),(8,'Gestão de Resíduos Sólidos','4º Semestre',1),(8,'Avaliação de Impactos Ambientais','5º Semestre',1),(8,'Empreendedorismo','5º Semestre',1),(8,'Gestão de Projetos','5º Semestre',1),(8,'Licenciamento Ambiental','5º Semestre',1),(8,'Tópicos Especiais em Gestão Ambiental I','5º Semestre',1),
-- FIC em Inglês (id=15)
(15,'Inglês',NULL,1),
-- Técnico em Guia de Turismo (id=16)
(16,'Espanhol Aplicado ao Guia de Turismo I','1º Semestre',1),(16,'Fundamentos do Turismo','1º Semestre',1),(16,'Geografia de Santa Catarina I','1º Semestre',1),(16,'História de Santa Catarina no Contexto do Brasil e do Mundo I','1º Semestre',1),(16,'Linguagem e Comunicação I','1º Semestre',1),(16,'Patrimônio Cultural','1º Semestre',1),(16,'Técnica Profissional 1','1º Semestre',1),(16,'História da Arte Ocidental e Brasileira','1º Semestre',1),(16,'Educação e Responsabilidade Ambiental','2º Semestre',1),(16,'Ecossistemas Regionais de Santa Catarina','2º Semestre',1),(16,'Espanhol Aplicado ao Guia de Turismo II','2º Semestre',1),(16,'Geografia de Santa Catarina II','2º Semestre',1),(16,'História de Santa Catarina no Contexto do Brasil e do Mundo II','2º Semestre',1),
-- PROEJA em Serviços de Restauração e Bar (id=17)
(17,'Armazenagem e Estocagem',NULL,1),(17,'Espanhol Aplicado',NULL,1),(17,'Filosofia',NULL,1),(17,'Física',NULL,1),(17,'Geografia',NULL,1),(17,'Linguagem e Comunicação',NULL,1),(17,'Matemática',NULL,1),(17,'Química',NULL,1),(17,'Sociologia',NULL,1);

/*Table structure for table `teachers` */

DROP TABLE IF EXISTS `teachers`;

CREATE TABLE `teachers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `teachers` */

insert into `teachers`(`name`) values
('ADRIANA MURARA SILVA'),
('ALBERTO FELIPE FRIDERICHS BARROS'),
('ALINE BORBA OLIVEIRA'),
('ANDRÉA CRISTIANE MACHADO MORAES'),
('ANDRE LUIZ SILVA DE MORAES'),
('ANTONIO MIGUEL FAUSTINI ZARTH'),
('CARMINE INES ACKER'),
('CLÁUDIA TELES DA SILVA'),
('CRISTIANE OLIVEIRA DA SILVA'),
('DIEGO MARLON DE CASTRO'),
('EDUARDO BATISTA VON BOROWSKI'),
('EDUARDO CARGNIN FERREIRA'),
('ELISA SERENA GANDOLFO MARTINS'),
('FABIANA BESEN SANTOS'),
('FABIANA DE AGAPITO KANGERSKI'),
('FELIX LOZANO MEDINA'),
('FERNANDO SOARES DE JESUS'),
('FRANCIELE ROCHA DE OLIVEIRA'),
('IGNACIO KIRHIAKO SEPULVEDA RAMIREZ'),
('JACIARA ZARPELLON MAZO'),
('JEAN MARCEL DE ALMEIDA ESPINOZA'),
('JOÃO EDUARDO NAVACHI DA SILVEIRA'),
('JOAO HENRIQUE QUOOS'),
('JOSE RODRIGO BARTH ADAMS'),
('JOSIMARA MARTINS KRAUSEN'),
('JULIANI BRIGNOL WALOTEK'),
('JULIANO DA CUNHA GOMES'),
('JULIO CEZAR BRAGAGLIA'),
('KARYNA LANDRA MARCELINO'),
('LUANA DE GUSMAO SILVEIRA'),
('LUANA DOS SANTOS DE SOUZA'),
('LUANA WRITZL'),
('LUCAS DE CASTRO'),
('LUIZ ANTONIO SCHALATA PACHECO'),
('MARCELO LEANDRO DOS SANTOS'),
('MARIANA REIS LEAL FERNANDES'),
('MARIA ROSA DA SILVA COSTA'),
('MARILIA RIBAS MACHADO'),
('MICHELINE SARTORI'),
('MICHELSCH JOAO DA SILVA'),
('NAUBER GAVSKI DA SILVA'),
('PAULA MAYARA ZUANAZZI'),
('RENATA WALESKA DE SOUSA PIMENTA'),
('RENATO DA SILVA ROSA RODRIGUES'),
('ROSANE SCHENKEL DE AQUINO'),
('RYAN MARCOS FRAGNANI CARDOSO'),
('SABRINA MORO VILLELA PACHECO'),
('SANDRA BEATRIZ KOELLING'),
('SCHIRLEI RUSSI VON DENTZ'),
('SIBELI PAULON FERRONATO'),
('TELMA PIRES PACHECO AMORIM'),
('THAIANA PEREIRA DOS ANJOS REIS'),
('THIAGO LIPINSKI PAES'),
('THIAGO WALTRIK');

/* Migrations: alter existing tables and insert new request types */

ALTER TABLE `requests` ADD COLUMN IF NOT EXISTS `extra_fields` JSON NULL AFTER `course_units`;

ALTER TABLE `request_types` ADD COLUMN IF NOT EXISTS `featured` tinyint(1) DEFAULT '0' AFTER `active`;

UPDATE `request_types` SET `name` = 'Plano de Estudo Diferenciado (PEDi)' WHERE `id` = 15;
UPDATE `request_types` SET `active` = 0 WHERE `id` = 16;
UPDATE `request_types` SET `featured` = 1 WHERE `id` IN (1,2,4,14,20,22);

INSERT IGNORE INTO `request_types` (`id`,`name`,`active`,`featured`,`information`,`attention`) VALUES
(22,'Revisão de Avaliação',1,1,
  'Será permitida a revisão de atividade de avaliação quando o estudante discordar da correção realizada pelo professor.',
  NULL),
(25,'Cancelamento de Matrícula em Unidade Curricular',1,0,
  'Apenas para cursos em regime de matrícula por unidade curricular. O cancelamento poderá ocorrer uma única vez para cada UC.',
  'Deve ser mantida matrícula em pelo menos uma unidade curricular.');

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
