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
    $studentPhone = $_POST['student_phone'] ?? null;
    $studentId = $_POST['student_id'];
    $courseId = $_POST['course_id'];
    $isMinor = !isset($_POST['is_adult']);
    $guardianName = $isMinor ? $_POST['guardian_name'] : null;
    $guardianPhone = $isMinor ? $_POST['guardian_phone'] : null;
    $requestTypeId = $_POST['request_type_id'];
    $description = $_POST['description'];
    $classInfo = $_POST['class_info'] ?? null;

    $startDateDB = null;
    $endDateDB = null;
    $courseUnitsDB = null;

    if ($requestTypeId == 1) {
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?? '';
        $subjects = $_POST['subjects'] ?? '';
        if ($startDate && $endDate && $subjects) {
            $startDateDB = $startDate;
            $endDateDB = $endDate;
            $courseUnitsDB = $subjects;
            $days = ['Domingo', 'Segunda-Feira', 'Terça-Feira', 'Quarta-Feira', 'Quinta-Feira', 'Sexta-Feira', 'Sábado'];
            $startObj = new DateTime($startDate);
            $endObj = new DateTime($endDate);
            $description .= "\n\nData Início: " . $startObj->format('d/m/Y') . ' (' . $days[$startObj->format('w')] . ')';
            $description .= "\nData Final: " . $endObj->format('d/m/Y') . ' (' . $days[$endObj->format('w')] . ')';
            $description .= "\nUnidades Curriculares: " . $subjects;
        }
    }

    $query = "INSERT INTO requests (
                protocol_code, student_name, student_email, student_phone, student_id, course_id, 
                class_info, is_minor, guardian_name, guardian_phone, request_type_id, 
                description, status, schedule_type, arrival_time_1, arrival_time_2, 
                departure_time_1, departure_time_2, declaration_accepted,
                start_date, end_date, course_units
              ) VALUES (
                :protocol, :name, :email, :phone, :sid, :cid, 
                :class_info, :minor, :gname, :gphone, :type, 
                :desc, 'pending', :stype, :at1, :at2, 
                :dt1, :dt2, :declaration,
                :start_date, :end_date, :course_units
              )";

    $stmt = $conn->prepare($query);
    $stmt->execute([
        ':protocol' => $protocolCode,
        ':name' => $studentName,
        ':email' => $studentEmail,
        ':phone' => $studentPhone,
        ':sid' => $studentId,
        ':cid' => $courseId,
        ':class_info' => $classInfo,
        ':minor' => $isMinor ? 1 : 0,
        ':gname' => $guardianName,
        ':gphone' => $guardianPhone,
        ':type' => $requestTypeId,
        ':desc' => $description,
        ':stype' => $_POST['schedule_type'] ?? null,
        ':at1' => !empty($_POST['arrival_time_1']) ? $_POST['arrival_time_1'] : null,
        ':at2' => !empty($_POST['arrival_time_2']) ? $_POST['arrival_time_2'] : null,
        ':dt1' => !empty($_POST['departure_time_1']) ? $_POST['departure_time_1'] : null,
        ':dt2' => !empty($_POST['departure_time_2']) ? $_POST['departure_time_2'] : null,
        ':declaration' => isset($_POST['declaration_accepted']) ? 1 : 0,
        ':start_date' => $startDateDB,
        ':end_date' => $endDateDB,
        ':course_units' => $courseUnitsDB
    ]);

    $requestId = $conn->lastInsertId();

    // 4. Handle Files (Permanent Transfer)
    if (isset($_POST['temp_files'])) {
        $tempFiles = $_POST['temp_files'];
        $uploadedCount = 0;
        $tempDir = __DIR__ . '/temp/';
        $targetDir = __DIR__ . '/uploads/';

        foreach ($tempFiles as $tempFile) {
            $tempFilePath = $tempDir . $tempFile;

            if (file_exists($tempFilePath)) {
                $uploadedCount++;
                $extension = strtolower(pathinfo($tempFile, PATHINFO_EXTENSION));
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
    $emailService = new EmailService($conn);

    $safeStudentName = htmlspecialchars($studentName);
    $safeProtocol = htmlspecialchars($protocolCode);

    $subject = "Recebemos sua solicitação - Protocolo: $safeProtocol";
    $body = "
        <h2>Olá, $safeStudentName!</h2>
        <p>Sua solicitação foi recebida com sucesso.</p>
        <p><strong>Protocolo:</strong> $safeProtocol</p>
        <p><strong>Status:</strong> Pendente</p>
        <p>Você pode acompanhar o status da sua solicitação clicando no link abaixo:</p>
        <p><a href='" . BASE_URL . "/check_status.php' style='color: #1CBB9B; font-weight: bold;'>Acompanhar Solicitação</a></p>
        <br>
        <p>Atenciosamente,<br>IFSC Câmpus Canoinhas</p>
    ";

    $emailService->send($studentEmail, $studentName, $subject, $body, false);

    // E-mail para os Admins
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

        $typeNameStmt = $conn->prepare("SELECT name FROM request_types WHERE id = :id");
        $typeNameStmt->execute([':id' => $requestTypeId]);
        $safeTypeName = htmlspecialchars($typeNameStmt->fetchColumn());

        $adminSubject = "Nova Solicitação Pendente - Protocolo: $safeProtocol";
        $adminBody = "
            <h2>Nova Solicitação Recebida</h2>
            <p><strong>Aluno:</strong> $safeStudentName</p>
            <p><strong>Protocolo:</strong> $safeProtocol</p>
            <p><strong>Tipo de Requerimento:</strong> $safeTypeName</p>
            <p>Acesse o sistema administrativo para analisar:</p>
            <p><a href='" . BASE_URL . "/admin/request_details.php?id=$requestId' style='color: #1CBB9B; font-weight: bold;'>Visualizar Requerimento</a></p>
        ";

        foreach ($usersStmt->fetchAll(PDO::FETCH_ASSOC) as $recipient) {
            $emailService->send($recipient['email'], $recipient['name'], $adminSubject, $adminBody, false);
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
    die("Erro: " . $e->getMessage());
}
