<?php
require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../src/Auth.php';
require_once '../../src/AscXmlParser.php';

Auth::check();
$user = Auth::user();
$db   = new Database();
$conn = $db->getConnection();

$stmtRole = $conn->prepare("SELECT is_sysadmin FROM roles WHERE id = :id");
$stmtRole->execute([':id' => $user['user_role']]);
$isSysAdmin = (bool)$stmtRole->fetchColumn();

$allowedRoles = [6, 14]; // DEPE, Assessoria DEPE
if (!$isSysAdmin && !in_array((int)$user['user_role'], $allowedRoles)) {
    header('Location: dashboard.php');
    exit;
}

// ─── Helpers ────────────────────────────────────────────────────────────────

function badSlotWeights(): array {
    return [
        '1_manha12' => 3.0,  '1_manha34' => 2.5,
        '1_tarde12' => 2.0,  '1_tarde34' => 1.5,
        '1_noite12' => 1.0,  '1_noite34' => 1.0,  '1_noite123' => 1.0,
        '5_manha12' => 1.0,  '5_manha34' => 1.0,
        '5_tarde12' => 1.0,  '5_tarde34' => 2.5,
        '5_noite12' => 3.0,  '5_noite34' => 3.0,  '5_noite123' => 3.0,
    ];
}

function badSlotLabels(): array {
    return [
        '1_manha12'  => 'Seg Manhã 12',  '1_manha34'  => 'Seg Manhã 34',
        '1_tarde12'  => 'Seg Tarde 12',  '1_tarde34'  => 'Seg Tarde 34',
        '1_noite12'  => 'Seg Noite 12',  '1_noite34'  => 'Seg Noite 34',
        '1_noite123' => 'Seg Noite 123',
        '5_manha12'  => 'Sex Manhã 12',  '5_manha34'  => 'Sex Manhã 34',
        '5_tarde12'  => 'Sex Tarde 12',  '5_tarde34'  => 'Sex Tarde 34',
        '5_noite12'  => 'Sex Noite 12',  '5_noite34'  => 'Sex Noite 34',
        '5_noite123' => 'Sex Noite 123',
    ];
}

function coordScores(): array {
    return [
        'Direção do Câmpus'                   => 10.0,
        'Chefe do DEPE'                        => 10.0,
        'Assessoria DEPE'                      =>  5.0,
        'Coordenadoria Pedagógica'             =>  5.0,
        'Secretaria Acadêmica'                 =>  2.0,
        'Coordenadoria de Extensão'            =>  2.0,
        'Coordenadoria de Pesquisa e Inovação' =>  2.0,
        'NEAD'                                 =>  2.0,
        'NAE'                                  =>  2.0,
        'Biblioteca'                           =>  2.0,
        'CTIC'                                 =>  2.0,
    ];
}

// Groups for ranking sub-columns
function slotGroups(): array {
    return [
        'seg_m' => ['1_manha12', '1_manha34'],
        'seg_t' => ['1_tarde12', '1_tarde34'],
        'seg_n' => ['1_noite12', '1_noite34', '1_noite123'],
        'sex_m' => ['5_manha12', '5_manha34'],
        'sex_t' => ['5_tarde12', '5_tarde34'],
        'sex_n' => ['5_noite12', '5_noite34', '5_noite123'],
    ];
}


