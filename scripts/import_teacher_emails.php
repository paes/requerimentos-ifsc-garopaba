<?php
/**
 * Script pontual para importar e-mails de docentes via scraping da agenda IFSC.
 * Fonte: https://agenda.ifsc.edu.br/php/servidores.php?idCampus=2230
 *
 * USO: php scripts/import_teacher_emails.php [--apply]
 *   Sem --apply: apenas imprime os UPDATEs sugeridos para revisão
 *   Com --apply:  executa os UPDATEs diretamente no banco
 */

require_once __DIR__ . '/../config/database.php';

$apply = in_array('--apply', $argv ?? []);

// --- Fetch IFSC agenda page ---
$url  = 'https://agenda.ifsc.edu.br/php/servidores.php?idCampus=2230';
$html = @file_get_contents($url, false, stream_context_create([
    'http' => ['timeout' => 15, 'user_agent' => 'Mozilla/5.0 (compatible; IFSC-importer/1.0)']
]));

if ($html === false) {
    fwrite(STDERR, "ERRO: Não foi possível acessar $url\n");
    exit(1);
}

// --- Extrair pares (nome => email) ---
// Padrão esperado: <a href="mailto:fulano@ifsc.edu.br">Nome Completo</a>
preg_match_all('/<a[^>]+href=["\']mailto:([^"\']+)["\'][^>]*>([^<]+)<\/a>/i', $html, $matches, PREG_SET_ORDER);

$scraped = []; // name_normalized => email
foreach ($matches as $m) {
    $email = strtolower(trim($m[1]));
    $name  = trim(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($name && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $scraped[normalizeName($name)] = ['original' => $name, 'email' => $email];
    }
}

if (empty($scraped)) {
    fwrite(STDERR, "AVISO: Nenhum e-mail encontrado na página. O layout pode ter mudado.\n");
    exit(1);
}

echo "Encontrados " . count($scraped) . " e-mails na página IFSC.\n\n";

// --- Carregar docentes do banco ---
$db   = new Database();
$conn = $db->getConnection();
$rows = $conn->query("SELECT id, name, email FROM teachers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$matched   = [];
$unmatched = [];

foreach ($rows as $teacher) {
    $norm = normalizeName($teacher['name']);

    // Tentativa 1: match exato do nome normalizado
    if (isset($scraped[$norm])) {
        $matched[] = ['teacher' => $teacher, 'scraped' => $scraped[$norm]];
        continue;
    }

    // Tentativa 2: match parcial — nome do banco contido no nome da página
    $found = null;
    foreach ($scraped as $scrapedNorm => $scrapedData) {
        if (str_contains($scrapedNorm, $norm) || str_contains($norm, $scrapedNorm)) {
            $found = $scrapedData;
            break;
        }
    }

    if ($found) {
        $matched[] = ['teacher' => $teacher, 'scraped' => $found, 'partial' => true];
    } else {
        $unmatched[] = $teacher;
    }
}

// --- Exibir / aplicar ---
$updatesSkipped = 0;
echo "=== MATCHES ENCONTRADOS (" . count($matched) . ") ===\n\n";

foreach ($matched as $m) {
    $t      = $m['teacher'];
    $email  = $m['scraped']['email'];
    $partial = !empty($m['partial']) ? ' [MATCH PARCIAL — confirme]' : '';

    if ($t['email'] === $email) {
        echo "  [igual]    {$t['name']} → {$email}\n";
        $updatesSkipped++;
        continue;
    }

    $old = $t['email'] ? "(era: {$t['email']})" : '(sem e-mail)';
    echo "  [UPDATE]{$partial}  {$t['name']} → {$email} {$old}\n";

    if ($apply) {
        $stmt = $conn->prepare("UPDATE teachers SET email = :email WHERE id = :id");
        $stmt->execute([':email' => $email, ':id' => $t['id']]);
    } else {
        echo "    SQL: UPDATE teachers SET email = '{$email}' WHERE id = {$t['id']};\n";
    }
}

if ($updatesSkipped > 0) {
    echo "\n  ($updatesSkipped docentes já tinham o e-mail correto, ignorados)\n";
}

if (!empty($unmatched)) {
    echo "\n=== SEM MATCH (" . count($unmatched) . ") — inserir manualmente via admin ===\n\n";
    foreach ($unmatched as $t) {
        echo "  ID {$t['id']}: {$t['name']}\n";
    }
}

if (!$apply) {
    echo "\n=> Execute com --apply para aplicar as alterações: php scripts/import_teacher_emails.php --apply\n";
} else {
    echo "\nConcluído.\n";
}

// --- Helpers ---
function normalizeName(string $name): string {
    $name = mb_strtolower(trim($name), 'UTF-8');
    $from = ['á','à','ã','â','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','õ','ô','ö','ú','ù','û','ü','ç','ñ'];
    $to   = ['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c','n'];
    return str_replace($from, $to, $name);
}
