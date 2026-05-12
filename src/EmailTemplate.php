<?php

class EmailTemplate
{
    public static function wrap(string $body): string
    {
        return '<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" border="0">
  <tr><td align="center" style="padding:32px 16px;">
    <table width="600" cellpadding="0" cellspacing="0" border="0"
      style="background:#ffffff;border-radius:10px;overflow:hidden;border:1px solid #e5e7eb;box-shadow:0 2px 8px rgba(0,0,0,0.06);">

      <!-- Header -->
      <tr>
        <td style="background:#1CBB9B;padding:24px 28px;">
          <p style="margin:0;color:#ffffff;font-size:20px;font-weight:bold;letter-spacing:-0.3px;">
            IFSC — Sistema de Requerimentos
          </p>
          <p style="margin:5px 0 0;color:#d1faf3;font-size:13px;">Câmpus Garopaba</p>
        </td>
      </tr>

      <!-- Body -->
      <tr>
        <td style="padding:32px 28px;color:#374151;font-size:14px;line-height:1.7;">
          ' . $body . '
        </td>
      </tr>

      <!-- Divider -->
      <tr><td style="padding:0 28px;"><hr style="border:none;border-top:1px solid #e5e7eb;margin:0;"></td></tr>

      <!-- Footer -->
      <tr>
        <td style="background:#f9fafb;padding:18px 28px;font-size:12px;color:#9ca3af;text-align:center;line-height:1.5;">
          Este é um e-mail automático. Por favor, não responda esta mensagem.<br>
          <strong style="color:#6b7280;">Instituto Federal de Santa Catarina — Câmpus Garopaba</strong>
        </td>
      </tr>

    </table>
  </td></tr>
</table>
</body>
</html>';
    }
}
