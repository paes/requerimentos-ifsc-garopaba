<?php
require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../src/GuardianTermPdf.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Endpoint AJAX: gera o HTML do termo para impressão/PDF
if (isset($_GET['action']) && $_GET['action'] === 'gerar_termo') {
    header('Content-Type: text/html; charset=utf-8');
    $students = [];
    $raw = json_decode($_POST['students_json'] ?? '[]', true);
    if (is_array($raw)) {
        foreach ($raw as $s) {
            $students[] = [
                'name'      => trim($s['name'] ?? ''),
                'matricula' => trim($s['matricula'] ?? ''),
            ];
        }
    }
    echo GuardianTermPdf::render([
        'name'     => trim($_POST['guardian_name'] ?? ''),
        'email'    => trim($_POST['guardian_email'] ?? ''),
        'cpf'      => trim($_POST['guardian_cpf'] ?? ''),
        'phone'    => trim($_POST['guardian_phone'] ?? ''),
        'students' => $students,
    ]);
    exit;
}

$error   = '';
$success = '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <script>document.documentElement.setAttribute('data-theme', localStorage.getItem('req_theme') || 'default')</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Responsável — IFSC Garopaba</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/themes.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/tailwind.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/favicon.ico" type="image/x-icon">
    <script src="<?= BASE_URL ?>/assets/js/theme.js"></script>
</head>
<body class="bg-[#F2F4F8] min-h-screen py-10">

<div class="max-w-2xl mx-auto px-4">

    <!-- Cabeçalho -->
    <div class="text-center mb-8">
        <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="IFSC Logo" class="h-12 mx-auto mb-4">
        <h1 class="text-2xl font-bold text-gray-800">Cadastro de Responsável</h1>
        <p class="text-gray-500 mt-1 text-sm">
            Portal de Responsáveis — IFSC Câmpus Garopaba<br>
            <span class="text-xs">Exclusivo para responsáveis legais de alunos menores de idade</span>
        </p>
    </div>

    <!-- Aviso inicial -->
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6 text-sm text-amber-800">
        <p class="font-semibold mb-1">Antes de preencher, leia com atenção:</p>
        <ol class="list-decimal ml-4 space-y-1">
            <li>O <strong>e-mail informado deve ser o mesmo cadastrado no SIGAA</strong> como e-mail de responsável do aluno.</li>
            <li>Após preencher, você baixará um <strong>Termo de Autorização</strong>, deverá assiná-lo digitalmente via <strong>gov.br</strong> e enviar o arquivo assinado aqui.</li>
            <li>Seu acesso só será liberado após <strong>análise pela Coordenação Pedagógica</strong>.</li>
        </ol>
    </div>

    <form id="form-cadastro" method="POST" action="submit_cadastro.php" enctype="multipart/form-data" class="space-y-6">

        <!-- Dados do responsável -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-base font-bold text-gray-700 mb-4">Seus dados</h2>
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nome completo <span class="text-red-500">*</span></label>
                    <input type="text" name="guardian_name" id="guardian_name" required maxlength="255"
                        class="w-full bg-gray-50 border border-gray-200 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-brand-DEFAULT focus:border-transparent outline-none text-sm"
                        placeholder="Seu nome completo">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">CPF <span class="text-red-500">*</span></label>
                        <input type="text" name="guardian_cpf" id="guardian_cpf" required maxlength="14"
                            class="w-full bg-gray-50 border border-gray-200 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-brand-DEFAULT focus:border-transparent outline-none text-sm"
                            placeholder="000.000.000-00">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Telefone / WhatsApp <span class="text-red-500">*</span></label>
                        <input type="text" name="guardian_phone" id="guardian_phone" required maxlength="20"
                            class="w-full bg-gray-50 border border-gray-200 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-brand-DEFAULT focus:border-transparent outline-none text-sm"
                            placeholder="(48) 99999-9999">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">E-mail <span class="text-red-500">*</span></label>
                    <input type="email" name="guardian_email" id="guardian_email" required maxlength="255"
                        class="w-full bg-gray-50 border border-gray-200 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-brand-DEFAULT focus:border-transparent outline-none text-sm"
                        placeholder="Mesmo e-mail cadastrado no SIGAA como responsável">
                    <p class="text-xs text-gray-400 mt-1">Este será seu login no portal. Deve coincidir com o que está no SIGAA.</p>
                </div>
            </div>
        </div>

        <!-- Alunos -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-base font-bold text-gray-700">Alunos sob sua responsabilidade</h2>
                <button type="button" id="btn-add-student"
                    class="text-sm bg-brand-DEFAULT/10 text-brand-DEFAULT hover:bg-brand-DEFAULT/20 font-semibold px-3 py-1.5 rounded-lg transition-colors">
                    + Adicionar aluno
                </button>
            </div>
            <p class="text-xs text-gray-400 mb-4">Informe o nome completo e a matrícula de cada aluno. A matrícula está no boletim ou no SIGAA.</p>

            <div id="students-container" class="space-y-3">
                <!-- Bloco inicial -->
                <div class="student-row flex gap-3 items-start">
                    <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <input type="text" name="student_name[]" required maxlength="255"
                            placeholder="Nome completo do aluno"
                            class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-DEFAULT outline-none">
                        <input type="text" name="student_matricula[]" required maxlength="50"
                            placeholder="Matrícula (ex: 20241234)"
                            class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-DEFAULT outline-none">
                    </div>
                    <button type="button" class="btn-remove-student mt-1 text-gray-300 hover:text-red-400 transition-colors hidden">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Termo e assinatura -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-base font-bold text-gray-700 mb-4">Termo de Autorização e Assinatura</h2>

            <div class="space-y-4">
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-sm text-gray-600">
                    <p class="font-medium text-gray-700 mb-2">Passo a passo:</p>
                    <ol class="list-decimal ml-4 space-y-1.5">
                        <li>Preencha os campos acima</li>
                        <li>Clique em <strong>"Gerar e Baixar Termo"</strong> — o documento abrirá em nova aba para você imprimir/salvar como PDF</li>
                        <li>Acesse o portal <strong>gov.br</strong> e assine o PDF digitalmente</li>
                        <li>Volte aqui e anexe o PDF assinado no campo abaixo</li>
                        <li>Clique em <strong>"Enviar Cadastro"</strong></li>
                    </ol>
                </div>

                <button type="button" id="btn-gerar-termo"
                    class="w-full bg-gray-700 text-white py-3 rounded-lg hover:bg-gray-800 transition-colors font-semibold text-sm">
                    Gerar e Baixar Termo (PDF)
                </button>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        PDF assinado via gov.br <span class="text-red-500">*</span>
                    </label>
                    <input type="file" name="signed_pdf" id="signed_pdf" accept=".pdf" required
                        class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-brand-DEFAULT/10 file:text-brand-DEFAULT hover:file:bg-brand-DEFAULT/20 cursor-pointer">
                    <p class="text-xs text-gray-400 mt-1">Somente arquivos PDF. Tamanho máximo: 5 MB.</p>
                </div>
            </div>
        </div>

        <!-- Declaração -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="declaration" id="declaration" required class="mt-0.5 rounded border-gray-300 text-brand-DEFAULT focus:ring-brand-DEFAULT">
                <span class="text-sm text-gray-600">
                    Declaro que os dados informados são verdadeiros, que sou responsável legal pelos alunos listados e
                    que estou ciente das condições de uso do Portal de Responsáveis do IFSC Câmpus Garopaba.
                </span>
            </label>
        </div>

        <button type="submit" id="btn-submit"
            class="w-full bg-brand-DEFAULT text-white py-4 rounded-xl hover:bg-brand-dark transition-colors font-bold text-base shadow-lg">
            Enviar Cadastro
        </button>

        <p class="text-center text-sm text-gray-400">
            Já possui acesso?
            <a href="login.php" class="text-brand-DEFAULT hover:underline font-medium">Entrar no portal</a>
        </p>

    </form>