function computeSemesterScores(PDO $conn, string $semester): array {
    $bsw       = badSlotWeights();
    $groupDefs = slotGroups();

    // All teachers this semester (schedule_slots + coordinators — no availability table)
    $stmtT = $conn->prepare("
        SELECT DISTINCT teacher_name FROM (
            SELECT teacher_name FROM schedule_slots WHERE semester = :s1
            UNION SELECT teacher_name FROM course_coordinators WHERE semester = :s2
        ) t ORDER BY teacher_name
    ");
    $stmtT->execute([':s1' => $semester, ':s2' => $semester]);
    $teachers = $stmtT->fetchAll(PDO::FETCH_COLUMN);

    // All allocations with terms factor and EAD factor
    $stmtA = $conn->prepare("
        SELECT
            UPPER(ss.teacher_name) AS tk,
            ss.day_of_week         AS dow,
            ss.time_slot           AS slot,
            ss.terms,
            CASE WHEN COALESCE(sub.is_ead, 0) = 1
                   OR ss.subject_name LIKE '% (EAD)'
                 THEN 1 ELSE 0 END AS is_ead
        FROM schedule_slots ss
        LEFT JOIN courses c_slot
            ON c_slot.abbreviation IS NOT NULL
           AND UPPER(ss.class_group) LIKE CONCAT(UPPER(c_slot.abbreviation), ' %')
        LEFT JOIN subjects sub
            ON UPPER(sub.name COLLATE utf8mb4_unicode_ci) = UPPER(REPLACE(ss.subject_name, ' (EAD)', ''))
           AND sub.course_id = c_slot.id
        WHERE ss.semester = :s
    ");
    $stmtA->execute([':s' => $semester]);

    $allocByT = []; // [tk][slotKey] = cumulative contribution (float)
    $baseByT  = []; // [tk] = baseline score from normal slots (Tue/Wed/Thu)

    foreach ($stmtA->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $tk          = $row['tk'];
        $slotKey     = $row['dow'] . '_' . $row['slot'];
        $termsFactor = substr_count($row['terms'], '1') / 4.0;
        $eadFactor   = $row['is_ead'] ? 0.5 : 1.0;

        if (isset($bsw[$slotKey])) {
            $contribution = $bsw[$slotKey] * $termsFactor * $eadFactor;
            $allocByT[$tk][$slotKey] = ($allocByT[$tk][$slotKey] ?? 0.0) + $contribution;
        } elseif (!in_array((int)$row['dow'], [1, 5])) {
            $baseByT[$tk] = ($baseByT[$tk] ?? 0.0) + 0.05 * $termsFactor * $eadFactor;
        }
    }

    // Coordination scores per teacher
    $campusScoreMap    = coordScores();
    $integratedAbbrevs = ['INF', 'ADM', 'LAZ'];
    $stmtC = $conn->prepare("
        SELECT UPPER(cc.teacher_name) AS tk, cc.course_id, cc.role_name, c.abbreviation
        FROM course_coordinators cc
        LEFT JOIN courses c ON c.id = cc.course_id
        WHERE cc.semester = :s AND cc.teacher_name IS NOT NULL AND cc.teacher_name <> ''
    ");
    $stmtC->execute([':s' => $semester]);
    $coordScoreByT = [];
    foreach ($stmtC->fetchAll(PDO::FETCH_ASSOC) as $cr) {
        if ($cr['course_id']) {
            $pts = in_array($cr['abbreviation'], $integratedAbbrevs) ? 5.0 : 2.0;
        } else {
            $pts = $campusScoreMap[$cr['role_name']] ?? 2.0;
        }
        $ck = accentFold($cr['tk']);
        $coordScoreByT[$ck] = ($coordScoreByT[$ck] ?? 0.0) + $pts;
    }

    $scores = [];
    foreach ($teachers as $tName) {
        $tKey          = mb_strtoupper($tName);
        $badAllocScore = 0.0;
        $detail        = [];
        $groupScores   = array_fill_keys(array_keys($groupDefs), 0.0);

        foreach ($bsw as $slotKey => $weight) {
            if (isset($allocByT[$tKey][$slotKey])) {
                $c = $allocByT[$tKey][$slotKey];
                $badAllocScore += $c;
                $detail[$slotKey] = 'alloc';
                foreach ($groupDefs as $grp => $keys) {
                    if (in_array($slotKey, $keys)) { $groupScores[$grp] += $c; break; }
                }
            } else {
                $detail[$slotKey] = 'free';
            }
        }

        $baseScore  = $baseByT[$tKey] ?? 0.0;
        $coordScore = $coordScoreByT[accentFold($tKey)] ?? 0.0;
        $totalScore = $badAllocScore + $baseScore;

        $scores[$tName] = [
            'badAllocScore' => $badAllocScore,
            'baseScore'     => $baseScore,
            'coordScore'    => round($coordScore, 1),
            'score'         => round($totalScore, 2),
            'scoreTotal'    => round($totalScore + $coordScore, 1),
            'groupScores'   => $groupScores,
            'detail'        => $detail,
        ];
    }

    uasort($scores, fn($a, $b) => $b['scoreTotal'] <=> $a['scoreTotal']);
    return $scores;
}

function accentFold(string $s): string {
    $r = iconv('UTF-8', 'ASCII//TRANSLIT', $s);
    return ($r !== false && $r !== '') ? $r : $s;
}

function gridCellClass(int $dow, string $slot, bool $hasAlloc): string {
    $isBad = isset(badSlotWeights()[$dow . '_' . $slot]);
    if ($hasAlloc) return $isBad ? 'bg-orange-300 text-orange-900' : 'bg-blue-300 text-blue-900';
    return 'bg-gray-100 text-gray-400';
}

// ─── POST handlers ───────────────────────────────────────────────────────────

$error   = '';
$success = '';
$tab     = trim($_GET['tab'] ?? 'ranking');

// ─── DB queries ──────────────────────────────────────────────────────────────

$stmtSems = $conn->prepare("SELECT DISTINCT semester FROM schedule_slots ORDER BY semester DESC");
$stmtSems->execute();
$xmlSemesters = $stmtSems->fetchAll(PDO::FETCH_COLUMN);


// Ranking tab
$rankSem    = trim($_GET['rank_sem'] ?? ($xmlSemesters[0] ?? ''));
$rankScores = [];
$rankAllocData = [];
$rankCoords    = [];
$rankSort = $_GET['rank_sort'] ?? 'total';
if ($rankSem) {
    $rankScores = computeSemesterScores($conn, $rankSem);
    switch ($rankSort) {
        case 'name':  uksort($rankScores, fn($a, $b) => mb_strtolower($a) <=> mb_strtolower($b)); break;
        case 'score': uasort($rankScores, fn($a, $b) => $b['score'] <=> $a['score']); break;
        case 'coord': uasort($rankScores, fn($a, $b) => $b['coordScore'] <=> $a['coordScore']); break;
    }

    $stmtRS = $conn->prepare("SELECT UPPER(teacher_name) AS tk, day_of_week, time_slot FROM schedule_slots WHERE semester = :s");
    $stmtRS->execute([':s' => $rankSem]);
    foreach ($stmtRS->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $rankAllocData[$r['tk']][$r['day_of_week']][$r['time_slot']] = true;
    }

    $stmtCo = $conn->prepare("SELECT UPPER(teacher_name) AS tk FROM course_coordinators WHERE semester = :s");
    $stmtCo->execute([':s' => $rankSem]);
    $rankCoords = array_flip($stmtCo->fetchAll(PDO::FETCH_COLUMN));
}

// Show/hide inactive teachers toggle
$showInactive  = (bool)($_GET['show_inactive'] ?? 0);
$rankSemRef    = $rankSem ?: ($xmlSemesters[0] ?? '');
$inactiveInRankSem = [];
if ($rankSemRef) {
    $stmtInRank = $conn->prepare("SELECT teacher_name FROM teacher_inactive_semesters WHERE semester = :s");
    $stmtInRank->execute([':s' => $rankSemRef]);
    foreach ($stmtInRank->fetchAll(PDO::FETCH_COLUMN) as $n) {
        $inactiveInRankSem[accentFold(mb_strtoupper($n))] = true;
    }
}
if (!$showInactive && !empty($inactiveInRankSem)) {
    $rankScores = array_filter($rankScores, fn($tName) => !isset($inactiveInRankSem[accentFold(mb_strtoupper($tName))]), ARRAY_FILTER_USE_KEY);
}

// History + Recommendations
$historyTeachers = [];
$historyScores   = [];
$accScores       = [];
$groupAccum      = []; // [teacher_name][group] = accumulated weighted score
$recTeachers     = [];
$recConsecutive  = [];
$recPriority     = [];

if (!empty($xmlSemesters)) {
    $placeholders = implode(',', array_fill(0, count($xmlSemesters), '?'));
    $stmtHT = $conn->prepare("
        SELECT DISTINCT teacher_name FROM schedule_slots
        WHERE semester IN ($placeholders) ORDER BY teacher_name
    ");
    $stmtHT->execute($xmlSemesters);
    $historyTeachers = $stmtHT->fetchAll(PDO::FETCH_COLUMN);

    $inactiveSet = [];
    $phIn = implode(',', array_fill(0, count($xmlSemesters), '?'));
    $stmtIn = $conn->prepare("SELECT teacher_name, semester FROM teacher_inactive_semesters WHERE semester IN ($phIn)");
    $stmtIn->execute($xmlSemesters);
    foreach ($stmtIn->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $inactiveSet[mb_strtoupper($r['teacher_name'])][$r['semester']] = true;
    }

    $recencyWeights = [1.0, 0.7, 0.5, 0.3];
    $allSemScores   = [];
    foreach ($xmlSemesters as $sem) {
        $allSemScores[$sem] = computeSemesterScores($conn, $sem);
    }

    foreach ($historyTeachers as $tName) {
        $acc     = 0.0;
        $nActive = 0;
        $tKey    = mb_strtoupper($tName);
        $groupAccum[$tName] = array_fill_keys(array_keys(slotGroups()), 0.0);
        foreach ($xmlSemesters as $i => $sem) {
            $s  = $allSemScores[$sem][$tName]['scoreTotal'] ?? 0.0;
            $gs = $allSemScores[$sem][$tName]['groupScores'] ?? [];
            $w  = $recencyWeights[min($i, 3)];
            $historyScores[$tName][$sem] = $s;
            if (!isset($inactiveSet[$tKey][$sem])) {
                $acc += $s;
                $nActive++;
            }
            foreach (array_keys(slotGroups()) as $grp) {
                $groupAccum[$tName][$grp] += ($gs[$grp] ?? 0.0) * $w;
            }
        }
        $accScores[$tName] = round($nActive > 0 ? $acc / $nActive : 0.0, 2);
    }

    $histSort = $_GET['hist_sort'] ?? 'total';
    if ($histSort === 'name') {
        sort($historyTeachers);
    } else {
        usort($historyTeachers, fn($a, $b) => ($accScores[$b] ?? 0) <=> ($accScores[$a] ?? 0));
    }

    if (!$showInactive && !empty($inactiveInRankSem)) {
        $historyTeachers = array_values(array_filter($historyTeachers, fn($t) => !isset($inactiveInRankSem[accentFold(mb_strtoupper($t))])));
    }

    $recTeachers = $historyTeachers;
    usort($recTeachers, fn($a, $b) => $accScores[$a] <=> $accScores[$b]);

    foreach ($recTeachers as $tName) {
        $consec = 0;
        foreach ($xmlSemesters as $sem) {
            if (($historyScores[$tName][$sem] ?? 0.0) == 0.0) $consec++;
            else break;
        }
        $recConsecutive[$tName] = $consec;

        $acc     = $accScores[$tName] ?? 0.0;
        $segAcc  = ($groupAccum[$tName]['seg_m'] ?? 0) + ($groupAccum[$tName]['seg_t'] ?? 0) + ($groupAccum[$tName]['seg_n'] ?? 0);
        $sexAcc  = ($groupAccum[$tName]['sex_m'] ?? 0) + ($groupAccum[$tName]['sex_t'] ?? 0) + ($groupAccum[$tName]['sex_n'] ?? 0);

        if ($acc == 0) {
            $recPriority[$tName] = ['label' => 'Seg manhã e Sex noite', 'class' => 'text-red-700 bg-red-50'];
        } elseif ($segAcc > 0 && $sexAcc > 0) {
            $recPriority[$tName] = ['label' => 'Evitar Seg e Sex', 'class' => 'text-orange-700 bg-orange-50'];
        } elseif ($segAcc >= $sexAcc) {
            $recPriority[$tName] = ['label' => 'Evitar Segunda', 'class' => 'text-yellow-700 bg-yellow-50'];
        } else {
            $recPriority[$tName] = ['label' => 'Evitar Sexta', 'class' => 'text-yellow-700 bg-yellow-50'];
        }
    }
}

// CSV export
if (isset($_GET['export_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="recomendacoes_equidade_' . date('Ymd') . '.csv"');
    echo "\xEF\xBB\xBF";
    $fp = fopen('php://output', 'w');
    fputcsv($fp, ['#', 'Docente', 'Score Acum.', 'Sem. seguidos sem slot ruim', 'Priorizar no próximo sem.', 'Recomendação'], ';');
    foreach ($recTeachers as $rank => $tName) {
        $acc    = $accScores[$tName] ?? 0.0;
        $consec = $recConsecutive[$tName] ?? 0;
        $prior  = $recPriority[$tName]['label'] ?? '';
        $rec    = $acc < 2 ? 'Deve assumir slots ruins' : ($acc < 5 ? 'Atenção' : 'Já contribui');
        fputcsv($fp, [$rank + 1, $tName, number_format($acc, 1, ',', '.'), $consec, $prior, $rec], ';');
    }
    fclose($fp);
    exit;
}

function slotShort(string $code): string {
    $map = [
        'manha12' => 'Manhã 12', 'manha34' => 'Manhã 34', 'tarde12' => 'Tarde 12',
        'tarde34' => 'Tarde 34', 'noite12' => 'Noite 12', 'noite34' => 'Noite 34',
        'noite123' => 'Noite 123',
    ];
    return $map[$code] ?? $code;
}

$pageTitle = 'Equidade de Horários';
require_once 'layout/header.php';
require_once 'layout/sidebar.php';
?>

<main class="flex-1 overflow-y-auto p-8">
<div class="max-w-6xl mx-auto">

    <div class="mb-6 flex items-start justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Equidade de Horários</h1>
            <p class="text-sm text-gray-500 mt-1">Justiceiro do Tempo — análise de equidade na distribuição de slots pesados entre docentes</p>
        </div>
        <div class="relative no-print" id="pdf-btn-wrap">
            <button onclick="togglePdfMenu(event)"
                class="flex items-center gap-2 px-4 py-2 border border-gray-200 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                Gerar PDF
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div id="pdf-dropdown" class="hidden absolute right-0 top-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg w-56 z-50 py-1">
                <button onclick="printReport(false)"
                    class="block w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                    <div class="font-medium">Apenas ranking</div>
                    <div class="text-xs text-gray-400 mt-0.5">Tabela de scores sem grades</div>
                </button>
                <button onclick="printReport(true)"
                    class="block w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors border-t border-gray-100">
                    <div class="font-medium">Ranking + grades</div>
                    <div class="text-xs text-gray-400 mt-0.5">Expande as grades de todos</div>
                </button>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4 text-sm"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Tab nav -->
    <div class="border-b border-gray-200 mb-6">
        <nav class="flex gap-6" id="tab-nav">
            <?php
            $tabs = [
                'ranking' => 'Diagnóstico',
                'history' => 'Histórico',
                'rec'     => 'Recomendações',
            ];
            foreach ($tabs as $tid => $tlabel):
            ?>
            <button onclick="switchTab('<?= $tid ?>')"
                id="tab-btn-<?= $tid ?>"
                class="tab-btn px-5 py-3 text-sm font-medium border-b-2 transition-colors
                       <?= $tab === $tid ? 'border-brand-DEFAULT text-brand-DEFAULT' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                <?= $tlabel ?>
            </button>
            <?php endforeach; ?>
        </nav>
    </div>


    <!-- ═══ TAB: Diagnóstico (ranking) ═══ -->
    <div id="tab-ranking" class="tab-panel <?= $tab !== 'ranking' ? 'hidden' : '' ?>">
        <?php
        $bsw    = badSlotWeights();
        $bsl    = badSlotLabels();
        $slots7 = array_values(AscXmlParser::SLOT_MAP);
        ?>

        <div class="flex flex-wrap items-center gap-3 mb-6">
            <form method="GET" class="flex items-center gap-2">
                <input type="hidden" name="tab" value="ranking">
                <label class="text-sm font-medium text-gray-700">Semestre:</label>
                <div class="relative">
                    <select name="rank_sem" onchange="this.form.submit()"
                        class="appearance-none border border-gray-200 rounded-lg pl-3 pr-8 py-2 text-sm focus:ring-2 focus:ring-brand-DEFAULT outline-none bg-gray-50 cursor-pointer">
                        <?php foreach ($xmlSemesters as $s): ?>
                        <option value="<?= $s ?>" <?= $rankSem === $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-gray-400 text-xs">▾</span>
                </div>
            </form>
            <div class="flex items-center gap-2">
                <span class="text-xs font-medium text-gray-400">Ordenar por</span>
                <div class="inline-flex rounded-lg border border-gray-200 overflow-hidden divide-x divide-gray-200 text-xs shadow-sm">
                    <?php foreach (['total' => 'Score Total', 'score' => 'Score', 'coord' => 'Coord.', 'name' => 'Nome'] as $sk => $sl):
                        $active = ($rankSort === $sk); ?>
                    <a href="?tab=ranking&rank_sem=<?= urlencode($rankSem) ?>&rank_sort=<?= $sk ?>"
                       class="px-3 py-1.5 font-medium transition-colors whitespace-nowrap
                              <?= $active ? 'bg-brand-DEFAULT text-white' : 'bg-white text-gray-600 hover:bg-gray-50' ?>">
                        <?= $sl ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php if (!empty($inactiveInRankSem)): ?>
            <a href="?tab=ranking&rank_sem=<?= urlencode($rankSem) ?>&rank_sort=<?= $rankSort ?>&show_inactive=<?= $showInactive ? 0 : 1 ?>"
               class="no-print ml-auto px-3 py-1.5 text-xs font-medium border rounded-lg transition-colors
                      <?= $showInactive ? 'bg-amber-50 text-amber-700 border-amber-200 hover:bg-amber-100' : 'bg-white text-gray-500 border-gray-200 hover:bg-gray-50' ?>">
                <?php if ($showInactive): ?>
                    <svg class="w-3.5 h-3.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    Ocultar inativos
                <?php else: ?>
                    <svg class="w-3.5 h-3.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                    Mostrar inativos
                <?php endif; ?>
            </a>
            <?php endif; ?>
        </div>

        <?php if (empty($rankScores)): ?>
        <div class="text-center text-gray-400 text-sm py-12">Nenhum dado encontrado para <?= htmlspecialchars($rankSem) ?>.</div>
        <?php else: ?>

        <!-- Legend -->
        <div class="flex flex-wrap gap-4 text-xs mb-4 bg-white rounded-xl border border-gray-100 px-4 py-3 items-center">
            <span class="font-medium text-gray-500">Legenda:</span>
            <span class="flex items-center gap-1.5"><span class="px-1.5 h-4 inline-flex items-center justify-center rounded bg-blue-300 text-blue-900 font-bold text-[10px]">Aula</span> Aula normal</span>
            <span class="flex items-center gap-1.5"><span class="px-1.5 h-4 inline-flex items-center justify-center rounded bg-orange-300 text-orange-900 font-bold text-[10px]">Aula⚠</span> Aula em slot pesado (Seg/Sex)</span>
            <span class="flex items-center gap-1.5"><span class="w-4 h-4 inline-block rounded bg-gray-100"></span> Sem aula</span>
        </div>

        <!-- Ranking table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-left">
                        <th rowspan="2" class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-8">#</th>
                        <th rowspan="2" class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Docente</th>
                        <th colspan="3" class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider text-center border-l border-gray-200">Segunda</th>
                        <th colspan="3" class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider text-center border-l border-gray-200">Sexta</th>
                        <th rowspan="2" class="px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider text-center border-l border-gray-200">Demais H.</th>
                        <th rowspan="2" class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center">Score</th>
                        <th rowspan="2" class="px-4 py-3 text-xs font-semibold text-purple-500 uppercase tracking-wider text-center">Score Coord.</th>
                        <th rowspan="2" class="px-4 py-3 text-xs font-semibold text-indigo-600 uppercase tracking-wider text-center border-l-2 border-indigo-200">Score Total</th>
                        <th rowspan="2" class="px-4 py-3 w-16"></th>
                    </tr>
                    <tr class="bg-gray-50 border-t border-gray-100">
                        <th class="px-3 py-1.5 text-xs font-semibold text-gray-400 text-center border-l border-gray-200">M</th>
                        <th class="px-3 py-1.5 text-xs font-semibold text-gray-400 text-center">T</th>
                        <th class="px-3 py-1.5 text-xs font-semibold text-gray-400 text-center">N</th>
                        <th class="px-3 py-1.5 text-xs font-semibold text-gray-400 text-center border-l border-gray-200">M</th>
                        <th class="px-3 py-1.5 text-xs font-semibold text-gray-400 text-center">T</th>
                        <th class="px-3 py-1.5 text-xs font-semibold text-gray-400 text-center">N</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $rank = 0; foreach ($rankScores as $tName => $data): $rank++; ?>
                    <?php
                    $tKey    = mb_strtoupper($tName);
                    $isCoord = isset($rankCoords[$tKey]);
                    $gs      = $data['groupScores'];
                    ?>
                    <tr class="border-t border-gray-50 hover:bg-gray-50">
                        <td class="px-5 py-3 text-gray-400 text-xs"><?= $rank ?></td>
                        <td class="px-5 py-3 font-medium text-gray-800">
                            <?= htmlspecialchars($tName) ?>
                            <?php if ($isCoord): ?>
                            <span class="ml-1 text-xs font-bold text-white bg-green-500 px-1.5 py-0.5 rounded">(C)</span>
                            <?php endif; ?>
                        </td>
                        <!-- Segunda: M, T, N -->
                        <?php foreach (['seg_m','seg_t','seg_n'] as $i => $grp): ?>
                        <?php $gsc = $gs[$grp] ?? 0.0; ?>
                        <td class="px-3 py-3 text-center text-xs <?= $i === 0 ? 'border-l border-gray-200' : '' ?>">
                            <?php if ($gsc > 0): ?>
                            <span class="font-bold text-orange-600"><?= number_format($gsc, 1) ?></span>
                            <?php else: ?><span class="text-gray-200">—</span><?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                        <!-- Sexta: M, T, N -->
                        <?php foreach (['sex_m','sex_t','sex_n'] as $i => $grp): ?>
                        <?php $gsc = $gs[$grp] ?? 0.0; ?>
                        <td class="px-3 py-3 text-center text-xs <?= $i === 0 ? 'border-l border-gray-200' : '' ?>">
                            <?php if ($gsc > 0): ?>
                            <span class="font-bold text-orange-600"><?= number_format($gsc, 1) ?></span>
                            <?php else: ?><span class="text-gray-200">—</span><?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                        <!-- Demais H. -->
                        <td class="px-4 py-3 text-center border-l border-gray-200">
                            <?php $bsc = $data['baseScore']; ?>
                            <?php if ($bsc > 0): ?>
                            <span class="text-xs text-blue-500"><?= number_format($bsc, 2) ?></span>
                            <?php else: ?><span class="text-gray-200 text-xs">—</span><?php endif; ?>
                        </td>
                        <!-- Score -->
                        <td class="px-4 py-3 text-center">
                            <?php $sc = $data['score']; ?>
                            <span class="font-bold text-sm <?= $sc >= 5 ? 'text-orange-600' : ($sc >= 2 ? 'text-yellow-600' : 'text-gray-500') ?>">
                                <?= number_format($sc, 1) ?>
                            </span>
                        </td>
                        <!-- Score Coord. -->
                        <td class="px-4 py-3 text-center">
                            <?php $csc = $data['coordScore']; ?>
                            <?php if ($csc > 0): ?>
                            <span class="font-bold text-sm text-purple-600"><?= number_format($csc, 1) ?></span>
                            <?php else: ?><span class="text-gray-200 text-xs">—</span><?php endif; ?>
                        </td>
                        <!-- Score Total -->
                        <td class="px-4 py-3 text-center border-l-2 border-indigo-100">
                            <?php $tot = $data['scoreTotal']; ?>
                            <span class="font-extrabold text-sm <?= $tot >= 10 ? 'text-red-600' : ($tot >= 5 ? 'text-orange-600' : ($tot >= 2 ? 'text-yellow-600' : 'text-gray-400')) ?>">
                                <?= number_format($tot, 1) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button onclick="toggleGrid('grid-<?= $rank ?>')"
                                class="text-xs text-brand-DEFAULT hover:underline whitespace-nowrap">
                                Grade ↕
                            </button>
                        </td>
                    </tr>
                    <!-- Expandable mini-grid -->
                    <tr id="grid-<?= $rank ?>" class="hidden">
                        <td colspan="13" class="px-5 py-4 bg-gray-50">
                            <div class="overflow-x-auto">
                                <table class="text-xs border-collapse">
                                    <thead>
                                        <tr>
                                            <th class="px-2 py-1 text-gray-400 font-normal text-left w-24"></th>
                                            <?php foreach (AscXmlParser::DAY_LABELS as $dayLabel): ?>
                                            <th class="px-3 py-1 text-gray-500 font-semibold text-center w-20"><?= $dayLabel ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($slots7 as $slotCode): ?>
                                        <tr>
                                            <td class="px-2 py-1.5 text-gray-500 whitespace-nowrap"><?= slotShort($slotCode) ?></td>
                                            <?php for ($d = 1; $d <= 5; $d++):
                                                $hasAlloc  = isset($rankAllocData[$tKey][$d][$slotCode]);
                                                $cellClass = gridCellClass($d, $slotCode, $hasAlloc);
                                                $isBad     = isset($bsw[$d . '_' . $slotCode]);
                                                $icon      = $hasAlloc ? ($isBad ? 'Aula⚠' : 'Aula') : '';
                                            ?>
                                            <td class="px-3 py-1.5 text-center rounded text-xs font-medium <?= $cellClass ?>">
                                                <?= $icon ?>
                                            </td>
                                            <?php endfor; ?>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <!-- Per-slot badges for bad slots with activity -->
                            <?php
                            $hasBadge = false;
                            foreach ($bsl as $slotKey => $slotLabel) {
                                $det = $data['detail'][$slotKey] ?? 'free';
                                if ($det !== 'free') { $hasBadge = true; break; }
                            }
                            ?>
                            <?php if ($hasBadge): ?>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <?php foreach ($bsl as $slotKey => $slotLabel):
                                    if (($data['detail'][$slotKey] ?? 'free') !== 'alloc') continue;
                                ?>
                                <span class="text-xs px-2 py-1 rounded-full bg-orange-100 text-orange-700">
                                    <?= htmlspecialchars($slotLabel) ?> (aula ⚠)
                                </span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="px-5 py-3 border-t border-gray-100 text-xs text-gray-400">
                Pesos: Seg Manhã 3,0/2,5 · Seg Tarde 2,0/1,5 · Seg Noite 1,0 · Sex Manhã 1,0 · Sex Tarde 34 2,5 · Sex Noite 3,0.
                Diagnóstico: +0,05 por aula em Ter/Qua/Qui.
                Coordenação ★: peso × 0,8.
            </div>
        </div>
        <?php endif; ?>
    </div><!-- /tab-ranking -->

    <!-- ═══ TAB: Histórico ═══ -->
    <div id="tab-history" class="tab-panel <?= $tab !== 'history' ? 'hidden' : '' ?>">
        <?php if (empty($historyTeachers)): ?>
        <div class="text-center text-gray-400 text-sm py-12">Nenhum dado disponível. Importe ao menos um semestre.</div>
        <?php else: ?>

        <div class="flex items-center gap-2 mb-4">
            <span class="text-xs font-medium text-gray-400">Ordenar por</span>
            <div class="inline-flex rounded-lg border border-gray-200 overflow-hidden divide-x divide-gray-200 text-xs shadow-sm">
                <a href="?tab=history&hist_sort=total&show_inactive=<?= $showInactive ? 1 : 0 ?>"
                   class="px-3 py-1.5 font-medium transition-colors <?= ($histSort ?? 'total') === 'total' ? 'bg-brand-DEFAULT text-white' : 'bg-white text-gray-600 hover:bg-gray-50' ?>">
                    Score Médio
                </a>
                <a href="?tab=history&hist_sort=name&show_inactive=<?= $showInactive ? 1 : 0 ?>"
                   class="px-3 py-1.5 font-medium transition-colors <?= ($histSort ?? '') === 'name' ? 'bg-brand-DEFAULT text-white' : 'bg-white text-gray-600 hover:bg-gray-50' ?>">
                    Nome
                </a>
            </div>
            <?php if (!empty($inactiveInRankSem)): ?>
            <a href="?tab=history&hist_sort=<?= $histSort ?? 'total' ?>&show_inactive=<?= $showInactive ? 0 : 1 ?>"
               class="no-print px-3 py-1.5 text-xs font-medium border rounded-lg transition-colors
                      <?= $showInactive ? 'bg-amber-50 text-amber-700 border-amber-200 hover:bg-amber-100' : 'bg-white text-gray-500 border-gray-200 hover:bg-gray-50' ?>">
                <?php if ($showInactive): ?>
                    <svg class="w-3.5 h-3.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    Ocultar inativos
                <?php else: ?>
                    <svg class="w-3.5 h-3.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                    Mostrar inativos
                <?php endif; ?>
            </a>
            <?php endif; ?>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="font-semibold text-gray-900">Score por Semestre</h2>
                <p class="text-xs text-gray-400 mt-0.5">Células em branco = score 0. Intensidade de vermelho = maior contribuição em slots pesados.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-left">
                            <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider sticky left-0 bg-gray-50">Docente</th>
                            <?php foreach ($xmlSemesters as $sem): ?>
                            <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center whitespace-nowrap"><?= $sem ?></th>
                            <?php endforeach; ?>
                            <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center whitespace-nowrap bg-gray-100">Score Médio</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach ($historyTeachers as $tName): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-2.5 font-medium text-gray-800 sticky left-0 bg-white"><?= htmlspecialchars($tName) ?></td>
                            <?php foreach ($xmlSemesters as $sem): ?>
                            <?php $sc = $historyScores[$tName][$sem] ?? 0.0; ?>
                            <td class="px-4 py-2.5 text-center"
                                <?php if ($sc > 0):
                                    $alpha = min(0.9, $sc / 10);
                                    $g = (int)(220 - $alpha * 160);
                                    $b = (int)(220 - $alpha * 160);
                                ?>style="background:rgba(255,<?= $g ?>,<?= $b ?>,0.5)"<?php endif; ?>>
                                <?= $sc > 0 ? number_format($sc, 1) : '<span class="text-gray-200">—</span>' ?>
                            </td>
                            <?php endforeach; ?>
                            <?php $acc = $accScores[$tName] ?? 0.0; ?>
                            <td class="px-4 py-2.5 text-center font-bold bg-gray-50
                                <?= $acc >= 8 ? 'text-orange-600' : ($acc >= 4 ? 'text-yellow-600' : 'text-gray-500') ?>">
                                <?= number_format($acc, 1) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div><!-- /tab-history -->

    <!-- ═══ TAB: Recomendações ═══ -->
    <div id="tab-rec" class="tab-panel <?= $tab !== 'rec' ? 'hidden' : '' ?>">
        <?php if (empty($recTeachers)): ?>
        <div class="text-center text-gray-400 text-sm py-12">Nenhum dado disponível.</div>
        <?php else: ?>

        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="font-semibold text-gray-900">Recomendações para o próximo semestre</h2>
                <p class="text-xs text-gray-400 mt-0.5">
                    Ordenado por score acumulado crescente — quem menos contribuiu aparece primeiro.
                    Pesos de recência: <?= implode(', ', [1.0, 0.7, 0.5, 0.3]) ?> (do mais recente para o mais antigo).
                </p>
            </div>
            <div class="flex items-center gap-2">
            <?php if (!empty($inactiveInRankSem)): ?>
            <a href="?tab=rec&show_inactive=<?= $showInactive ? 0 : 1 ?>"
               class="no-print px-3 py-1.5 text-xs font-medium border rounded-lg transition-colors
                      <?= $showInactive ? 'bg-amber-50 text-amber-700 border-amber-200 hover:bg-amber-100' : 'bg-white text-gray-500 border-gray-200 hover:bg-gray-50' ?>">
                <?php if ($showInactive): ?>
                    <svg class="w-3.5 h-3.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    Ocultar inativos
                <?php else: ?>
                    <svg class="w-3.5 h-3.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                    Mostrar inativos
                <?php endif; ?>
            </a>
            <?php endif; ?>
            <a href="?export_csv=1"
               class="no-print px-4 py-2 border border-gray-200 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Exportar CSV
            </a>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-left">
                        <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-10">#</th>
                        <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Docente</th>
                        <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center">Score Acum.</th>
                        <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center whitespace-nowrap">Sem. sem slot pesado</th>
                        <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Priorizar no próx. sem.</th>
                        <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Recomendação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($recTeachers as $rank => $tName):
                        $acc    = $accScores[$tName]      ?? 0.0;
                        $consec = $recConsecutive[$tName]  ?? 0;
                        $prior  = $recPriority[$tName]     ?? ['label' => '—', 'class' => 'text-gray-500 bg-gray-50'];
                        if ($acc < 2) {
                            $recLabel = 'Deve assumir slots pesados'; $recClass = 'text-red-700 bg-red-50'; $recIcon = '⛔';
                        } elseif ($acc < 5) {
                            $recLabel = 'Atenção'; $recClass = 'text-yellow-700 bg-yellow-50'; $recIcon = '⚠️';
                        } else {
                            $recLabel = 'Já contribui'; $recClass = 'text-green-700 bg-green-50'; $recIcon = '✅';
                        }
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 text-gray-400 text-xs"><?= $rank + 1 ?></td>
                        <td class="px-5 py-3 font-medium text-gray-800"><?= htmlspecialchars($tName) ?></td>
                        <td class="px-4 py-3 text-center font-bold
                            <?= $acc >= 8 ? 'text-orange-600' : ($acc >= 4 ? 'text-yellow-600' : 'text-gray-500') ?>">
                            <?= number_format($acc, 1) ?>
                        </td>
                        <td class="px-4 py-3 text-center text-gray-600">
                            <?= $consec > 0 ? "$consec sem." : '—' ?>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center text-xs px-2.5 py-1 rounded-full <?= $prior['class'] ?>">
                                <?= htmlspecialchars($prior['label']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1 text-xs px-2.5 py-1 rounded-full <?= $recClass ?>">
                                <?= $recIcon ?> <?= $recLabel ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div><!-- /tab-rec -->

</div>
</main>

<script>
function switchTab(id) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(b => {
        b.classList.remove('border-brand-DEFAULT', 'text-brand-DEFAULT');
        b.classList.add('border-transparent', 'text-gray-500');
    });
    document.getElementById('tab-' + id).classList.remove('hidden');
    const btn = document.getElementById('tab-btn-' + id);
    if (btn) {
        btn.classList.remove('border-transparent', 'text-gray-500');
        btn.classList.add('border-brand-DEFAULT', 'text-brand-DEFAULT');
    }
    const url = new URL(window.location);
    url.searchParams.set('tab', id);
    history.replaceState(null, '', url);
}

function toggleGrid(id) {
    const el = document.getElementById(id);
    if (el) el.classList.toggle('hidden');
}

function togglePdfMenu(e) {
    e.stopPropagation();
    document.getElementById('pdf-dropdown').classList.toggle('hidden');
}
document.addEventListener('click', () => document.getElementById('pdf-dropdown')?.classList.add('hidden'));

function printReport(withGrids) {
    document.getElementById('pdf-dropdown').classList.add('hidden');
    if (withGrids) {
        document.querySelectorAll('[id^="grid-"]').forEach(el => el.classList.remove('hidden'));
    }
    // Show all tab panels for print
    document.querySelectorAll('.tab-panel').forEach(el => el.classList.remove('hidden'));
    window.print();
}
</script>

<style>
@media print {
    aside { display: none !important; }
    #tab-nav { display: none !important; }
    .flex.h-screen { display: block !important; height: auto !important; overflow: visible !important; }
    main { height: auto !important; overflow: visible !important; padding: 16px !important; }
    .tab-panel { display: block !important; }
    .no-print { display: none !important; }
    #print-report-header { display: block !important; }
    tr { page-break-inside: avoid; }
    .tab-panel + .tab-panel { page-break-before: always; margin-top: 0; padding-top: 0; }
    .tab-panel h2:first-child { page-break-before: auto; }
    button { display: none !important; }
}
</style>

<div id="print-report-header" style="display:none" class="mb-6 pb-4 border-b-2 border-gray-300">
    <h1 class="text-xl font-bold text-gray-900">Equidade de Horários — <?= htmlspecialchars($rankSem ?: ($xmlSemesters[0] ?? '')) ?></h1>
    <p class="text-sm text-gray-500 mt-1">
        Gerado em: <strong><?= date('d/m/Y \à\s H:i') ?></strong>
        <?php if (!$showInactive && !empty($inactiveInRankSem)): ?>
        &nbsp;·&nbsp; <em>Docentes inativos ocultos</em>
        <?php endif; ?>
    </p>
</div>

<?php require_once 'layout/footer.php'; ?>
