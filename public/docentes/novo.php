<?php
require_once 'guard.php';
require_once '../../src/AscXmlParser.php';

$pageTitle = 'Nova Solicitação de Substituição';

// Cursos ativos
$stmtCourses = $conn->prepare("SELECT id, name FROM courses WHERE active = 1 ORDER BY name");
$stmtCourses->execute();
$courses = $stmtCourses->fetchAll(PDO::FETCH_ASSOC);

// Docentes ativos (para sugestão de substituto)
$stmtTeachers = $conn->prepare("SELECT id, name FROM teachers WHERE active = 1 ORDER BY name");
$stmtTeachers->execute();
$teachers = $stmtTeachers->fetchAll(PDO::FETCH_ASSOC);

// UCs por curso (para datalist dinâmico via JS)
$stmtSubjects = $conn->prepare("SELECT id, course_id, name FROM subjects WHERE active = 1 ORDER BY name");
$stmtSubjects->execute();
$subjectsRaw = $stmtSubjects->fetchAll(PDO::FETCH_ASSOC);
$subjectsByCourse = [];
foreach ($subjectsRaw as $s) {
    $subjectsByCourse[$s['course_id']][] = $s['name'];
}

// Semestre atual para API de sugestão
$currentMonth    = (int)date('n');
$currentYear     = date('Y');
$currentSemester = $currentYear . '.' . ($currentMonth >= 2 && $currentMonth <= 7 ? '1' : '2');

// Turmas do semestre atual para datalist
$stmtClasses = $conn->prepare("SELECT DISTINCT class_group FROM schedule_slots WHERE semester = :sem ORDER BY class_group");
$stmtClasses->execute([':sem' => $currentSemester]);
$classGroups = $stmtClasses->fetchAll(PDO::FETCH_COLUMN);

require_once 'layout/header.php';
require_once 'layout/sidebar.php';
?>