</div>

<script>
// Máscara CPF
document.getElementById('guardian_cpf').addEventListener('input', function () {
    let v = this.value.replace(/\D/g, '').slice(0, 11);
    if (v.length > 9) v = v.replace(/^(\d{3})(\d{3})(\d{3})(\d{0,2})/, '$1.$2.$3-$4');
    else if (v.length > 6) v = v.replace(/^(\d{3})(\d{3})(\d{0,3})/, '$1.$2.$3');
    else if (v.length > 3) v = v.replace(/^(\d{3})(\d{0,3})/, '$1.$2');
    this.value = v;
});

// Adicionar/remover linhas de aluno
document.getElementById('btn-add-student').addEventListener('click', function () {
    const container = document.getElementById('students-container');
    const tpl = container.querySelector('.student-row').cloneNode(true);
    tpl.querySelectorAll('input').forEach(i => i.value = '');
    tpl.querySelector('.btn-remove-student').classList.remove('hidden');
    container.appendChild(tpl);
    updateRemoveButtons();
});

document.getElementById('students-container').addEventListener('click', function (e) {
    const btn = e.target.closest('.btn-remove-student');
    if (!btn) return;
    const rows = document.querySelectorAll('.student-row');
    if (rows.length > 1) { btn.closest('.student-row').remove(); updateRemoveButtons(); }
});

function updateRemoveButtons() {
    const rows = document.querySelectorAll('.student-row');
    rows.forEach(r => {
        const btn = r.querySelector('.btn-remove-student');
        btn.classList.toggle('hidden', rows.length === 1);
    });
}

// Gerar termo (abre nova aba com HTML printável)
document.getElementById('btn-gerar-termo').addEventListener('click', function () {
    const name    = document.getElementById('guardian_name').value.trim();
    const cpf     = document.getElementById('guardian_cpf').value.trim();
    const email   = document.getElementById('guardian_email').value.trim();
    const phone   = document.getElementById('guardian_phone').value.trim();

    if (!name || !cpf || !email) {
        alert('Preencha seu nome, CPF e e-mail antes de gerar o termo.');
        return;
    }

    const students = [];
    document.querySelectorAll('.student-row').forEach(row => {
        const n = row.querySelectorAll('input')[0].value.trim();
        const m = row.querySelectorAll('input')[1].value.trim();
        if (n || m) students.push({ name: n, matricula: m });
    });

    if (students.length === 0) {
        alert('Informe ao menos um aluno antes de gerar o termo.');
        return;
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'cadastro.php?action=gerar_termo';
    form.target = '_blank';

    const fields = { guardian_name: name, guardian_cpf: cpf, guardian_email: email, guardian_phone: phone, students_json: JSON.stringify(students) };
    for (const [k, v] of Object.entries(fields)) {
        const inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = k; inp.value = v;
        form.appendChild(inp);
    }
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
});

// Disable submit duplo
document.getElementById('form-cadastro').addEventListener('submit', function () {
    const btn = document.getElementById('btn-submit');
    btn.disabled = true;
    btn.textContent = 'Enviando...';
});
</script>
</body>
</html>
