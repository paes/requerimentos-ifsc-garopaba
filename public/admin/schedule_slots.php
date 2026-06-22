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

$error   = '';
$success = '';
$importSummary = null;

$currentMonth    = (int)date('n');
$currentYear     = date('Y');
$defaultSemester = $currentYear . '.' . ($currentMonth >= 2 && $currentMonth <= 7 ? '1' : '2');

$storageDir = dirname(__DIR__, 2) . '/storage/schedules/';

// --- Exclusão de slot individual ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_slot_id'])) {
    $slotId = (int)$_POST['delete_slot_id'];
    $conn->prepare("DELETE FROM schedule_slots WHERE id = :id")->execute([':id' => $slotId]);
    $success = 'Entrada removida.';
}

// --- Importação de XML ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_xml'])) {
    $semester = trim($_POST['semester'] ?? '');

    if (!preg_match('/^\d{4}\.[12]$/', $semester)) {
        $error = 'Semestre inválido. Use o formato AAAA.S (ex: 2026.1).';
    } elseif (!isset($_FILES['xml_file']) || $_FILES['xml_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Erro no upload do arquivo XML.';
    } else {
        $file = $_FILES['xml_file'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($ext !== 'xml') {
            $error = 'Apenas arquivos XML são aceitos.';
        } elseif ($file['size'] > 10 * 1024 * 1024) {
            $error = 'O arquivo XML excede 10 MB.';
        } else {
            $safeSemester = str_replace('.', '-', $semester);
            $xmlFilename  = 'horarios_' . $safeSemester . '_' . time() . '.xml';
            $xmlDest      = $storageDir . $xmlFilename;

            if (!move_uploaded_file($file['tmp_name'], $xmlDest)) {
                $error = 'Falha ao mover o arquivo. Verifique as permissões do diretório.';
            } else {
                $parser  = new AscXmlParser();
                $entries = $parser->parse($xmlDest);

                if (empty($entries)) {
                    $error = 'Nenhuma aula encontrada no XML. Verifique se o arquivo é uma exportação válida do aSc Timetables.';
                    unlink($xmlDest);
                } else {
                    // Carregar mapa nome → id dos docentes cadastrados
                    $stmtT = $conn->prepare("SELECT id, name FROM teachers WHERE active = 1");
                    $stmtT->execute();
                    $teacherMap = [];
                    foreach ($stmtT->fetchAll(PDO::FETCH_ASSOC) as $t) {
                        $teacherMap[mb_strtolower(trim($t['name']))] = $t['id'];
                    }

                    $conn->beginTransaction();
                    try {
                        // Limpar dados anteriores do semestre
                        $conn->prepare("DELETE FROM schedule_slots WHERE semester = :sem")->execute([':sem' => $semester]);

                        $stmtIns = $conn->prepare("
                            INSERT INTO schedule_slots
                                (semester, teacher_id, teacher_name, class_group, subject_name, day_of_week, time_slot, terms)
                            VALUES
                                (:semester, :teacher_id, :teacher_name, :class_group, :subject_name, :dow, :slot, :terms)
                        ");

                        $teacherCount = [];
                        foreach ($entries as $e) {
                            $nameKey   = mb_strtolower(trim($e['teacher_name']));
                            $teacherId = $teacherMap[$nameKey] ?? null;

                            $stmtIns->execute([
                                ':semester'     => $semester,
                                ':teacher_id'   => $teacherId,
                                ':teacher_name' => $e['teacher_name'],
                                ':class_group'  => $e['class_group'],
                                ':subject_name' => $e['subject_name'],
                                ':dow'          => $e['day_of_week'],
                                ':slot'         => $e['time_slot'],
                                ':terms'        => $e['terms'],
                            ]);

                            $teacherCount[$e['teacher_name']] = true;
                        }

                        $conn->commit();
                        $importSummary = [
                            'count'    => count($entries),
                            'teachers' => count($teacherCount),
                            'semester' => $semester,
                        ];
                        $success = count($entries) . ' alocações importadas para ' . count($teacherCount) . ' docentes (semestre ' . $semester . ').';
                    } catch (Exception $e2) {
                        $conn->rollBack();
                        $error = 'Erro ao salvar no banco de dados: ' . $e2->getMessage();
                        unlink($xmlDest);
                    }
                }
            }
        }
    }
}

// --- Semestres disponíveis ---
$stmtSems = $conn->prepare("SELECT DISTINCT semester FROM schedule_slots ORDER BY semester DESC");
$stmtSems->execute();
$semesters = $stmtSems->fetchAll(PDO::FETCH_COLUMN);

// --- Filtro de visualização ---
$viewSemester = $_GET['semester'] ?? ($semesters[0] ?? $defaultSemester);
$viewTeacher  = trim($_GET['teacher'] ?? '');

// Docentes do semestre selecionado
$stmtTeachers = $conn->prepare("
    SELECT DISTINCT teacher_name
    FROM schedule_slots
    WHERE semester = :sem
    ORDER BY teacher_name
");
$stmtTeachers->execute([':sem' => $viewSemester]);
$semTeachers = $stmtTeachers->fetchAll(PDO::FETCH_COLUMN);

// Slots do docente selecionado
$slots = [];
if ($viewTeacher) {
    $stmtSlots = $conn->prepare("
        SELECT id, day_of_week, time_slot, class_group, subject_name, terms
        FROM schedule_slots
        WHERE semester = :sem AND teacher_name = :teacher
        ORDER BY day_of_week, time_slot
    ");
    $stmtSlots->execute([':sem' => $viewSemester, ':teacher' => $viewTeacher]);
    $slots = $stmtSlots->fetchAll(PDO::FETCH_ASSOC);
}

// Coordenações do docente no semestre selecionado
$coordinations = [];
if ($viewTeacher) {
    $stmtCoord = $conn->prepare("
        SELECT role_name
        FROM course_coordinators
        WHERE semester = :sem AND LOWER(teacher_name) = LOWER(:teacher)
        ORDER BY role_name
    ");
    $stmtCoord->execute([':sem' => $viewSemester, ':teacher' => $viewTeacher]);
    $coordinations = $stmtCoord->fetchAll(PDO::FETCH_COLUMN);
}


$pageTitle = 'Grade de Horários';
require_once 'layout/header.php';
require_once 'layout/sidebar.php';
?>

<main class="flex-1 overflow-y-auto p-8">
    <div class="max-w-5xl mx-auto">

        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">Grade de Horários</h1>
            <p class="text-sm text-gray-500 mt-1">Importar horário do aSc Timetables e consultar alocações por docente</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 text-sm">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6 text-sm">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <!-- Importar XML -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-8">
            <h2 class="font-semibold text-gray-900 mb-1">Importar Horário (XML do aSc Timetables)</h2>
            <p class="text-xs text-gray-400 mb-4">Importe a exportação XML do aSc Timetables (formato asctt2012). Os dados do semestre serão substituídos.</p>

            <form method="POST" enctype="multipart/form-data" class="flex flex-wrap gap-4 items-end">
                <?= Csrf::field() ?>
                <input type="hidden" name="import_xml" value="1">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wider">Semestre</label>
                    <input type="text" name="semester" value="<?= htmlspecialchars($defaultSemester) ?>"
                        placeholder="ex: 2026.1"
                        pattern="\d{4}\.[12]"
                        class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-DEFAULT outline-none bg-gray-50 w-32">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wider">Arquivo XML</label>
                    <input type="file" name="xml_file" accept=".xml"
                        class="border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50 text-gray-700">
                </div>
                <button type="submit"
                    class="px-4 py-2 bg-brand-DEFAULT text-white text-sm font-medium rounded-lg hover:bg-brand-dark transition-colors">
                    Importar XML
                </button>
            </form>
        </div>

        <!-- Visualização por docente -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="font-semibold text-gray-900 mb-3">Consultar por Docente</h2>
                <form method="GET" class="flex flex-wrap gap-3 items-end">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wider">Semestre</label>
                        <select name="semester" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-DEFAULT outline-none bg-gray-50">
                            <?php if (empty($semesters)): ?>
                                <option value="<?= htmlspecialchars($defaultSemester) ?>"><?= htmlspecialchars($defaultSemester) ?></option>
                            <?php else: ?>
                                <?php foreach ($semesters as $s): ?>
                                    <option value="<?= htmlspecialchars($s) ?>" <?= $viewSemester === $s ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wider">Docente</label>
                        <select name="teacher" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-DEFAULT outline-none bg-gray-50 min-w-[240px]">
                            <option value="">— Selecione —</option>
                            <?php foreach ($semTeachers as $tName): ?>
                                <option value="<?= htmlspecialchars($tName) ?>" <?= $viewTeacher === $tName ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tName) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit"
                        class="px-4 py-2 bg-brand-DEFAULT text-white text-sm font-medium rounded-lg hover:bg-brand-dark transition-colors">
                        Consultar
                    </button>
                </form>
            </div>

            <?php if ($viewTeacher && empty($slots)): ?>
                <div class="px-6 py-8 text-center text-gray-400 text-sm">
                    Nenhuma alocação encontrada para <?= htmlspecialchars($viewTeacher) ?> no semestre <?= htmlspecialchars($viewSemester) ?>.
                </div>
            <?php elseif (!$viewTeacher && empty($semesters)): ?>
                <div class="px-6 py-8 text-center text-gray-400 text-sm">
                    Nenhum horário importado ainda. Use o formulário acima para importar o XML do aSc Timetables.
                </div>
            <?php elseif (!$viewTeacher): ?>
                <div class="px-6 py-8 text-center text-gray-400 text-sm">
                    Selecione um docente para visualizar as alocações.
                </div>
            <?php else: ?>
                <?php
                // Construir grid de horários: [time_slot][day_of_week] → [{class_group, subject_name, terms}]
                $grid = [];
                foreach ($slots as $slot) {
                    $grid[$slot['time_slot']][$slot['day_of_week']][] = $slot;
                }
                $palette     = ['#bfdbfe','#bbf7d0','#fde68a','#e9d5ff','#fed7aa','#bae6fd','#fbcfe8','#d9f99d'];
                $classColors = [];
                $colorIdx    = 0;
                foreach ($slots as $slot) {
                    $cg = $slot['class_group'];
                    if (!isset($classColors[$cg])) {
                        $classColors[$cg] = $palette[$colorIdx % count($palette)];
                        $colorIdx++;
                    }
                }

                // Calcular CH por duração — separado entre Regular e FIC
                $chByDur    = [0, 0, 0, 0];
                $chByDurFic = [0, 0, 0, 0];
                $chTotal    = 0;
                $chTotalFic = 0;
                foreach ($slots as $slot) {
                    $ha    = AscXmlParser::SLOT_HOURS[$slot['time_slot']] ?? 2;
                    $terms = $slot['terms'] ?? '1111';
                    $isFic = stripos($slot['class_group'], 'FIC') !== false;
                    for ($i = 0; $i < 4; $i++) {
                        if (($terms[$i] ?? '0') === '1') {
                            if ($isFic) $chByDurFic[$i] += $ha;
                            else        $chByDur[$i]    += $ha;
                        }
                    }
                    $weeks = substr_count($terms, '1') * 5;
                    if ($isFic) $chTotalFic += $ha * $weeks;
                    else        $chTotal    += $ha * $weeks;
                }
                $chGrand    = $chTotal + $chTotalFic;
                $chGrandDur = array_map(fn($r, $f) => $r + $f, $chByDur, $chByDurFic);
                $hasFic     = $chTotalFic > 0;
                ?>

                <!-- Mini-card: coordenação -->
                <?php if (!empty($coordinations)): ?>
                <div class="px-6 py-3 bg-amber-50 border-b border-amber-100 flex items-start gap-3">
                    <svg class="w-4 h-4 text-amber-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    <div>
                        <p class="text-xs font-semibold text-amber-700 mb-0.5">Em coordenação — <?= htmlspecialchars($viewSemester) ?></p>
                        <?php foreach ($coordinations as $cn): ?>
                        <p class="text-sm text-amber-800"><?= htmlspecialchars($cn) ?></p>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Grade visual de horários -->
                <div class="overflow-x-auto border-b border-gray-100">
                    <table class="w-full text-xs border-collapse">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-3 py-2 text-gray-500 font-semibold text-left border-r border-gray-200 whitespace-nowrap w-28">Turno</th>
                                <?php foreach (AscXmlParser::DAY_LABELS as $d): ?>
                                <th class="px-3 py-2 text-gray-500 font-semibold text-center border-r border-gray-200"><?= $d ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (AscXmlParser::SLOT_MAP as $slotCode): ?>
                            <?php $slotLabel = preg_replace('/\s*\([^)]+\)/', '', AscXmlParser::TIME_SLOT_LABELS[$slotCode] ?? $slotCode); ?>
                            <tr class="border-t border-gray-100">
                                <td class="px-3 py-2 text-gray-500 font-medium border-r border-gray-200 whitespace-nowrap"><?= $slotLabel ?></td>
                                <?php for ($dow = 1; $dow <= 5; $dow++): ?>
                                <?php $cells = $grid[$slotCode][$dow] ?? []; ?>
                                <td class="px-2 py-1 border-r border-gray-100 align-top" style="min-width:90px">
                                    <?php if (empty($cells)): ?>
                                        <span class="text-gray-200 block text-center">—</span>
                                    <?php else: ?>
                                        <?php foreach ($cells as $cell): ?>
                                        <div class="rounded px-1.5 py-1 mb-0.5 border border-black/10"
                                             style="background:<?= $classColors[$cell['class_group']] ?? '#f9fafb' ?>">
                                            <p class="font-semibold text-gray-800 leading-tight text-xs"><?= htmlspecialchars($cell['class_group']) ?></p>
                                            <?php if ($cell['subject_name']): ?>
                                            <p class="text-gray-500 leading-tight text-xs" style="max-width:80px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis"
                                               title="<?= htmlspecialchars($cell['subject_name']) ?>">
                                                <?= htmlspecialchars(mb_strimwidth($cell['subject_name'], 0, 18, '…')) ?>
                                            </p>
                                            <?php endif; ?>
                                            <?php if (($cell['terms'] ?? '1111') !== '1111'): ?>
                                            <p class="text-gray-400 leading-tight text-xs"><?= AscXmlParser::termsLabel($cell['terms']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                                <?php endfor; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Lista plana (com botões remover) -->
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 text-left">
                                <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Dia</th>
                                <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Turno</th>
                                <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Turma</th>
                                <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Disciplina</th>
                                <th class="px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Durações</th>
                                <th class="px-5 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php foreach ($slots as $slot): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-5 py-3 font-medium text-gray-700">
                                        <?= htmlspecialchars(AscXmlParser::DAY_LABELS[$slot['day_of_week']] ?? $slot['day_of_week']) ?>
                                    </td>
                                    <td class="px-5 py-3 text-gray-700">
                                        <?= htmlspecialchars(AscXmlParser::TIME_SLOT_LABELS[$slot['time_slot']] ?? $slot['time_slot']) ?>
                                    </td>
                                    <td class="px-5 py-3 text-gray-700">
                                        <?= htmlspecialchars($slot['class_group']) ?>
                                    </td>
                                    <td class="px-5 py-3 text-gray-500">
                                        <?= htmlspecialchars($slot['subject_name'] ?? '—') ?>
                                    </td>
                                    <td class="px-5 py-3 text-gray-500 text-xs">
                                        <?= htmlspecialchars(AscXmlParser::termsLabel($slot['terms'] ?? '1111')) ?>
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <form method="POST" onsubmit="return confirm('Remover esta alocação?')">
                                            <?= Csrf::field() ?>
                                            <input type="hidden" name="delete_slot_id" value="<?= $slot['id'] ?>">
                                            <button type="submit"
                                                class="text-xs text-red-500 hover:text-red-700 font-medium">
                                                Remover
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="px-5 py-3 text-xs text-gray-400 border-t border-gray-50">
                        <?= count($slots) ?> alocação(ões) — semestre <?= htmlspecialchars($viewSemester) ?>
                    </p>
                </div>

                <!-- Carga Horária por duração -->
                <div class="px-6 py-5 border-t border-gray-100 bg-gray-50">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-4">Carga Horária — <?= htmlspecialchars($viewTeacher) ?></h3>

                    <!-- Cartões: CH semanal média -->
                    <div class="flex divide-x divide-gray-200 mb-5">
                        <div class="<?= $hasFic ? 'pr-6' : '' ?>">
                            <p class="text-xs text-gray-400 mb-1"><?= $hasFic ? 'Regular' : 'CH semanal média' ?></p>
                            <p class="text-2xl font-bold text-gray-800"><?= number_format($chTotal / 20, 1) ?> <span class="text-sm font-normal text-gray-400">h/a/sem</span></p>
                            <p class="text-xs text-gray-400 mt-0.5"><?= $chTotal ?> h/a no semestre</p>
                        </div>
                        <?php if ($hasFic): ?>
                        <div class="px-6">
                            <p class="text-xs text-gray-400 mb-1">FIC</p>
                            <p class="text-2xl font-bold text-gray-800"><?= number_format($chTotalFic / 20, 1) ?> <span class="text-sm font-normal text-gray-400">h/a/sem</span></p>
                            <p class="text-xs text-gray-400 mt-0.5"><?= $chTotalFic ?> h/a no semestre</p>
                        </div>
                        <div class="pl-6">
                            <p class="text-xs text-gray-400 mb-1">Total</p>
                            <p class="text-2xl font-bold text-gray-900"><?= number_format($chGrand / 20, 1) ?> <span class="text-sm font-normal text-gray-400">h/a/sem</span></p>
                            <p class="text-xs text-gray-400 mt-0.5"><?= $chGrand ?> h/a no semestre</p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Grade D1–D4 -->
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Carga semanal por duração</p>
                        <?php
                        $durLabels = ['D1 (sem. 1–5)', 'D2 (sem. 6–10)', 'D3 (sem. 11–15)', 'D4 (sem. 16–20)'];
                        if (!$hasFic):
                        ?>
                        <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm max-w-xs">
                            <?php foreach ($durLabels as $di => $dl): ?>
                            <span class="text-gray-500"><?= $dl ?></span>
                            <span class="font-medium text-gray-800"><?= $chByDur[$di] ?> <span class="text-xs font-normal text-gray-400">h/a/sem</span></span>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-sm" style="display:grid;grid-template-columns:auto 5rem 4rem 5rem;gap:0.3rem 1.5rem">
                            <span></span>
                            <span class="text-xs text-gray-400 text-right">Regular</span>
                            <span class="text-xs text-gray-400 text-right">FIC</span>
                            <span class="text-xs text-gray-400 text-right font-semibold">Total</span>
                            <?php foreach ($durLabels as $di => $dl): ?>
                            <span class="text-gray-500"><?= $dl ?></span>
                            <span class="text-right text-gray-700"><?= $chByDur[$di] ?> <span class="text-xs text-gray-400">h/a/s</span></span>
                            <span class="text-right text-gray-700"><?= $chByDurFic[$di] ?> <span class="text-xs text-gray-400">h/a/s</span></span>
                            <span class="text-right font-semibold text-gray-900"><?= $chGrandDur[$di] ?> <span class="text-xs font-normal text-gray-400">h/a/s</span></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>


            <?php endif; ?>
        </div>

    </div>
</main>

<?php require_once 'layout/footer.php'; ?>
