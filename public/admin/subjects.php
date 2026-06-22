<?php
/**
 * Gerenciamento de Unidades Curriculares.
 * Admins veem e gerenciam todos os cursos; coordenadores veem e gerenciam apenas seus cursos.
 *
 * @author Prof. Thiago Paes
 */
require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../src/Auth.php';
require_once '../../src/Helpers.php';

Auth::check();
$user = Auth::user();

$db   = new Database();
$conn = $db->getConnection();

$roleStmt = $conn->prepare("SELECT is_course_bound, is_sysadmin FROM roles WHERE id = :id");
$roleStmt->execute([':id' => $user['user_role']]);
$roleData      = $roleStmt->fetch(PDO::FETCH_ASSOC);
$isCourseBound = (bool)$roleData['is_course_bound'];
$isSysAdmin    = (bool)$roleData['is_sysadmin'];

if (!$isCourseBound && !$isSysAdmin) {
    header('Location: dashboard.php');
    exit;
}

$userCourses = $user['user_courses'] ?? [];

$canManageCourse = function(int $cid) use ($isSysAdmin, $userCourses): bool {
    return $isSysAdmin || in_array($cid, array_map('intval', $userCourses));
};

$message = '';
$error   = '';

// --- CRUD ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $name      = trim($_POST['name'] ?? '');
        $course_id = intval($_POST['course_id'] ?? 0);
        $period    = trim($_POST['period'] ?? '') ?: null;
        $active    = isset($_POST['active']) ? 1 : 0;
        $isEad     = isset($_POST['is_ead']) ? 1 : 0;

        if (!$canManageCourse($course_id)) {
            header('Location: subjects.php');
            exit;
        }

        try {
            if ($action === 'create') {
                $stmt = $conn->prepare("INSERT INTO subjects (course_id, name, period, active, is_ead) VALUES (:course_id, :name, :period, :active, :is_ead)");
                $stmt->execute([':course_id' => $course_id, ':name' => $name, ':period' => $period, ':active' => $active, ':is_ead' => $isEad]);
                $message = 'Unidade Curricular criada com sucesso!';
            } else {
                $id = intval($_POST['id']);
                $ownerStmt = $conn->prepare("SELECT course_id FROM subjects WHERE id = :id");
                $ownerStmt->execute([':id' => $id]);
                if (!$canManageCourse((int)$ownerStmt->fetchColumn())) {
                    header('Location: subjects.php');
                    exit;
                }
                $stmt = $conn->prepare("UPDATE subjects SET course_id = :course_id, name = :name, period = :period, active = :active, is_ead = :is_ead WHERE id = :id");
                $stmt->execute([':course_id' => $course_id, ':name' => $name, ':period' => $period, ':active' => $active, ':is_ead' => $isEad, ':id' => $id]);
                $message = 'Unidade Curricular atualizada com sucesso!';
            }
        } catch (PDOException $e) {
            $error = 'Erro ao salvar: ' . $e->getMessage();
        }

    } elseif ($action === 'delete') {
        $id = intval($_POST['id']);
        $ownerStmt = $conn->prepare("SELECT course_id FROM subjects WHERE id = :id");
        $ownerStmt->execute([':id' => $id]);
        if (!$canManageCourse((int)$ownerStmt->fetchColumn())) {
            header('Location: subjects.php');
            exit;
        }
        try {
            $stmt = $conn->prepare("DELETE FROM subjects WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $message = 'Unidade Curricular excluída com sucesso!';
        } catch (PDOException $e) {
            $error = 'Erro ao excluir: verifique se a UC está vinculada a requerimentos.';
        }
    }
}

// --- Edição ---
$editingSubject = null;
if (isset($_GET['edit'])) {
    $editStmt = $conn->prepare("SELECT * FROM subjects WHERE id = :id");
    $editStmt->execute([':id' => intval($_GET['edit'])]);
    $editingSubject = $editStmt->fetch(PDO::FETCH_ASSOC);
    if ($editingSubject && !$canManageCourse((int)$editingSubject['course_id'])) {
        $editingSubject = null;
    }
}

