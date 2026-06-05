-- Adicionar abreviação e flag EAD à tabela de cursos
-- Abreviação: prefixo usado em class_group (ex: "SI", "ADM", "PROEJA")
-- is_ead: cursos EAD pontuam 0,5× no Justiceiro do Tempo (aulas presenciais esporádicas)
ALTER TABLE courses
    ADD COLUMN IF NOT EXISTS abbreviation VARCHAR(20) NULL AFTER name,
    ADD COLUMN IF NOT EXISTS is_ead TINYINT(1) NOT NULL DEFAULT 0 AFTER abbreviation;
