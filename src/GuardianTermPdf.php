<?php
class GuardianTermPdf
{
    public static function render(array $data): string
    {
        $name     = htmlspecialchars($data['name'] ?? '');
        $email    = htmlspecialchars($data['email'] ?? '');
        $cpf      = htmlspecialchars($data['cpf'] ?? '');
        $phone    = htmlspecialchars($data['phone'] ?? '');
        $students = $data['students'] ?? [];
        $date     = date('d/m/Y');

        $studentsHtml = '';
        foreach ($students as $i => $s) {
            $sName = htmlspecialchars($s['name'] ?? '');
            $sMatr = htmlspecialchars($s['matricula'] ?? '');
            $studentsHtml .= "<tr>
                <td style='padding:6px 10px;border-bottom:1px solid #e5e7eb;'>" . ($i + 1) . "</td>
                <td style='padding:6px 10px;border-bottom:1px solid #e5e7eb;'>{$sName}</td>
                <td style='padding:6px 10px;border-bottom:1px solid #e5e7eb;'>{$sMatr}</td>
            </tr>";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
  @media print { @page { margin: 2cm; } }
  body { font-family: Arial, sans-serif; font-size: 12px; color: #111; line-height: 1.6; }
  .header { text-align: center; border-bottom: 2px solid #006633; padding-bottom: 16px; margin-bottom: 24px; }
  .header h1 { font-size: 16px; color: #006633; margin: 4px 0; }
  .header p { font-size: 11px; color: #666; margin: 0; }
  h2 { font-size: 13px; color: #006633; margin: 20px 0 8px; border-bottom: 1px solid #cce5d4; padding-bottom: 4px; }
  table { width: 100%; border-collapse: collapse; margin: 12px 0; }
  th { background: #006633; color: white; padding: 7px 10px; text-align: left; font-size: 11px; }
  td { font-size: 11px; }
  .field-row { display: flex; gap: 24px; margin-bottom: 8px; }
  .field { flex: 1; }
  .field label { font-size: 10px; font-weight: bold; color: #555; display: block; margin-bottom: 2px; }
  .field span { font-size: 12px; }
  .term-box { background: #f9fafb; border: 1px solid #d1fae5; border-radius: 4px; padding: 14px 16px; margin: 16px 0; font-size: 11px; line-height: 1.7; }
  .sign-area { margin-top: 48px; border-top: 1px solid #ddd; padding-top: 16px; }
  .sign-line { border-bottom: 1px solid #333; width: 60%; margin: 40px auto 4px; }
  .sign-label { text-align: center; font-size: 10px; color: #666; }
  .govbr-notice { background: #fffbeb; border: 1px solid #fde68a; border-radius: 4px; padding: 10px 14px; margin-top: 20px; font-size: 10px; color: #92400e; }
</style>
</head>
<body>

<div class="header">
  <h1>IFSC — Instituto Federal de Santa Catarina · Câmpus Garopaba</h1>
  <h1>Termo de Autorização de Acesso ao Portal de Responsáveis</h1>
  <p>Sistema Web de Gestão de Requerimentos e Gestão de Ensino</p>
</div>

<h2>1. Dados do Responsável</h2>
<div class="field-row">
  <div class="field"><label>Nome completo</label><span>{$name}</span></div>
  <div class="field"><label>CPF</label><span>{$cpf}</span></div>
</div>
<div class="field-row">
  <div class="field"><label>E-mail</label><span>{$email}</span></div>
  <div class="field"><label>Telefone</label><span>{$phone}</span></div>
</div>

<h2>2. Alunos sob responsabilidade</h2>
<table>
  <thead><tr><th>#</th><th>Nome do Aluno</th><th>Matrícula</th></tr></thead>
  <tbody>{$studentsHtml}</tbody>
</table>

<h2>3. Termo de Responsabilidade</h2>
<div class="term-box">
  <p>Eu, <strong>{$name}</strong>, portador(a) do CPF <strong>{$cpf}</strong>, declaro para os devidos fins que:</p>
  <ol style="margin:8px 0 0 16px; padding:0;">
    <li>Sou responsável legal pelo(s) aluno(s) listado(s) acima, matriculado(s) no IFSC Câmpus Garopaba;</li>
    <li>O endereço de e-mail informado (<strong>{$email}</strong>) é o mesmo cadastrado no SIGAA como e-mail de responsável do(s) aluno(s);</li>
    <li>Estou ciente de que o acesso ao Portal de Responsáveis me permitirá acompanhar e protocolar requerimentos em nome do(s) aluno(s) menor(es) de idade;</li>
    <li>Assumo total responsabilidade pelo uso das credenciais de acesso, comprometendo-me a não compartilhá-las com terceiros, incluindo o(s) próprio(s) aluno(s);</li>
    <li>Estou ciente de que informações falsas implicam em responsabilidade civil e administrativa, conforme legislação vigente;</li>
    <li>Autorizo o IFSC Câmpus Garopaba a tratar os dados pessoais aqui informados para fins de gestão acadêmica, nos termos da LGPD (Lei nº 13.709/2018).</li>
  </ol>
</div>

<div class="govbr-notice">
  <strong>Instrução para assinatura digital:</strong> Este documento deve ser assinado eletronicamente via
  <strong>gov.br</strong> (assinatura digital com certificado ICP-Brasil ou pelo aplicativo gov.br).
  Após assinar, envie o arquivo PDF assinado digitalmente pelo formulário de cadastro.
  Documentos sem assinatura digital não serão aceitos.
</div>

<div class="sign-area">
  <div class="sign-line"></div>
  <div class="sign-label">{$name} — CPF: {$cpf}<br>{$date}</div>
  <div style="margin-top:16px; text-align:center; font-size:10px; color:#999;">
    (Assinatura digital via gov.br — não assinar manualmente)
  </div>
</div>

</body>
</html>
HTML;
    }
}
