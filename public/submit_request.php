<?php
/**
 * Script responsavel por receber o POST do formulario publico, validar os dados e inserir o novo requerimento no banco.
 *
 * @author Prof. Eduardo Gomes
 */
// public/submit_request.php
session_start();
session_write_close();

require_once '../config/database.php';
require_once '../config/config.php';
require_once '../src/Helpers.php';
require_once '../src/CryptoHelper.php';

// Verificacoes Anti-Bot
if (ENABLE_TURNSTILE) {
    $cf_secret = TURNSTILE_SECRET_KEY;
    $cf_response = $_POST['cf-turnstile-response'] ?? '';
    $verification = Helpers::verifyTurnstile($cf_response, $cf_secret, $_SERVER['REMOTE_ADDR']);
    if (!$verification['success']) {
        die("Erro: " . $verification['message']);
    }
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    $conn->beginTransaction();

    $protocolCode = Helpers::generateProtocolCode($conn);

    $studentName = $_POST['student_name'];
    $studentEmail = $_POST['student_email'];
    $studentPhone  = CryptoHelper::encrypt($_POST['student_phone'] ?? null);
    $studentId = $_POST['student_id'];
    $courseId = $_POST['course_id'];
    $isMinor = !isset($_POST['is_adult']);
    $guardianName  = $isMinor ? $_POST['guardian_name'] : null;
    $guardianPhone = $isMinor ? CryptoHelper::encrypt($_POST['guardian_phone'] ?? null) : null;
    $requestTypeId = $_POST['request_type_id'];
    $description = $_POST['description'];
    $classInfo = $_POST['class_info'] ?? null;

    $startDateDB = null;
    $endDateDB = null;
    $courseUnitsDB = null;
    $requestTypeIdInt = intval($requestTypeId);

    // Legado tipo 1: preserva colunas dedicadas e constrói resumo na descrição
    if ($requestTypeIdInt === 1) {
        $startDate = $_POST['start_date'] ?? '';
        $endDate   = $_POST['end_date'] ?? '';
        $selSubjects = $_POST['selected_subjects'] ?? [];
        $subjectsText = is_array($selSubjects) ? implode(', ', $selSubjects) : $selSubjects;
        if ($startDate && $endDate) {
            $startDateDB   = $startDate;
            $endDateDB     = $endDate;
            $courseUnitsDB = $subjectsText;
            $days = ['Domingo', 'Segunda-Feira', 'Terça-Feira', 'Quarta-Feira', 'Quinta-Feira', 'Sexta-Feira', 'Sábado'];
            $startObj = new DateTime($startDate);
            $endObj   = new DateTime($endDate);
            $description .= "\n\nData Início: " . $startObj->format('d/m/Y') . ' (' . $days[$startObj->format('w')] . ')';
            $description .= "\nData Final: "   . $endObj->format('d/m/Y')   . ' (' . $days[$endObj->format('w')] . ')';
            if ($subjectsText) $description .= "\nUnidades Curriculares: " . $subjectsText;
        }
    }

    // Monta extra_fields JSON por tipo
    $extra = [];
    switch ($requestTypeIdInt) {
        case 1:
            $extra['start_date']        = $_POST['start_date'] ?? '';
            $extra['end_date']          = $_POST['end_date'] ?? '';
            $extra['selected_subjects'] = $_POST['selected_subjects'] ?? [];
            break;
        case 2:
            $extra['selected_subjects']    = $_POST['selected_subjects'] ?? [];
            $extra['selected_teachers']    = $_POST['selected_teachers'] ?? [];
            $extra['uc_other_name']        = trim($_POST['uc_other_name'] ?? '');
            $extra['teacher_other_name']   = $_POST['teacher_other_name'] ?? '';
            $extra['also_justify_absence'] = isset($_POST['also_justify_absence']);
            break;
        case 4:
            $extra['semesters_to_lock'] = $_POST['semesters_to_lock'] ?? '';
            $extra['lock_reason']       = $_POST['lock_reason'] ?? '';
            break;
        case 5:
            $extra['cancel_reason'] = $_POST['cancel_reason'] ?? '';
            break;
        case 7:
            $extra['uc_isolated_names']  = $_POST['uc_isolated_names'] ?? '';
            $extra['uc_isolated_course'] = $_POST['uc_isolated_course'] ?? '';
            break;
        case 8:
            $extra['doc_type']           = $_POST['doc_type'] ?? '';
            $extra['diploma_course_name']= $_POST['diploma_course_name'] ?? '';
            $extra['graduation_year']    = $_POST['graduation_year'] ?? '';
            break;
        case 9:
            $extra['validation_type'] = $_POST['validation_type'] ?? '';
            $extra['uc_re_detail']    = $_POST['uc_re_detail'] ?? '';
            $extra['uc_rs_detail']    = $_POST['uc_rs_detail'] ?? '';
            $extra['uc_eae_detail']   = $_POST['uc_eae_detail'] ?? '';
            break;
        case 12:
            $extra['uc_special_names']  = $_POST['uc_special_names'] ?? '';
            $extra['uc_special_course'] = $_POST['uc_special_course'] ?? '';
            break;
        case 14:
            $extra['uc_changes'] = $_POST['uc_changes'] ?? '';
            break;
        case 18:
            $extra['enade_status']        = $_POST['enade_status'] ?? '';
            $extra['colacao_declaration'] = isset($_POST['colacao_declaration']);
            break;
        case 22:
            $extra['selected_subjects'] = $_POST['selected_subjects'] ?? [];
            $extra['selected_teachers'] = $_POST['selected_teachers'] ?? [];
            $extra['teacher_other_name']= $_POST['teacher_other_name'] ?? '';
            break;
        case 25:
            $extra['selected_subjects'] = $_POST['selected_subjects'] ?? [];
            $extra['uc_cancel_reason']  = $_POST['uc_cancel_reason'] ?? '';
            break;
    }
    $extraFieldsJson = !empty($extra) ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null;

    $query = "INSERT INTO requests (
                protocol_code, student_name, student_email, student_phone, student_id, course_id,
                class_info, is_minor, guardian_name, guardian_phone, request_type_id,
                description, status, schedule_type, arrival_time_1, arrival_time_2,
                departure_time_1, departure_time_2, declaration_accepted,
                start_date, end_date, course_units, extra_fields
              ) VALUES (
                :protocol, :name, :email, :phone, :sid, :cid,
                :class_info, :minor, :gname, :gphone, :type,
                :desc, 'pending', :stype, :at1, :at2,
                :dt1, :dt2, :declaration,
                :start_date, :end_date, :course_units, :extra_fields
              )";

    $stmt = $conn->prepare($query);
    $stmt->execute([
        ':protocol'    => $protocolCode,
        ':name'        => $studentName,
        ':email'       => $studentEmail,
        ':phone'       => $studentPhone,
        ':sid'         => $studentId,
        ':cid'         => $courseId,
        ':class_info'  => $classInfo,
        ':minor'       => $isMinor ? 1 : 0,
        ':gname'       => $guardianName,
        ':gphone'      => $guardianPhone,
        ':type'        => $requestTypeId,
        ':desc'        => $description,
        ':stype'       => $_POST['schedule_type'] ?? null,
        ':at1'         => !empty($_POST['arrival_time_1']) ? $_POST['arrival_time_1'] : null,
        ':at2'         => !empty($_POST['arrival_time_2']) ? $_POST['arrival_time_2'] : null,
        ':dt1'         => !empty($_POST['departure_time_1']) ? $_POST['departure_time_1'] : null,
        ':dt2'         => !empty($_POST['departure_time_2']) ? $_POST['departure_time_2'] : null,
        ':declaration' => isset($_POST['declaration_accepted']) ? 1 : 0,
        ':start_date'  => $startDateDB,
        ':end_date'    => $endDateDB,
        ':course_units'=> $courseUnitsDB,
        ':extra_fields'=> $extraFieldsJson,
    ]);

    $requestId = $conn->lastInsertId();

    // 4. Handle Files (Permanent Transfer)
    if (isset($_POST['temp_files']) && is_array($_POST['temp_files'])) {
        $tempFiles = $_POST['temp_files'];
        $uploadedCount = 0;
        $tempDir = __DIR__ . '/temp/';
        $targetDir = __DIR__ . '/uploads/';

        foreach ($tempFiles as $tempFile) {
            // SEGURANÇA: aceitar APENAS nomes gerados por upload_temp.php
            // (32 hex + extensão permitida). Bloqueia path traversal (ex.: ../config/config.php).
            if (!is_string($tempFile) || !preg_match('/^[a-f0-9]{32}\.(pdf|jpe?g|png|docx?)$/i', $tempFile)) {
                continue;
            }
            $safeName     = basename($tempFile);
            $tempFilePath = $tempDir . $safeName;

            if (file_exists($tempFilePath)) {
                $uploadedCount++;
                $extension = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
                $newFilename = "{$protocolCode}-" . str_pad($uploadedCount, 2, '0', STR_PAD_LEFT) . ".{$extension}";
                $targetPath = $targetDir . $newFilename;

                if (rename($tempFilePath, $targetPath)) {
                    $fileStmt = $conn->prepare("INSERT INTO request_files (request_id, filepath) VALUES (:rid, :path)");
                    $fileStmt->execute([':rid' => $requestId, ':path' => $newFilename]);
                }
            }
        }
    }

    $conn->commit();

    // 5. Success Response & Email
    require_once '../src/EmailService.php';
    require_once '../src/EmailTemplate.php';
    $emailService = new EmailService($conn);

    $safeStudentName = htmlspecialchars($studentName);
    $safeProtocol    = htmlspecialchars($protocolCode);

    // Busca nome do curso e do tipo para usar nos e-mails
    $courseNameRow = $conn->prepare("SELECT name FROM courses WHERE id = :id");
    $courseNameRow->execute([':id' => $courseId]);
    $safeCourseName = htmlspecialchars($courseNameRow->fetchColumn() ?: '');

    $typeNameRow = $conn->prepare("SELECT name FROM request_types WHERE id = :id");
    $typeNameRow->execute([':id' => $requestTypeId]);
    $safeTypeName = htmlspecialchars($typeNameRow->fetchColumn() ?: '');

    // --- E-mail de confirmação ao aluno ---
    $subject = "Requerimento recebido — Protocolo $safeProtocol";
    $body = "
        <p>Olá, <strong>$safeStudentName</strong>!</p>
        <p>Sua solicitação foi recebida com sucesso e será analisada em breve.</p>

        <div style='background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:16px 20px;margin:20px 0;text-align:center;'>
            <p style='margin:0;font-size:11px;color:#16a34a;font-weight:bold;text-transform:uppercase;letter-spacing:1px;'>Número de Protocolo</p>
            <p style='margin:6px 0 0;font-size:22px;font-weight:bold;color:#15803d;font-family:monospace;letter-spacing:1px;'>$safeProtocol</p>
            <p style='margin:6px 0 0;font-size:12px;color:#4b5563;'>Guarde este número para acompanhar sua solicitação</p>
        </div>

        <table style='width:100%;border-collapse:collapse;font-size:14px;'>
            <tr style='border-bottom:1px solid #f3f4f6;'>
                <td style='padding:8px 0;color:#6b7280;width:40%;'>Tipo de Requerimento</td>
                <td style='padding:8px 0;font-weight:600;color:#111827;'>$safeTypeName</td>
            </tr>
            <tr style='border-bottom:1px solid #f3f4f6;'>
                <td style='padding:8px 0;color:#6b7280;'>Curso</td>
                <td style='padding:8px 0;font-weight:600;color:#111827;'>$safeCourseName</td>
            </tr>
            <tr>
                <td style='padding:8px 0;color:#6b7280;'>Status</td>
                <td style='padding:8px 0;'><span style='background:#fef3c7;color:#92400e;font-size:12px;font-weight:700;padding:2px 10px;border-radius:99px;'>Em análise</span></td>
            </tr>
        </table>

        <p style='margin-top:20px;'>Você receberá um novo e-mail assim que houver uma atualização. Se preferir, acompanhe pelo sistema:</p>

        <div style='text-align:center;margin:24px 0;'>
            <a href='" . BASE_URL . "/check_status.php'
               style='background:#1CBB9B;color:#ffffff;padding:12px 32px;border-radius:6px;font-weight:bold;text-decoration:none;font-size:14px;display:inline-block;'>
                Acompanhar Solicitação
            </a>
        </div>
    ";
    $emailService->send($studentEmail, $studentName, $subject, EmailTemplate::wrap($body), false);

    // --- E-mail de notificação ao responsável pela análise (step 1 do workflow) ---
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

        $adminSubject = "Nova solicitação aguardando análise — $safeProtocol";

        foreach ($usersStmt->fetchAll(PDO::FETCH_ASSOC) as $recipient) {
            $safeRecipientName = htmlspecialchars($recipient['name']);
            $adminBody = "
                <p>Olá, <strong>$safeRecipientName</strong>!</p>
                <p>Uma nova solicitação foi registrada no sistema e aguarda sua análise.</p>

                <div style='background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px 20px;margin:20px 0;'>
                    <table style='width:100%;border-collapse:collapse;font-size:14px;'>
                        <tr style='border-bottom:1px solid #e2e8f0;'>
                            <td style='padding:8px 0;color:#6b7280;width:40%;'>Protocolo</td>
                            <td style='padding:8px 0;font-weight:700;color:#111827;font-family:monospace;'>$safeProtocol</td>
                        </tr>
                        <tr style='border-bottom:1px solid #e2e8f0;'>
                            <td style='padding:8px 0;color:#6b7280;'>Aluno(a)</td>
                            <td style='padding:8px 0;font-weight:600;color:#111827;'>$safeStudentName</td>
                        </tr>
                        <tr style='border-bottom:1px solid #e2e8f0;'>
                            <td style='padding:8px 0;color:#6b7280;'>Tipo de Requerimento</td>
                            <td style='padding:8px 0;font-weight:600;color:#111827;'>$safeTypeName</td>
                        </tr>
                        <tr>
                            <td style='padding:8px 0;color:#6b7280;'>Curso</td>
                            <td style='padding:8px 0;font-weight:600;color:#111827;'>$safeCourseName</td>
                        </tr>
                    </table>
                </div>

                <div style='text-align:center;margin:24px 0;'>
                    <a href='" . BASE_URL . "/admin/request_details.php?id=$requestId'
                       style='background:#1CBB9B;color:#ffffff;padding:12px 32px;border-radius:6px;font-weight:bold;text-decoration:none;font-size:14px;display:inline-block;'>
                        Analisar Requerimento
                    </a>
                </div>
            ";
            $emailService->send($recipient['email'], $recipient['name'], $adminSubject, EmailTemplate::wrap($adminBody), false);
        }

        $emailService->triggerBackgroundProcess();
    }

    // Redireciona e Fecha Conexao com Padding
    header("Location: " . BASE_URL . "/success.php?protocol=" . $protocolCode, true, 303);
    header("Connection: close");

    // Padding para evitar buffering em alguns proxies (Cloudflare/Nginx)
    // Enviaing ~4KB of invisible spaces to force buffer flush
    echo str_repeat(' ', 4096);

    if (ob_get_level() > 0)
        ob_end_flush();
    flush();

    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    exit;

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }
    // Loga o detalhe internamente (não expõe ao usuário — evita vazamento de informação)
    error_log('[submit_request] ' . $e->getMessage());
    http_response_code(500);
    die("Não foi possível registrar seu requerimento no momento. Tente novamente em alguns minutos ou contate a secretaria.");
}