<main class="flex-1 overflow-y-auto p-8">
    <div class="max-w-2xl mx-auto">

        <div class="mb-8">
            <a href="dashboard.php" class="text-sm text-gray-400 hover:text-gray-600 inline-flex items-center gap-1 mb-4">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Minhas Solicitações
            </a>
            <h1 class="text-2xl font-bold text-gray-900">Nova Solicitação de Substituição</h1>
            <p class="text-sm text-gray-500 mt-1">Preencha os dados para solicitar substituição de suas aulas</p>
        </div>

        <!-- Info box -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 text-sm text-blue-800">
            <p class="font-semibold mb-1">Como funciona a substituição</p>
            <p>A substituição ideal é por um docente que já tem aula com a mesma turma em outro horário —
               assim ele poderá devolver a aula naturalmente. Ao enviar esta solicitação, a coordenação
               do curso avaliará e, se necessário, encontrará um substituto adequado.</p>
        </div>

        <form method="POST" action="submit.php" class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 space-y-6">
            <?= Csrf::field() ?>

            <!-- Curso -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Curso <span class="text-red-500">*</span>
                </label>
                <select name="course_id" id="course_id" required
                    class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-brand-DEFAULT focus:border-transparent outline-none bg-gray-50">
                    <option value="">Selecione o curso da turma...</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Turma -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Turma</label>
                <input type="text" name="class_group" id="class_group" list="class_groups_list"
                    placeholder="Ex: INF 3 - 2025"
                    class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-brand-DEFAULT focus:border-transparent outline-none bg-gray-50">
                <datalist id="class_groups_list">
                    <?php foreach ($classGroups as $cg): ?>
                        <option value="<?= htmlspecialchars($cg) ?>">
                    <?php endforeach; ?>
                </datalist>
                <p class="text-xs text-gray-400 mt-1">Identificação da turma afetada</p>
            </div>

            <!-- UC -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Unidade Curricular (UC)</label>
                <input type="text" name="subject_name" id="subject_name" list="subjects_list"
                    placeholder="Nome da UC..."
                    class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-brand-DEFAULT focus:border-transparent outline-none bg-gray-50">
                <datalist id="subjects_list"></datalist>
                <p class="text-xs text-gray-400 mt-1">Selecione o curso acima para sugestões automáticas</p>
            </div>

            <!-- Datas de ausência -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Datas de ausência <span class="text-red-500">*</span>
                </label>
                <div id="dates_container" class="space-y-2">
                    <div class="flex items-center gap-2">
                        <input type="date" name="absence_dates[]" required
                            min="<?= date('Y-m-d') ?>"
                            class="date-input flex-1 border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-brand-DEFAULT focus:border-transparent outline-none bg-gray-50">
                        <span class="dow-label text-xs text-gray-500 font-medium w-32 shrink-0"></span>
                        <button type="button" class="remove-date hidden text-red-400 hover:text-red-600 p-1">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                <button type="button" id="add_date"
                    class="mt-2 text-sm text-brand-DEFAULT hover:text-brand-dark font-medium inline-flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Adicionar outra data
                </button>
            </div>

            <!-- Turnos -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Turno(s) de aula afetado(s) <span class="text-red-500">*</span>
                </label>
                <div class="grid grid-cols-2 gap-2">
                    <?php foreach (AscXmlParser::TIME_SLOT_LABELS as $code => $label): ?>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="time_slots[]" value="<?= $code ?>"
                                class="time-slot-cb w-4 h-4 rounded border-gray-300 text-brand-DEFAULT focus:ring-brand-DEFAULT">
                            <span class="text-sm text-gray-700"><?= htmlspecialchars($label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Painel de disponibilidade de substitutos -->
            <div id="suggest_box" class="hidden">
                <div class="border border-gray-200 rounded-lg overflow-hidden text-sm">
                    <div class="bg-gray-50 px-4 py-2 border-b border-gray-200">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Docentes da turma — clique para selecionar como candidato</p>
                    </div>
                    <div id="sg_busy" class="hidden px-4 py-3 border-b border-gray-100">
                        <p class="text-xs font-semibold text-red-500 mb-1">Ocupados neste horário</p>
                        <p class="text-xs text-gray-400 mb-2">Podem ser selecionados mesmo assim — o sistema enviará e-mail para avaliar disponibilidade</p>
                        <div id="sg_busy_list" class="flex flex-wrap gap-2"></div>
                    </div>
                    <div id="sg_devolutiva" class="hidden px-4 py-3 border-b border-gray-100 bg-green-50">
                        <p class="text-xs font-semibold text-green-700 mb-0.5">Devolutiva facilitada</p>
                        <p class="text-xs text-green-600 mb-2">Livres agora e já ensinam esta turma em outro horário</p>
                        <div id="sg_devolutiva_list" class="flex flex-wrap gap-2"></div>
                    </div>
                    <div id="sg_free" class="hidden px-4 py-3">
                        <p class="text-xs font-semibold text-green-600 mb-2">Disponíveis neste horário</p>
                        <div id="sg_free_list" class="flex flex-wrap gap-2"></div>
                    </div>
                </div>
            </div>

            <!-- Candidatos selecionados -->
            <div id="candidates_section" class="hidden">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Candidatos a substituto selecionados</label>
                <div id="candidates_list" class="flex flex-wrap gap-2 mb-2"></div>
                <div id="candidates_inputs"></div>
                <p class="text-xs text-gray-400">Os docentes selecionados receberão um e-mail e o primeiro a aceitar ficará responsável pela substituição.</p>
            </div>

            <!-- Motivo -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Motivo da ausência <span class="text-red-500">*</span>
                </label>
                <textarea name="reason" required rows="3" placeholder="Descreva brevemente o motivo..."
                    class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-brand-DEFAULT focus:border-transparent outline-none bg-gray-50 resize-none"></textarea>
            </div>

            <!-- Declaração de devolutiva -->
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" name="devolutiva_agreed" value="1" required
                        class="mt-0.5 w-4 h-4 rounded border-gray-300 text-brand-DEFAULT focus:ring-brand-DEFAULT flex-shrink-0">
                    <span class="text-sm text-amber-900">
                        <strong>Declaro ciência da devolutiva:</strong> a pessoa que realizar a substituição
                        deverá ter a aula devolvida, e me comprometo a organizar o retorno desta carga horária
                        conforme orientação da coordenação de curso.
                    </span>
                </label>
            </div>

            <!-- Botões -->
            <div class="flex items-center justify-between pt-2">
                <a href="dashboard.php" class="text-sm text-gray-500 hover:text-gray-700">Cancelar</a>
                <button type="submit" id="submit_btn"
                    class="px-8 py-3 bg-brand-DEFAULT text-white font-semibold rounded-lg hover:bg-brand-dark transition-colors shadow-sm">
                    Enviar Solicitação
                </button>
            </div>

        </form>
    </div>
</main>

<script>
// Datalist de UCs por curso
const subjectsByCourse = <?= json_encode($subjectsByCourse, JSON_UNESCAPED_UNICODE) ?>;
const currentSemester  = <?= json_encode($currentSemester) ?>;

document.getElementById('course_id').addEventListener('change', function () {
    const courseId = this.value;
    const datalist = document.getElementById('subjects_list');
    datalist.innerHTML = '';
    if (courseId && subjectsByCourse[courseId]) {
        subjectsByCourse[courseId].forEach(function (name) {
            const opt = document.createElement('option');
            opt.value = name;
            datalist.appendChild(opt);
        });
    }
});

// ─── Dia da semana ao lado das datas ───────────────────────────────────────
const DOW_PT = ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'];

function updateDow(input) {
    const span = input.closest('div').querySelector('.dow-label');
    if (!span) return;
    if (!input.value) { span.textContent = ''; return; }
    const d = new Date(input.value + 'T12:00:00');
    span.textContent = DOW_PT[d.getDay()];
}

document.querySelectorAll('.date-input').forEach(inp => {
    inp.addEventListener('change', () => updateDow(inp));
});

// ─── Seleção múltipla de candidatos ────────────────────────────────────────
const selected = new Map(); // teacher_name → {teacher_name, teacher_email}

function toggleCandidate(teacher, btn, selectedClass, normalClass) {
    if (selected.has(teacher.teacher_name)) {
        selected.delete(teacher.teacher_name);
        btn.className = btn.className.replace(selectedClass, normalClass);
        btn.dataset.selected = '';
    } else {
        selected.set(teacher.teacher_name, teacher);
        btn.className = btn.className.replace(normalClass, selectedClass);
        btn.dataset.selected = '1';
    }
    updateCandidatesUI();
}

function updateCandidatesUI() {
    const section = document.getElementById('candidates_section');
    const list    = document.getElementById('candidates_list');
    const inputs  = document.getElementById('candidates_inputs');
    list.innerHTML = ''; inputs.innerHTML = '';

    if (selected.size === 0) { section.classList.add('hidden'); return; }
    section.classList.remove('hidden');

    for (const [, t] of selected) {
        const tag = document.createElement('span');
        tag.className   = 'inline-flex items-center gap-1 bg-brand-DEFAULT text-white text-xs px-2.5 py-1 rounded-full font-medium';
        tag.textContent = t.teacher_name;
        list.appendChild(tag);

        const inp1 = document.createElement('input');
        inp1.type = 'hidden'; inp1.name = 'candidate_names[]'; inp1.value = t.teacher_name;
        const inp2 = document.createElement('input');
        inp2.type = 'hidden'; inp2.name = 'candidate_emails[]'; inp2.value = t.teacher_email || '';
        inputs.appendChild(inp1); inputs.appendChild(inp2);
    }
}

// ─── Painel de disponibilidade ─────────────────────────────────────────────
let suggestTimer = null;

function fetchSuggestions() {
    const classGroup   = document.getElementById('class_group').value.trim();
    const checkedSlots = Array.from(document.querySelectorAll('.time-slot-cb:checked')).map(cb => cb.value);
    const box = document.getElementById('suggest_box');
    if (!classGroup || checkedSlots.length === 0) { box.classList.add('hidden'); return; }

    const params = new URLSearchParams({ class_group: classGroup, semester: currentSemester });
    checkedSlots.forEach(s => params.append('time_slots[]', s));

    fetch('api_suggest.php?' + params.toString())
        .then(r => r.json())
        .then(data => {
            const busy        = data.busy  || [];
            const freeWithDev = (data.free || []).filter(t => t.devolutiva);
            const freeOther   = (data.free || []).filter(t => !t.devolutiva);
            if (!busy.length && !freeWithDev.length && !freeOther.length) {
                box.classList.add('hidden'); return;
            }
            // Preservar seleções existentes ao re-renderizar
            renderSection('sg_busy',       'sg_busy_list',       busy,        buildBusy);
            renderSection('sg_devolutiva', 'sg_devolutiva_list', freeWithDev, buildDevolutiva);
            renderSection('sg_free',       'sg_free_list',       freeOther,   buildFree);
            box.classList.remove('hidden');
        })
        .catch(() => box.classList.add('hidden'));
}

function renderSection(sectionId, listId, items, buildFn) {
    const section = document.getElementById(sectionId);
    const list    = document.getElementById(listId);
    list.innerHTML = '';
    if (!items.length) { section.classList.add('hidden'); return; }
    items.forEach(t => list.appendChild(buildFn(t)));
    section.classList.remove('hidden');
}

function buildBusy(t) {
    const normalClass    = 'bg-red-100 text-red-700';
    const selectedClass  = 'bg-red-600 text-white';
    const b = document.createElement('button');
    b.type      = 'button';
    b.className = 'text-xs px-2.5 py-1 rounded-full cursor-pointer font-medium transition-colors ' + (selected.has(t.teacher_name) ? selectedClass : normalClass);
    b.textContent = t.teacher_name;
    if (selected.has(t.teacher_name)) b.dataset.selected = '1';
    b.addEventListener('click', () => toggleCandidate(t, b, selectedClass, normalClass));
    return b;
}

function buildDevolutiva(t) {
    const normalClass   = 'bg-green-100 text-green-800 border border-green-200';
    const selectedClass = 'bg-green-600 text-white border border-green-600';
    const b = document.createElement('button');
    b.type      = 'button';
    b.className = 'text-xs px-2.5 py-1 rounded-full cursor-pointer font-medium transition-colors ' + (selected.has(t.teacher_name) ? selectedClass : normalClass);
    b.textContent = t.teacher_name + ' ★';
    if (t.devolutiva_info) b.title = 'Ensina esta turma em: ' + t.devolutiva_info;
    if (selected.has(t.teacher_name)) b.dataset.selected = '1';
    b.addEventListener('click', () => toggleCandidate(t, b, selectedClass, normalClass));
    return b;
}

function buildFree(t) {
    const normalClass   = 'bg-white text-green-700 border border-green-300';
    const selectedClass = 'bg-green-600 text-white border border-green-600';
    const b = document.createElement('button');
    b.type      = 'button';
    b.className = 'text-xs px-2.5 py-1 rounded-full cursor-pointer font-medium transition-colors ' + (selected.has(t.teacher_name) ? selectedClass : normalClass);
    b.textContent = t.teacher_name;
    if (selected.has(t.teacher_name)) b.dataset.selected = '1';
    b.addEventListener('click', () => toggleCandidate(t, b, selectedClass, normalClass));
    return b;
}

document.getElementById('class_group').addEventListener('input', function () {
    clearTimeout(suggestTimer);
    suggestTimer = setTimeout(fetchSuggestions, 400);
});
document.querySelectorAll('.time-slot-cb').forEach(function (cb) {
    cb.addEventListener('change', function () {
        clearTimeout(suggestTimer);
        suggestTimer = setTimeout(fetchSuggestions, 200);
    });
});

// ─── Adicionar/remover datas ───────────────────────────────────────────────
document.getElementById('add_date').addEventListener('click', function () {
    const container = document.getElementById('dates_container');
    const div = document.createElement('div');
    div.className = 'flex items-center gap-2';
    div.innerHTML = `
        <input type="date" name="absence_dates[]" min="${new Date().toISOString().split('T')[0]}"
            class="date-input flex-1 border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-brand-DEFAULT focus:border-transparent outline-none bg-gray-50">
        <span class="dow-label text-xs text-gray-500 font-medium w-32 shrink-0"></span>
        <button type="button" class="remove-date text-red-400 hover:text-red-600 p-1">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    `;
    container.appendChild(div);
    const newInput = div.querySelector('.date-input');
    newInput.addEventListener('change', () => updateDow(newInput));
    div.querySelector('.remove-date').addEventListener('click', function () {
        container.removeChild(div);
    });
});

// ─── Validação e submit ────────────────────────────────────────────────────
document.getElementById('submit_btn').addEventListener('click', function (e) {
    const slots = document.querySelectorAll('input[name="time_slots[]"]:checked');
    if (slots.length === 0) {
        e.preventDefault();
        alert('Selecione ao menos um turno de aula afetado.');
        return;
    }
});

document.querySelector('form').addEventListener('submit', function () {
    const btn = document.getElementById('submit_btn');
    btn.disabled = true;
    btn.textContent = 'Enviando...';
});
</script>

<?php require_once 'layout/footer.php'; ?>
