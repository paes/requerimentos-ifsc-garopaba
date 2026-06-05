-- Corrigir is_ead de UCs existentes no CST SI
UPDATE subjects SET is_ead = 1 WHERE id = 155; -- Interfaces e Experiência do Usuário
UPDATE subjects SET is_ead = 1 WHERE id = 163; -- Ciência de Dados II

-- Inserir UCs faltantes do CST em Sistemas para Internet (course_id=7)
INSERT INTO subjects (course_id, name, period, active, is_ead) VALUES
-- 2º Semestre
(7, 'Iniciação Científica e Extensionista', '2º Semestre', 1, 0),
(7, 'Programação Orientada a Objetos',      '2º Semestre', 1, 0),
(7, 'Sociedade e Trabalho',                 '2º Semestre', 1, 1),
(7, 'Sistemas Operacionais',                '2º Semestre', 1, 0),
-- 4º Semestre
(7, 'Desenvolvimento de Jogos',              '4º Semestre', 1, 1),
(7, 'Programação para Internet II',          '4º Semestre', 1, 0),
(7, 'Redes de Computadores',                 '4º Semestre', 1, 0),
(7, 'Sistemas de Gerenciamento de Conteúdo', '4º Semestre', 1, 1),
-- 5º Semestre
(7, 'Atividades de Extensão',                '5º Semestre', 1, 0),
(7, 'Espanhol Aplicado',                     '5º Semestre', 1, 1),
(7, 'Libras',                                '5º Semestre', 1, 1),
(7, 'Trabalho de Conclusão de Curso',        '5º Semestre', 1, 0),
-- 6º Semestre
(7, 'Ética, Sociedade e Informática',                   '6º Semestre', 1, 0),
(7, 'Gestão e Segurança de Tecnologia da Informação',   '6º Semestre', 1, 0),
(7, 'Programação para Dispositivos Móveis II',          '6º Semestre', 1, 0),
(7, 'Tópicos Especiais em Sistemas para Internet',      '6º Semestre', 1, 0);
