<?php
require_once 'guard.php';
require_once '../../src/AscXmlParser.php';

header('Content-Type: application/json; charset=utf-8');

$classGroup  = trim($_GET['class_group'] ?? '');
$semester    = trim($_GET['semester'] ?? '');
$rawSlots    = $_GET['time_slots'] ?? [];
$myNameLower = mb_strtolower(trim($teacherName ?? ''));

$empty = json_encode(['busy' => [], 'free' => []], JSON_UNESCAPED_UNICODE);

if (!$classGroup || !$semester || empty($rawSlots)) {
    echo $empty; exit;
}

$requestedSlots = array_values(array_filter(array_map('trim', (array)$rawSlots)));
if (empty($requestedSlots)) {
    echo $empty; exit;
}

// 1. Todos os docentes que ensinam essa turma (exceto o requerente)
$stmt1 = $conn->prepare("
    SELECT DISTINCT ss.teacher_name, ss.teacher_id, t.email AS teacher_email
    FROM schedule_slots ss
    LEFT JOIN teachers t ON t.id = ss.teacher_id
    WHERE ss.semester = ? AND ss.class_group = ?
      AND LOWER(ss.teacher_name) != ?
    ORDER BY ss.teacher_name
");
$stmt1->execute([$semester, $classGroup, $myNameLower]);
$classTeachersRaw = $stmt1->fetchAll(PDO::FETCH_ASSOC);

if (empty($classTeachersRaw)) {
    echo $empty; exit;
}

// Mapa: nome_lower → {teacher_name, teacher_id, teacher_email}
$teacherMap = [];
foreach ($classTeachersRaw as $r) {
    $teacherMap[mb_strtolower($r['teacher_name'])] = $r;
}
$allNamesLower = array_keys($teacherMap);

// 2. Quais estão ocupados nos horários solicitados? (qualquer aula, não só essa turma)
$slotPH = implode(',', array_fill(0, count($requestedSlots), '?'));
$namePH = implode(',', array_fill(0, count($allNamesLower), '?'));
$stmt2  = $conn->prepare("
    SELECT DISTINCT LOWER(teacher_name) AS tname
    FROM schedule_slots
    WHERE semester = ?
      AND time_slot IN ($slotPH)
      AND LOWER(teacher_name) IN ($namePH)
");
$stmt2->execute(array_merge([$semester], $requestedSlots, $allNamesLower));
$busyNamesLower = $stmt2->fetchAll(PDO::FETCH_COLUMN);

// free = class_teachers - busy
$freeNamesLower = array_values(array_diff($allNamesLower, $busyNamesLower));

// 3. Horários do requerente nessa turma (base para calcular devolutiva)
$stmt3 = $conn->prepare("
    SELECT DISTINCT time_slot
    FROM schedule_slots
    WHERE semester = ? AND class_group = ? AND LOWER(teacher_name) = ?
");
$stmt3->execute([$semester, $classGroup, $myNameLower]);
$mySlots = $stmt3->fetchAll(PDO::FETCH_COLUMN);

// 4. Free teachers que ensinam essa turma em slots fora dos do requerente (devolutiva facilitada)
$devolutivaInfo = []; // nome_lower → string descritiva dos slots
if (!empty($freeNamesLower)) {
    $freeNH       = implode(',', array_fill(0, count($freeNamesLower), '?'));
    $mySlotCond   = '';
    $mySlotParams = [];
    if (!empty($mySlots)) {
        $mySlotCond   = 'AND time_slot NOT IN (' . implode(',', array_fill(0, count($mySlots), '?')) . ')';
        $mySlotParams = $mySlots;
    }
    $stmt4 = $conn->prepare("
        SELECT teacher_name, time_slot, day_of_week
        FROM schedule_slots
        WHERE semester = ? AND class_group = ?
          AND LOWER(teacher_name) IN ($freeNH)
          $mySlotCond
        ORDER BY teacher_name, time_slot, day_of_week
    ");
    $stmt4->execute(array_merge([$semester, $classGroup], $freeNamesLower, $mySlotParams));
    $devRows = $stmt4->fetchAll(PDO::FETCH_ASSOC);

    // Agrupar: nome_lower → time_slot → [dias]
    $grouped = [];
    foreach ($devRows as $r) {
        $nl = mb_strtolower($r['teacher_name']);
        $grouped[$nl][$r['time_slot']][] = AscXmlParser::DAY_LABELS[(int)$r['day_of_week']] ?? '';
    }
    foreach ($grouped as $nl => $slotDays) {
        $parts = [];
        foreach ($slotDays as $ts => $days) {
            $label   = preg_replace('/\s*\([^)]+\)/', '', AscXmlParser::TIME_SLOT_LABELS[$ts] ?? $ts);
            $parts[] = $label . ' (' . implode(', ', array_unique($days)) . ')';
        }
        $devolutivaInfo[$nl] = implode('; ', $parts);
    }
}

// Montar resposta
$busy = [];
foreach ($busyNamesLower as $nl) {
    if (isset($teacherMap[$nl])) {
        $busy[] = [
            'teacher_name'  => $teacherMap[$nl]['teacher_name'],
            'teacher_email' => $teacherMap[$nl]['teacher_email'] ?? '',
        ];
    }
}
usort($busy, fn($a, $b) => strcmp($a['teacher_name'], $b['teacher_name']));

$free = [];
foreach ($freeNamesLower as $nl) {
    $hasDev = isset($devolutivaInfo[$nl]);
    $free[] = [
        'teacher_name'    => $teacherMap[$nl]['teacher_name'],
        'teacher_id'      => $teacherMap[$nl]['teacher_id'],
        'teacher_email'   => $teacherMap[$nl]['teacher_email'] ?? '',
        'devolutiva'      => $hasDev,
        'devolutiva_info' => $devolutivaInfo[$nl] ?? '',
    ];
}
// Devolutiva primeiro, depois alfabético
usort($free, fn($a, $b) => $b['devolutiva'] <=> $a['devolutiva'] ?: strcmp($a['teacher_name'], $b['teacher_name']));

echo json_encode(['busy' => $busy, 'free' => $free], JSON_UNESCAPED_UNICODE);