// --- Cursos disponíveis (para form e filtro) ---
if ($isSysAdmin) {
    $courses = $conn->query("SELECT id, name FROM courses WHERE active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $inList  = empty($userCourses) ? '0' : implode(',', array_map('intval', $userCourses));
    $courses = $conn->query("SELECT id, name FROM courses WHERE id IN ($inList) ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
}

// --- Filtros ---
$filterCourse = intval($_GET['course'] ?? 0);
$filterPeriod = trim($_GET['period'] ?? '');
$filterSearch = trim($_GET['search'] ?? '');
$filterActive = $_GET['active'] ?? '';

$q = "SELECT s.*, c.name AS course_name
      FROM subjects s
      JOIN courses c ON c.id = s.course_id
      WHERE 1=1";
$p = [];

if ($isCourseBound && !$isSysAdmin) {
    $inList = empty($userCourses) ? '0' : implode(',', array_map('intval', $userCourses));
    $q .= " AND s.course_id IN ($inList)";
} elseif ($filterCourse > 0) {
    $q .= " AND s.course_id = :course";
    $p[':course'] = $filterCourse;
}
if ($filterPeriod !== '') {
    $q .= " AND s.period = :period";
    $p[':period'] = $filterPeriod;
}
if ($filterSearch !== '') {
    $q .= " AND s.name LIKE :search";
    $p[':search'] = "%$filterSearch%";
}
if ($filterActive !== '') {
    $q .= " AND s.active = :active";
    $p[':active'] = (int)$filterActive;
}
$q .= " ORDER BY c.name, s.period, s.name";

$stmt = $conn->prepare($q);
$stmt->execute($p);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Períodos distintos (para datalist e filtro) ---
$periodQ = "SELECT DISTINCT s.period FROM subjects s WHERE s.period IS NOT NULL";
if ($isCourseBound && !$isSysAdmin) {
    $inList2 = empty($userCourses) ? '0' : implode(',', array_map('intval', $userCourses));
    $periodQ .= " AND s.course_id IN ($inList2)";
}
$periodQ .= " ORDER BY s.period";
$allPeriods = $conn->query($periodQ)->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Unidades Curriculares';
require_once 'layout/header.php';
?>

<?php require_once 'layout/sidebar.php'; ?>

<main class="flex-1 overflow-y-auto p-8 bg-[#F2F4F8] dark:bg-gray-900">

    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100 tracking-tight">Unidades Curriculares</h1>
        <p class="text-gray-500 dark:text-gray-400 mt-1">Disciplinas por curso disponíveis nos requerimentos</p>
    </div>

    <?php if ($message): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6 text-sm font-medium flex items-center shadow-sm">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6 text-sm font-medium flex items-center shadow-sm">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Form de criação / edição -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 mb-6">
        <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-4 flex items-center">
            <svg class="w-5 h-5 mr-2 text-brand-DEFAULT" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
            </svg>
            <?= $editingSubject ? 'Editar Unidade Curricular' : 'Nova Unidade Curricular' ?>
        </h3>
        <form method="POST" class="space-y-4">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="<?= $editingSubject ? 'update' : 'create' ?>">
            <?php if ($editingSubject): ?>
                <input type="hidden" name="id" value="<?= $editingSubject['id'] ?>">
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nome da UC</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($editingSubject['name'] ?? '') ?>" required
                        class="w-full bg-gray-50 dark:bg-gray-700 dark:text-gray-100 border border-gray-200 dark:border-gray-600 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent outline-none transition-all">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Curso</label>
                    <select name="course_id" required
                        class="w-full bg-gray-50 dark:bg-gray-700 dark:text-gray-100 border border-gray-200 dark:border-gray-600 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent outline-none transition-all">
                        <option value="">Selecione o curso...</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($editingSubject['course_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Período / Fase</label>
                    <input type="text" name="period" value="<?= htmlspecialchars($editingSubject['period'] ?? '') ?>"
                        placeholder="Ex: 1º Semestre, 2º Nível"
                        list="period-suggestions"
                        autocomplete="off"
                        class="w-full bg-gray-50 dark:bg-gray-700 dark:text-gray-100 border border-gray-200 dark:border-gray-600 rounded-xl px-4 py-2 focus:ring-2 focus:ring-[#1CBB9B] focus:border-transparent outline-none transition-all">
                    <datalist id="period-suggestions">
                        <?php foreach ($allPeriods as $per): ?>
                            <option value="<?= htmlspecialchars($per) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
            </div>

            <div class="flex items-center justify-between pt-2">
                <div class="flex items-center gap-6">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" name="active" value="1"
                            <?= (!isset($editingSubject) || $editingSubject['active']) ? 'checked' : '' ?>
                            class="h-4 w-4 text-[#1CBB9B] focus:ring-[#1CBB9B] border-gray-300 rounded">
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Ativa (disponível nos requerimentos)</span>
                    </label>
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" name="is_ead" value="1"
                            <?= ($editingSubject['is_ead'] ?? 0) ? 'checked' : '' ?>
                            class="h-4 w-4 text-blue-500 focus:ring-blue-400 border-gray-300 rounded">
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">UC EAD <span class="text-gray-400 text-xs">(aulas pontuam 0,5× no Justiceiro do Tempo)</span></span>
                    </label>
                </div>
                <div class="flex gap-2">
                    <?php if ($editingSubject): ?>
                        <a href="subjects.php" class="px-6 py-2 rounded-xl font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-all">Cancelar</a>
                    <?php endif; ?>
                    <button type="submit"
                        class="bg-[#1CBB9B] text-white px-8 py-2 rounded-xl font-bold hover:bg-[#169C80] transition-all shadow-sm hover:shadow-md">
                        <?= $editingSubject ? 'Salvar Alterações' : 'Adicionar UC' ?>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Filtros + Listagem -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">

        <!-- Barra de filtros -->
        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
            <form method="GET" class="flex flex-wrap gap-3 items-end">
                <div class="flex-1 min-w-[160px]">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Buscar por nome</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($filterSearch) ?>"
                        placeholder="Nome da UC..."
                        class="w-full bg-gray-50 dark:bg-gray-700 dark:text-gray-100 border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-1.5 text-sm focus:ring-2 focus:ring-[#1CBB9B] outline-none">
                </div>
                <?php if ($isSysAdmin): ?>
                <div class="flex-1 min-w-[160px]">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Curso</label>
                    <select name="course"
                        class="w-full bg-gray-50 dark:bg-gray-700 dark:text-gray-100 border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-1.5 text-sm focus:ring-2 focus:ring-[#1CBB9B] outline-none">
                        <option value="">Todos os cursos</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $filterCourse == $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="flex-1 min-w-[140px]">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Período / Fase</label>
                    <select name="period"
                        class="w-full bg-gray-50 dark:bg-gray-700 dark:text-gray-100 border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-1.5 text-sm focus:ring-2 focus:ring-[#1CBB9B] outline-none">
                        <option value="">Todos os períodos</option>
                        <?php foreach ($allPeriods as $per): ?>
                            <option value="<?= htmlspecialchars($per) ?>" <?= $filterPeriod === $per ? 'selected' : '' ?>>
                                <?= htmlspecialchars($per) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="min-w-[110px]">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Status</label>
                    <select name="active"
                        class="w-full bg-gray-50 dark:bg-gray-700 dark:text-gray-100 border border-gray-200 dark:border-gray-600 rounded-xl px-3 py-1.5 text-sm focus:ring-2 focus:ring-[#1CBB9B] outline-none">
                        <option value="">Todas</option>
                        <option value="1" <?= $filterActive === '1' ? 'selected' : '' ?>>Ativas</option>
                        <option value="0" <?= $filterActive === '0' ? 'selected' : '' ?>>Inativas</option>
                    </select>
                </div>
                <div class="flex gap-2 items-end">
                    <a href="subjects.php" class="px-4 py-1.5 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 font-medium">Limpar</a>
                    <button type="submit" class="bg-[#1CBB9B] text-white px-5 py-1.5 rounded-xl text-sm font-bold hover:bg-[#169C80] transition-all">Filtrar</button>
                </div>
            </form>
        </div>

        <p class="px-6 py-2 text-xs text-gray-400 dark:text-gray-500 border-b border-gray-50 dark:border-gray-700/50">
            <?= count($subjects) ?> UC(s) encontrada(s)
        </p>

        <!-- Listagem agrupada por curso e período -->
        <?php if (empty($subjects)): ?>
            <p class="px-6 py-10 text-center text-gray-400 dark:text-gray-500 text-sm">Nenhuma UC encontrada com os filtros aplicados.</p>
        <?php else: ?>
            <?php
                $currentCourseName = null;
                $currentPeriod     = null;
            ?>
            <table class="min-w-full">
                <thead class="bg-gray-50/50 dark:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nome</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-[70px]">EAD</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-[90px]">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider w-[90px]">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                    <?php foreach ($subjects as $s): ?>

                        <?php if ($s['course_name'] !== $currentCourseName): ?>
                            <?php $currentCourseName = $s['course_name']; $currentPeriod = null; ?>
                            <tr class="bg-[#1CBB9B]/10 dark:bg-[#1CBB9B]/20">
                                <td colspan="4" class="px-6 py-2 text-sm font-bold text-[#169C80] dark:text-[#1CBB9B]">
                                    <?= htmlspecialchars($s['course_name']) ?>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php if ($s['period'] !== $currentPeriod): ?>
                            <?php $currentPeriod = $s['period']; ?>
                            <tr class="bg-gray-50/60 dark:bg-gray-700/30">
                                <td colspan="4" class="px-8 py-1.5 text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">
                                    <?= $s['period'] ? htmlspecialchars($s['period']) : 'Sem período definido' ?>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="px-6 py-3 pl-10 text-sm font-medium text-gray-900 dark:text-gray-100">
                                <?= htmlspecialchars($s['name']) ?>
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap text-sm">
                                <?php if ($s['is_ead']): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">EAD</span>
                                <?php else: ?>
                                    <span class="text-gray-300 dark:text-gray-600">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap text-sm">
                                <?php if ($s['active']): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Ativa</span>
                                <?php else: ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300">Inativa</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="subjects.php?edit=<?= $s['id'] ?>"
                                        class="text-blue-400 hover:text-blue-600 p-1.5 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-lg transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </a>
                                    <form method="POST" class="inline-block" onsubmit="return confirm('Excluir esta UC?');">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                        <button type="submit"
                                            class="text-red-400 hover:text-red-600 p-1.5 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>

                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</main>

<?php require_once 'layout/footer.php'; ?>
