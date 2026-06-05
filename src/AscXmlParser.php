<?php
/**
 * Parser do formato XML do aSc Timetables (asctt2012).
 * Extrai as alocações de aulas usando apenas simplexml_load_file() nativo.
 */

class AscXmlParser {

    const SLOT_MAP = [
        1 => 'manha12',
        2 => 'manha34',
        3 => 'tarde12',
        4 => 'tarde34',
        5 => 'noite12',
        6 => 'noite34',
        7 => 'noite123',
    ];

    const TIME_SLOT_LABELS = [
        'manha12'  => 'Manhã 12 (8:00–9:50)',
        'manha34'  => 'Manhã 34 (10:10–12:00)',
        'tarde12'  => 'Tarde 12 (13:30–15:20)',
        'tarde34'  => 'Tarde 34 (15:40–17:30)',
        'noite12'  => 'Noite 12 (18:00–19:50)',
        'noite34'  => 'Noite 34 (20:10–22:00)',
        'noite123' => 'Noite 123 (19:00–22:00)',
    ];

    const DAY_LABELS = [
        1 => 'Seg',
        2 => 'Ter',
        3 => 'Qua',
        4 => 'Qui',
        5 => 'Sex',
    ];

    const SLOT_HOURS = [
        'manha12'  => 2,
        'manha34'  => 2,
        'tarde12'  => 2,
        'tarde34'  => 2,
        'noite12'  => 2,
        'noite34'  => 2,
        'noite123' => 3,
    ];

    public static function termsLabel(string $terms): string {
        $ones = substr_count($terms, '1');
        if ($ones === 4)          return 'Semestre completo';
        if ($terms === '1100')    return 'D1+D2 (sem. 1–10)';
        if ($terms === '0011')    return 'D3+D4 (sem. 11–20)';
        if ($terms === '1000')    return 'D1 (sem. 1–5)';
        if ($terms === '0100')    return 'D2 (sem. 6–10)';
        if ($terms === '0010')    return 'D3 (sem. 11–15)';
        if ($terms === '0001')    return 'D4 (sem. 16–20)';
        $parts  = [];
        $labels = ['D1', 'D2', 'D3', 'D4'];
        for ($i = 0; $i < 4; $i++) {
            if (($terms[$i] ?? '0') === '1') $parts[] = $labels[$i];
        }
        return implode('+', $parts) . ' (' . ($ones * 5) . ' sem.)';
    }

    /**
     * Parseia um arquivo XML do aSc Timetables e retorna array de entradas de schedule_slots.
     * Cada entrada: [teacher_name, class_group, subject_name, day_of_week, time_slot]
     */
    public function parse(string $xmlPath): array {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($xmlPath);
        if (!$xml) return [];

        // Indexar teachers
        $teachers = [];
        foreach ($xml->teachers->teacher ?? [] as $t) {
            $id = (string)$t['id'];
            $teachers[$id] = trim((string)$t['name']);
        }

        // Indexar turmas pelo campo short (ex: "INF 3 - 2025")
        $classes = [];
        foreach ($xml->classes->class ?? [] as $c) {
            $id = (string)$c['id'];
            $classes[$id] = trim((string)$c['short']);
        }

        // Indexar disciplinas
        $subjects = [];
        foreach ($xml->subjects->subject ?? [] as $s) {
            $id = (string)$s['id'];
            $subjects[$id] = trim((string)$s['name']);
        }

        // Indexar aulas (lessons)
        $lessons = [];
        foreach ($xml->lessons->lesson ?? [] as $l) {
            $id = (string)$l['id'];
            $lessons[$id] = [
                'teacherids' => array_filter(explode(',', (string)$l['teacherids'])),
                'classids'   => array_filter(explode(',', (string)$l['classids'])),
                'subjectid'  => (string)$l['subjectid'],
            ];
        }

        // Processar cards (alocações)
        $entries = [];
        foreach ($xml->cards->card ?? [] as $card) {
            $lessonId = (string)$card['lessonid'];
            $period   = (int)$card['period'];
            $daysStr  = (string)$card['days'];

            if (!isset($lessons[$lessonId], self::SLOT_MAP[$period])) continue;

            $lesson   = $lessons[$lessonId];
            $timeSlot = self::SLOT_MAP[$period];

            // Pega a primeira turma da aula (casos de múltiplas turmas são raros)
            $classId   = $lesson['classids'][0] ?? '';
            $className = $classes[$classId] ?? '';
            if (!$className) continue;

            $subjectName = $subjects[$lesson['subjectid']] ?? null;
            $days        = $this->expandDays($daysStr);

            foreach ($lesson['teacherids'] as $tid) {
                $teacherName = $teachers[trim($tid)] ?? '';
                if (!$teacherName) continue;

                $terms = $this->resolveTerms((string)($card['terms'] ?? '1111'));
                foreach ($days as $dow) {
                    $entries[] = [
                        'teacher_name' => $teacherName,
                        'class_group'  => $className,
                        'subject_name' => $subjectName,
                        'day_of_week'  => $dow,
                        'time_slot'    => $timeSlot,
                        'terms'        => $terms,
                    ];
                }
            }
        }

        return $entries;
    }

    /**
     * Resolve terms multi-valor (ex: "1000,0100,0010,0001") em um único bitmask via OR.
     * Sempre retorna uma string de exatamente 4 chars (ex: "1111").
     */
    private function resolveTerms(string $raw): string {
        $result = [0, 0, 0, 0];
        foreach (explode(',', $raw) as $t) {
            $t = trim($t);
            for ($i = 0; $i < 4; $i++) {
                if (($t[$i] ?? '0') === '1') $result[$i] = 1;
            }
        }
        $resolved = implode('', $result);
        return $resolved ?: '1111';
    }

    /**
     * Expande o bitmask de dias (ex: "00010" → [4]) em array de day_of_week (1=Seg…5=Sex).
     * Suporta múltiplos bitmasks separados por vírgula.
     */
    private function expandDays(string $bits): array {
        $days = [];
        foreach (explode(',', $bits) as $bitmap) {
            $bitmap = trim($bitmap);
            for ($i = 0, $len = strlen($bitmap); $i < 5 && $i < $len; $i++) {
                if ($bitmap[$i] === '1') {
                    $day = $i + 1;
                    if (!in_array($day, $days, true)) {
                        $days[] = $day;
                    }
                }
            }
        }
        return $days;
    }
}
