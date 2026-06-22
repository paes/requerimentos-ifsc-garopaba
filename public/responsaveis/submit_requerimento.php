<?php
require_once 'guard.php';
require_once '../../src/EmailService.php';
require_once '../../src/EmailTemplate.php';
require_once '../../src/CryptoHelper.php';
require_once '../../src/Helpers.php';

$action = $_GET['action'] ?? '';

// CSRF: todas as ações deste handler mudam estado e chegam via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
}

// ===================================================================
// ACTION: solicitar_otp — valida form, gera OTP, guarda sessão, envia e-mail
// ===================================================================
if ($action === 'solicitar_otp' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $studentName   = trim($_POST['student_name'] ?? '');
    $courseId      = (int)($_POST['course_id'] ?? 0);
    $requestTypeId = (int)($_POST['request_type_id'] ?? 0);
    $description   = trim($_POST['description'] ?? '');

    if (!$studentName || !$courseId || !$requestTypeId || !$description) {
        header('Location: novo_requerimento.php?error=campos');
        exit;
    }

    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    // Guarda na sessão: form_data (para reprocessar no confirmar), OTP e meta
    // Nota: NÃO inclui telefone (buscado do banco no confirmar, evita expor dado decriptado na sessão)
    $_SESSION['guardian_pending_request'] = [
        'form_data' => $_POST,
        'otp'       => $otp,
        'expires'   => time() + 600,
        'attempts'  => 0,
    ];

    // Envia e-mail com o código
    $db   = new Database();
    $conn = $db->getConnection();
    $emailService = new EmailService($conn);
    $safeGuardianName = htmlspecialchars($guardianName);

    $body = EmailTemplate::wrap("
        <h2 style='color:#006633;'>Código de confirmação — Requerimento</h2>
        <p>Olá, <strong>{$safeGuardianName}</strong>!</p>
        <p>Você está prestes a protocolar um requerimento pelo Portal de Responsáveis do IFSC Câmpus Garopaba.</p>
        <p>Use o código abaixo para confirmar o envio:</p>
        <div style='text-align:center;margin:24px 0;'>
            <span style='font-family:monospace;font-size:40px;font-weight:bold;color:#006633;letter-spacing:0.4em;
                         background:#f0fdf4;padding:16px 28px;border-radius:8px;border:2px solid #86efac;display:inline-block;'>
                {$otp}
            </span>
        </div>
        <p style='color:#555;font-size:13px;'>Válido por <strong>10 minutos</strong>. Não compartilhe este código com ninguém.</p>
        <p style='color:#999;font-size:11px;margin-top:16px;'>Se você não solicitou este código, ignore este e-mail.</p>
    ");
    $emailService->queue($guardianEmail, $guardianName, 'Código de confirmação — Requerimento IFSC Garopaba', $body);

    header('Location: confirmar_otp.php');
    exit;
}

// ===================================================================
// ACTION: reenviar_otp
// ===================================================================
if ($action === 'reenviar_otp') {
    if (empty($_SESSION['guardian_pending_request'])) {
        header('Location: novo_requerimento.php');
        exit;
    }

    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['guardian_pending_request']['otp']      = $otp;
    $_SESSION['guardian_pending_request']['expires']  = time() + 600;
    $_SESSION['guardian_pending_request']['attempts'] = 0;

    $db   = new Database();
    $conn = $db->getConnection();
    $emailService = new EmailService($conn);
    $safeGuardianName = htmlspecialchars($guardianName);

    $body = EmailTemplate::wrap("
        <h2 style='color:#006633;'>Novo código de confirmação</h2>
        <p>Olá, <strong>{$safeGuardianName}</strong>!</p>
        <p>Seu novo código é:</p>
        <div style='text-align:center;margin:24px 0;'>
            <span style='font-family:monospace;font-size:40px;font-weight:bold;color:#006633;letter-spacing:0.4em;
                         background:#f0fdf4;padding:16px 28px;border-radius:8px;border:2px solid #86efac;display:inline-block;'>
                {$otp}
            </span>
        </div>
        <p style='color:#555;font-size:13px;'>Válido por <strong>10 minutos</strong>.</p>
    ");
    $emailService->queue($guardianEmail, $guardianName, 'Código de confirmação — Requerimento IFSC Garopaba', $body);

    header('Location: confirmar_otp.php?reenviado=1');
    exit;
}

// ===================================================================
// ACTION: confirmar — valida OTP e submete o requerimento
// ===================================================================
if ($action === 'confirmar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pending = $_SESSION['guardian_pending_request'] ?? null;

    if (!$pending) {
        header('Location: novo_requerimento.php?error=sessao');
        exit;
    }

    if (time() > $pending['expires']) {
        unset($_SESSION['guardian_pending_request']);
        header('Location: confirmar_otp.php?error=expirado');
        exit;
    }

    if ($pending['attempts'] >= 5) {
        unset($_SESSION['guardian_pending_request']);
        header('Location: confirmar_otp.php?error=limite');
        exit;
    }

    if (trim($_POST['otp_code'] ?? '') !== $pending['otp']) {
        $_SESSION['guardian_pending_request']['attempts']++;
        header('Location: confirmar_otp.php?error=invalido');
        exit;
    }

    // OTP válido — gravar no banco
    $formData = $pending['form_data'];

    try {
        $db   = new Database();
        $conn = $db->getConnection();
        $conn->beginTransaction();

        // Busca telefone do responsável diretamente do banco (já criptografado)
        $gStmt = $conn->prepare("SELECT phone FROM guardians WHERE id = :id");
        $gStmt->execute([':id' => $guardianId]);
        $guardianPhoneEncrypted = $gStmt->fetchColumn();

        $protocolCode  = Helpers::generateProtocolCode($conn);
        $studentName   = $formData['student_name'] ?? '';
        $studentId     = $formData['student_id'] ?? '';
        $courseId      = $formData['course_id'] ?? 0;
        $classInfo     = $formData['class_info'] ?? null;
        $requestTypeId = $formData['request_type_id'] ?? 0;
        $description   = $formData['description'] ?? '';
        $requestTypeIdInt = (int)$requestTypeId;

        $startDateDB   = null;
        $endDateDB     = null;
        $courseUnitsDB = null;

        // Legado tipo 1: datas e UCs inline na descrição
        if ($requestTypeIdInt === 1) {
            $startDate   = $formData['start_date'] ?? '';
            $endDate     = $formData['end_date'] ?? '';
            $selSubjects = $formData['selected_subjects'] ?? [];
            $subjectsText = is_array($selSubjects) ? implode(', ', $selSubjects) : $selSubjects;
            if ($startDate && $endDate) {
                $startDateDB   = $startDate;
                $endDateDB     = $endDate;
                $courseUnitsDB = $subjectsText;
                $days = ['Domingo','Segunda-Feira','Terça-Feira','Quarta-Feira','Quinta-Feira','Sexta-Feira','Sábado'];
                $sObj = new DateTime($startDate);
                $eObj = new DateTime($endDate);
                $description .= "\n\nData Início: " . $sObj->format('d/m/Y') . ' (' . $days[$sObj->format('w')] . ')';
                $description .= "\nData Final: "   . $eObj->format('d/m/Y')   . ' (' . $days[$eObj->format('w')] . ')';
                if ($subjectsText) $description .= "\nUnidades Curriculares: " . $subjectsText;
            }
        }

        // extra_fields JSON — idêntico ao submit_request.php
        $extra = [];
        switch ($requestTypeIdInt) {
            case 1:
                $extra['start_date']        = $formData['start_date'] ?? '';
                $extra['end_date']          = $formData['end_date'] ?? '';
                $extra['selected_subjects'] = $formData['selected_subjects'] ?? [];
                break;
            case 2:
                $extra['selected_subjects']    = $formData['selected_subjects'] ?? [];
                $extra['selected_teachers']    = $formData['selected_teachers'] ?? [];
                $extra['uc_other_name']        = trim($formData['uc_other_name'] ?? '');
                $extra['teacher_other_name']   = $formData['teacher_other_name'] ?? '';
                $extra['also_justify_absence'] = isset($formData['also_justify_absence']);
                break;
            case 4:
                $extra['semesters_to_lock'] = $formData['semesters_to_lock'] ?? '';
                $extra['lock_reason']       = $formData['lock_reason'] ?? '';
                break;
            case 5:
                $extra['cancel_reason'] = $formData['cancel_reason'] ?? '';
                break;
            case 7:
                $extra['uc_isolated_names']  = $formData['uc_isolated_names'] ?? '';
                $extra['uc_isolated_course'] = $formData['uc_isolated_course'] ?? '';
                break;
            case 8:
                $extra['doc_type']            = $formData['doc_type'] ?? '';
                $extra['diploma_course_name'] = $formData['diploma_course_name'] ?? '';
                $extra['graduation_year']     = $formData['graduation_year'] ?? '';
                break;
            case 9:
                $extra['validation_type'] = $formData['validation_type'] ?? '';
                $extra['uc_re_detail']    = $formData['uc_re_detail'] ?? '';
                $extra['uc_rs_detail']    = $formData['uc_rs_detail'] ?? '';
                $extra['uc_eae_detail']   = $formData['uc_eae_detail'] ?? '';
                break;
            case 12:
                $extra['uc_special_names']  = $formData['uc_special_names'] ?? '';
                $extra['uc_special_course'] = $formData['uc_special_course'] ?? '';
                break;
            case 14:
                $extra['uc_changes'] = $formData['uc_changes'] ?? '';
                break;
            case 18:
                $extra['enade_status']        = $formData['enade_status'] ?? '';
                $extra['colacao_declaration'] = isset($formData['colacao_declaration']);
                break;
            case 22:
                $extra['selected_subjects']  = $formData['selected_subjects'] ?? [];
                $extra['selected_teachers']  = $formData['selected_teachers'] ?? [];
                $extra['teacher_other_name'] = $formData['teacher_other_name'] ?? '';
                break;
            case 25:
                $extra['selected_subjects'] = $formData['selected_subjects'] ?? [];
                $extra['uc_cancel_reason']  = $formData['uc_cancel_reason'] ?? '';
                break;
        }
        $extraFieldsJson = !empty($extra) ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null;

        $stmt = $conn->prepare("
            INSERT INTO requests (
                protocol_code, student_name, student_email, student_phone, student_id, course_id,
                class_info, is_minor, guardian_name, guardian_phone, request_type_id,
                description, status, schedule_type, arrival_time_1, arrival_time_2,
                departure_time_1, departure_time_2, declaration_accepted,
                start_date, end_date, course_units, extra_fields, submitted_by_guardian_id
            ) VALUES (
                :protocol, :name, :email, :phone, :sid, :cid,
                :class_info, 1, :gname, :gphone, :type,
                :desc, 'pending', :stype, :at1, :at2,
                :dt1, :dt2, :declaration,
                :start_date, :end_date, :course_units, :extra_fields, :guardian_id
            )
        ");
        $stmt->execute([
            ':protocol'    => $protocolCode,
            ':name'        => $studentName,
            ':email'       => $guardianEmail,
            ':phone'       => $guardianPhoneEncrypted,
            ':sid'         => $studentId,
            ':cid'         => $courseId,
            ':class_info'  => $classInfo,
            ':gname'       => $guardianName,
            ':gphone'      => $guardianPhoneEncrypted,
            ':type'        => $requestTypeId,
            ':desc'        => $description,
            ':stype'       => $formData['schedule_type'] ?? null,
            ':at1'         => !empty($formData['arrival_time_1']) ? $formData['arrival_time_1'] : null,
            ':at2'         => !empty($formData['arrival_time_2']) ? $formData['arrival_time_2'] : null,
            ':dt1'         => !empty($formData['departure_time_1']) ? $formData['departure_time_1'] : null,
            ':dt2'         => !empty($formData['departure_time_2']) ? $formData['departure_time_2'] : null,
            ':declaration' => isset($formData['declaration_accepted']) ? 1 : 0,
            ':start_date'  => $startDateDB,
            ':end_date'    => $endDateDB,
            ':course_units'=> $courseUnitsDB,
            ':extra_fields'=> $extraFieldsJson,
            ':guardian_id' => $guardianId,
        ]);

        $requestId = $conn->lastInsertId();

        // Move arquivos temporários para uploads
        if (!empty($formData['temp_files'])) {
            $uploadedCount = 0;
            $tempDir   = dirname(__DIR__) . '/temp/';
            $targetDir = dirname(__DIR__) . '/uploads/';
            foreach ((array)$formData['temp_files'] as $tempFile) {
                // SEGURANÇA: aceitar APENAS nomes gerados por upload_temp.php (bloqueia path traversal)
                if (!is_string($tempFile) || !preg_match('/^[a-f0-9]{32}\.(pdf|jpe?g|png|docx?)$/i', $tempFile)) {
                    continue;
                }
                $tempFilePath = $tempDir . basename($tempFile);
                if (file_exists($tempFilePath)) {
                    $uploadedCount++;
                    $ext         = strtolower(pathinfo($tempFilePath, PATHINFO_EXTENSION));
                    $newFilename = "{$protocolCode}-" . str_pad($uploadedCount, 2, '0', STR_PAD_LEFT) . ".{$ext}";
                    if (rename($tempFilePath, $targetDir . $newFilename)) {
                        $conn->prepare("INSERT INTO request_files (request_id, filepath) VALUES (:rid, :path)")
                             ->execute([':rid' => $requestId, ':path' => $newFilename]);
                    }
                }
            }
        }

        $conn->commit();
        unset($_SESSION['guardian_pending_request']);

        // E-mails pós-commit
        $emailService = new EmailService($conn);
        $safeGuardianName = htmlspecialchars($guardianName);
        $safeStudentName  = htmlspecialchars($studentName);
        $safeProtocol     = htmlspecialchars($protocolCode);

        $courseNameStmt = $conn->prepare("SELECT name FROM courses WHERE id = :id");
        $courseNameStmt->execute([':id' => $courseId]);
        $safeCourseName = htmlspecialchars($courseNameStmt->fetchColumn() ?: '');

        $typeNameStmt = $conn->prepare("SELECT name FROM request_types WHERE id = :id");
        $typeNameStmt->execute([':id' => $requestTypeId]);
        $safeTypeName = htmlspecialchars($typeNameStmt->fetchColumn() ?: '');

        // Confirmação ao responsável
        $emailService->queue($guardianEmail, $guardianName,
            "Requerimento protocolado — $safeProtocol",
            EmailTemplate::wrap("
                <h2 style='color:#006633;'>Requerimento protocolado com sucesso!</h2>
                <p>Olá, <strong>$safeGuardianName</strong>!</p>
                <p>O requerimento em nome de <strong>$safeStudentName</strong> foi registrado.</p>
                <div style='background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:16px 20px;margin:20px 0;text-align:center;'>
                    <p style='margin:0;font-size:11px;color:#16a34a;font-weight:bold;text-transform:uppercase;letter-spacing:1px;'>Protocolo</p>
                    <p style='margin:6px 0 0;font-size:26px;font-weight:bold;color:#15803d;font-family:monospace;letter-spacing:1px;'>$safeProtocol</p>
                </div>
                <table style='width:100%;border-collapse:collapse;font-size:14px;'>
                    <tr style='border-bottom:1px solid #f3f4f6;'>
                        <td style='padding:8px 0;color:#6b7280;width:40%;'>Aluno(a)</td>
                        <td style='padding:8px 0;font-weight:600;color:#111827;'>$safeStudentName</td>
                    </tr>
                    <tr style='border-bottom:1px solid #f3f4f6;'>
                        <td style='padding:8px 0;color:#6b7280;'>Tipo</td>
                        <td style='padding:8px 0;font-weight:600;color:#111827;'>$safeTypeName</td>
                    </tr>
                    <tr>
                        <td style='padding:8px 0;color:#6b7280;'>Curso</td>
                        <td style='padding:8px 0;font-weight:600;color:#111827;'>$safeCourseName</td>
                    </tr>
                </table>
                <p style='margin-top:16px;color:#555;font-size:13px;'>Você será notificado(a) por e-mail sobre o andamento.</p>
            ")
        );

        // Notificação para revisores do step 1 do workflow
        $stepStmt = $conn->prepare("SELECT role_id FROM workflow_steps WHERE request_type_id = :type AND step_order = 1");
        $stepStmt->execute([':type' => $requestTypeId]);
        $stepRole = $stepStmt->fetchColumn();

        if ($stepRole) {
            $roleStmt = $conn->prepare("SELECT is_course_bound FROM roles WHERE id = :role");
            $roleStmt->execute([':role' => $stepRole]);
            $isCourseBound = $roleStmt->fetchColumn();

            if ($isCourseBound) {
                $usersStmt = $conn->prepare("SELECT u.name, u.email FROM users u JOIN user_courses uc ON u.id = uc.user_id WHERE u.role_id = :role AND uc.course_id = :course AND u.receive_email = 1");
                $usersStmt->execute([':role' => $stepRole, ':course' => $courseId]);
            } else {
                $usersStmt = $conn->prepare("SELECT name, email FROM users WHERE role_id = :role AND receive_email = 1");
                $usersStmt->execute([':role' => $stepRole]);
            }

            foreach ($usersStmt->fetchAll(PDO::FETCH_ASSOC) as $recipient) {
                $safeRecipient = htmlspecialchars($recipient['name']);
                $emailService->queue($recipient['email'], $recipient['name'],
                    "Nova solicitação aguardando análise — $safeProtocol",
                    EmailTemplate::wrap("
                        <p>Olá, <strong>$safeRecipient</strong>!</p>
                        <p>Uma nova solicitação foi registrada pelo <strong>Portal de Responsáveis</strong> e aguarda sua análise.</p>
                        <div style='background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px 20px;margin:20px 0;'>
                            <table style='width:100%;border-collapse:collapse;font-size:14px;'>
                                <tr style='border-bottom:1px solid #e2e8f0;'>
                                    <td style='padding:8px 0;color:#6b7280;width:40%;'>Protocolo</td>
                                    <td style='padding:8px 0;font-weight:700;font-family:monospace;'>$safeProtocol</td>
                                </tr>
                                <tr style='border-bottom:1px solid #e2e8f0;'>
                                    <td style='padding:8px 0;color:#6b7280;'>Aluno(a)</td>
                                    <td style='padding:8px 0;font-weight:600;'>$safeStudentName</td>
                                </tr>
                                <tr style='border-bottom:1px solid #e2e8f0;'>
                                    <td style='padding:8px 0;color:#6b7280;'>Responsável</td>
                                    <td style='padding:8px 0;font-weight:600;'>$safeGuardianName</td>
                                </tr>
                                <tr style='border-bottom:1px solid #e2e8f0;'>
                                    <td style='padding:8px 0;color:#6b7280;'>Tipo</td>
                                    <td style='padding:8px 0;font-weight:600;'>$safeTypeName</td>
                                </tr>
                                <tr>
                                    <td style='padding:8px 0;color:#6b7280;'>Curso</td>
                                    <td style='padding:8px 0;font-weight:600;'>$safeCourseName</td>
                                </tr>
                            </table>
                        </div>
                        <div style='text-align:center;margin:24px 0;'>
                            <a href='" . BASE_URL . "/admin/request_details.php?id={$requestId}'
                               style='background:#1CBB9B;color:#fff;padding:12px 32px;border-radius:6px;font-weight:bold;text-decoration:none;font-size:14px;display:inline-block;'>
                                Analisar Requerimento
                            </a>
                        </div>
                    ")
                );
            }
            $emailService->triggerBackgroundProcess();
        }

        header('Location: dashboard.php?success=' . urlencode($protocolCode));
        exit;

    } catch (Exception $e) {
        if (isset($conn)) $conn->rollBack();
        unset($_SESSION['guardian_pending_request']);
        error_log('submit_requerimento error: ' . $e->getMessage());
        header('Location: novo_requerimento.php?error=sistema');
        exit;
    }
}

// Fallback: ação inválida
header('Location: dashboard.php');
exit;
