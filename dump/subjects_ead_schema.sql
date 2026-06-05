-- UCs EAD pontuam 0,5× no Justiceiro do Tempo (aulas presenciais esporádicas)
ALTER TABLE subjects
    ADD COLUMN IF NOT EXISTS is_ead TINYINT(1) NOT NULL DEFAULT 0 AFTER active;
