-- Corrige as UCs do PROEJA em Serviços de Restauração e Bar (course_id = 17)
-- As 9 UCs genéricas originais (IDs 202–210) não tinham period e estavam incorretas.
-- Nenhum requerimento as referenciava — deleção segura.

DELETE FROM subjects WHERE course_id = 17;

INSERT INTO subjects (course_id, name, period, active) VALUES
-- 1º Nível (8 UCs)
(17, 'Arte 1', '1º Nível', 1),
(17, 'Espanhol', '1º Nível', 1),
(17, 'Filosofia 1', '1º Nível', 1),
(17, 'Fundamentos do Turismo', '1º Nível', 1),
(17, 'História 1', '1º Nível', 1),
(17, 'Informática e Autonomia Digital', '1º Nível', 1),
(17, 'Língua Portuguesa e Literatura 1', '1º Nível', 1),
(17, 'Relações Interpessoais no Mundo do Trabalho', '1º Nível', 1),
-- 2º Nível (8 UCs)
(17, 'Arte 2', '2º Nível', 1),
(17, 'Biologia 1', '2º Nível', 1),
(17, 'Geografia 1', '2º Nível', 1),
(17, 'História e Cultura da Gastronomia', '2º Nível', 1),
(17, 'Higiene e Manipulação de Alimentos', '2º Nível', 1),
(17, 'Língua Portuguesa e Literatura 2', '2º Nível', 1),
(17, 'Matemática 1', '2º Nível', 1),
(17, 'Sociologia 1', '2º Nível', 1),
-- 3º Nível (8 UCs)
(17, 'Biologia 2', '3º Nível', 1),
(17, 'História 2', '3º Nível', 1),
(17, 'Inglês', '3º Nível', 1),
(17, 'Língua Portuguesa e Literatura 3', '3º Nível', 1),
(17, 'Matemática 2', '3º Nível', 1),
(17, 'Responsabilidade Socioambiental', '3º Nível', 1),
(17, 'Serviços de Alimentos e Bebidas', '3º Nível', 1),
(17, 'Sociologia 2', '3º Nível', 1),
-- 4º Nível (9 UCs)
(17, 'Educação Física 1', '4º Nível', 1),
(17, 'Espanhol Aplicado', '4º Nível', 1),
(17, 'Filosofia 2', '4º Nível', 1),
(17, 'Física 1', '4º Nível', 1),
(17, 'Geografia 2', '4º Nível', 1),
(17, 'Linguagem e Comunicação', '4º Nível', 1),
(17, 'Organização de Eventos', '4º Nível', 1),
(17, 'Química 1', '4º Nível', 1),
(17, 'Sociedade e Trabalho', '4º Nível', 1),
-- 5º Nível (8 UCs)
(17, 'Biologia 3', '5º Nível', 1),
(17, 'Controle Operacional Administrativo', '5º Nível', 1),
(17, 'Comportamento Empreendedor', '5º Nível', 1),
(17, 'Educação Física 2', '5º Nível', 1),
(17, 'Filosofia 3', '5º Nível', 1),
(17, 'Física 2', '5º Nível', 1),
(17, 'História 3', '5º Nível', 1),
(17, 'Química 2', '5º Nível', 1),
-- 6º Nível (8 UCs)
(17, 'Arte 3', '6º Nível', 1),
(17, 'Formação de Preço e Elaboração de Cardápio', '6º Nível', 1),
(17, 'Geografia 3', '6º Nível', 1),
(17, 'Inglês Aplicado', '6º Nível', 1),
(17, 'Matemática 3', '6º Nível', 1),
(17, 'Projeto Integrador', '6º Nível', 1),
(17, 'Química 3', '6º Nível', 1),
(17, 'Sociologia 3', '6º Nível', 1);
