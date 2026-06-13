<?php
/**
 * Libro de Reclamaciones Virtual - YanaYacu Clean
 * Conforme a la Ley N° 29571 (Código de Protección y Defensa del Consumidor)
 * y el D.S. N° 011-2011-PCM (modificado por D.S. 101-2021-PCM).
 */

date_default_timezone_set('America/Lima');

define('EMPRESA_RAZON_SOCIAL', 'YANA YACU CLEAN S.A.C.S.');
define('EMPRESA_RUC',          '20615474755');
define('EMPRESA_DIRECCION',    'Cal. Calle 8 Mza. I\' Lote 3 H.U. Chacupe II (Altura Dren Bungalows Tio Sam), Lambayeque - Chiclayo - La Victoria');
define('EMPRESA_EMAIL',        'info@yanayacuclean.com');

$smtpConfigFile = __DIR__ . '/config.smtp.php';
if (file_exists($smtpConfigFile)) require $smtpConfigFile;

if (!defined('SMTP_PORT'))           define('SMTP_PORT', 465);
if (!defined('SMTP_FROM_NAME'))      define('SMTP_FROM_NAME', 'YanaYacu Clean');
if (!defined('EMPRESA_NOTIF_EMAIL')) define('EMPRESA_NOTIF_EMAIL', 'reclamaciones@yanayacuclean.com');

$recordsDir  = __DIR__ . '/reclamaciones';
$recordsFile = $recordsDir . '/records.json';
$lockFile    = $recordsDir . '/records.lock';

if (!is_dir($recordsDir)) {
    if (!@mkdir($recordsDir, 0755, true))
        $errorMsg = 'Error de configuración del servidor: no se puede crear el directorio de registros.';
}

$fpdfPath      = __DIR__ . '/lib/fpdf.php';
$fpdfAvailable = file_exists($fpdfPath);
if ($fpdfAvailable) require_once $fpdfPath;

// ── Generar PDF ───────────────────────────────────────────────────────────────
function generarPDFReclamo(array $d): string {
    if (!class_exists('FPDF')) return '';
    $c = fn(string $s): string => iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $s);

    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();

    // Cabecera navy
    $pdf->SetFillColor(13, 27, 62);
    $pdf->Rect(0, 0, 210, 38, 'F');
    $pdf->SetTextColor(77, 166, 214);
    $pdf->SetFont('Arial', 'B', 15);
    $pdf->SetXY(10, 7);
    $pdf->Cell(0, 8, $c('HOJA DE RECLAMACIÓN VIRTUAL'), 0, 1, 'C');
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetXY(10, 17);
    $pdf->Cell(0, 6, $c('Conforme a la Ley N 29571 - Codigo de Proteccion y Defensa del Consumidor'), 0, 1, 'C');
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetXY(10, 26);
    $pdf->Cell(0, 6, $c('Codigo: ' . $d['codigo'] . '     Fecha: ' . $d['fecha']), 0, 1, 'C');

    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetY(44);

    // Proveedor
    $pdf->SetFillColor(235, 247, 253);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(0, 6, $c('PROVEEDOR'), 0, 1, 'L', true);
    $pdf->SetFont('Arial', '', 9);
    $pdf->MultiCell(0, 5, $c('Razon Social: ' . EMPRESA_RAZON_SOCIAL . '   RUC: ' . EMPRESA_RUC), 0, 'L');
    $pdf->MultiCell(0, 5, $c('Domicilio: ' . EMPRESA_DIRECCION), 0, 'L');
    $pdf->Ln(2);

    $col1W = 45; $col2W = 135;

    foreach ([
        ['1. IDENTIFICACION DEL CONSUMIDOR RECLAMANTE', [
            ['Nombre completo:', $d['nombres']],
            ['Documento:', $d['doc_tipo'] . ' N ' . $d['doc_nro']],
            ['Domicilio:', $d['direccion'] . ', ' . $d['distrito'] . ' - ' . $d['provincia'] . ' (' . $d['departamento'] . ')'],
            ['Telefono:', $d['telefono'] ?: '-'],
            ['Email:', $d['email']],
            ...($d['menor_edad'] ? [['Apoderado:', $d['apoderado_nombres'] . ' (' . $d['apoderado_doc_tipo'] . ' ' . $d['apoderado_doc_nro'] . ')']] : []),
        ]],
        ['2. IDENTIFICACION DEL BIEN CONTRATADO', [
            ['Tipo de bien:', ucfirst($d['bien_tipo'])],
            ['Monto reclamado:', 'S/. ' . $d['monto']],
            ['Descripcion:', $d['bien_desc'] ?: '-'],
        ]],
    ] as [$title, $rows]) {
        $pdf->SetFillColor(13, 27, 62);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(0, 6, $c($title), 0, 1, 'L', true);
        $pdf->SetTextColor(0, 0, 0);
        foreach ($rows as [$label, $val]) {
            $pdf->SetFont('Arial', 'B', 8.5); $pdf->Cell($col1W, 5.5, $c($label), 'B', 0, 'L');
            $pdf->SetFont('Arial', '', 8.5);  $pdf->Cell($col2W, 5.5, $c($val),   'B', 1, 'L');
        }
        $pdf->Ln(3);
    }

    // Sección 3
    $pdf->SetFillColor(13, 27, 62);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(0, 6, $c('3. DETALLE DE LA RECLAMACION Y PEDIDO DEL CONSUMIDOR'), 0, 1, 'L', true);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', 'B', 9); $pdf->Cell(40, 6, $c('Tipo de incidencia:'), 0, 0);
    $pdf->SetFont('Arial', '', 9);
    $esReclamo = strtolower($d['reclamo_tipo']) === 'reclamo';
    $pdf->Cell(5, 6, $esReclamo ? 'X' : 'O', 1, 0, 'C');
    $pdf->Cell(22, 6, $c(' Reclamo'), 0, 0);
    $pdf->Cell(5, 6, !$esReclamo ? 'X' : 'O', 1, 0, 'C');
    $pdf->Cell(22, 6, $c(' Queja'), 0, 1);
    $pdf->Ln(1);
    $pdf->SetFillColor(248, 248, 248);
    $pdf->SetFont('Arial', 'B', 8.5); $pdf->Cell(0, 5, $c('Detalle del hecho:'), 0, 1);
    $pdf->SetFont('Arial', '', 8.5);  $pdf->MultiCell(0, 5, $c($d['detalle']), 1, 'L', true);
    $pdf->Ln(2);
    $pdf->SetFont('Arial', 'B', 8.5); $pdf->Cell(0, 5, $c('Pedido del consumidor:'), 0, 1);
    $pdf->SetFont('Arial', '', 8.5);  $pdf->MultiCell(0, 5, $c($d['pedido']), 1, 'L', true);
    $pdf->Ln(4);

    $pdf->SetFont('Arial', '', 8.5);
    $pdf->Cell(90, 5, $c('Firma del Consumidor'), 'T', 0, 'C');
    $pdf->Cell(10, 5, '', 0, 0);
    $pdf->Cell(90, 5, $c('Firma del Proveedor'), 'T', 1, 'C');
    $pdf->Ln(2);

    $pdf->SetFont('Arial', 'I', 7.5);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->MultiCell(0, 4, $c('La formulacion del reclamo no impide acudir a otras vias de solucion de controversias ni es requisito previo para interponer una denuncia ante el INDECOPI.'), 0, 'L');
    $pdf->SetFont('Arial', 'BI', 7.5);
    $pdf->MultiCell(0, 4, $c('El proveedor debe dar respuesta al reclamo o queja en un plazo no mayor a quince (15) dias habiles improrrogables (D.S. 101-2021-PCM).'), 0, 'L');

    return $pdf->Output('', 'S');
}

// ── Enviar correo SMTP ────────────────────────────────────────────────────────
function enviarCorreoSMTP(string $toEmail, string $subject, string $htmlBody, string $pdfB64 = '', string $pdfName = '', string &$err = ''): bool {
    if (!defined('SMTP_HOST') || !defined('SMTP_USER') || !defined('SMTP_PASS')) {
        $err = 'Configuración SMTP no encontrada (falta config.smtp.php).'; return false;
    }
    $enc = fn(string $s): string => '=?UTF-8?B?' . base64_encode($s) . '?=';
    $fromEmail = SMTP_USER;
    $eol = "\r\n";
    $headers  = 'Date: ' . date('r') . $eol;
    $headers .= 'From: ' . $enc(SMTP_FROM_NAME) . ' <' . $fromEmail . '>' . $eol;
    $headers .= 'To: <' . $toEmail . '>' . $eol;
    $headers .= 'Subject: ' . $enc($subject) . $eol;
    $headers .= 'Reply-To: ' . EMPRESA_EMAIL . $eol;
    $headers .= 'MIME-Version: 1.0' . $eol;
    if ($pdfB64) {
        $b = '----=_Part_' . md5(uniqid());
        $headers .= 'Content-Type: multipart/mixed; boundary="' . $b . '"' . $eol;
        $body  = '--' . $b . $eol . 'Content-Type: text/html; charset=UTF-8' . $eol . 'Content-Transfer-Encoding: 7bit' . $eol . $eol . $htmlBody . $eol;
        $body .= '--' . $b . $eol . 'Content-Type: application/pdf; name="' . $pdfName . '"' . $eol . 'Content-Transfer-Encoding: base64' . $eol . 'Content-Disposition: attachment; filename="' . $pdfName . '"' . $eol . $eol . $pdfB64 . $eol;
        $body .= '--' . $b . '--';
    } else {
        $headers .= 'Content-Type: text/html; charset=UTF-8' . $eol;
        $body = $htmlBody;
    }
    $message = str_replace($eol . '.', $eol . '..', str_replace("\n", $eol, str_replace(["\r\n","\r","\n"], "\n", $headers . $eol . $body)));
    $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]]);
    $fp  = @stream_socket_client((SMTP_PORT == 465 ? 'ssl://' : '') . SMTP_HOST . ':' . SMTP_PORT, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) { $err = "Conexión SMTP fallida: $errstr ($errno)"; return false; }
    stream_set_timeout($fp, 20);
    $read = function() use ($fp): string { $d=''; while(($l=fgets($fp,515))!==false){$d.=$l;if(strlen($l)<4||$l[3]===' ')break;} return $d; };
    $cmd  = fn(string $c): string => (fwrite($fp, $c."\r\n") && $read());
    $ok   = function(string $r, array $codes) use (&$err): bool { foreach($codes as $c){if(strncmp($r,$c,strlen($c))===0)return true;} $err=trim($r);return false; };
    $fail = function() use ($fp) { @fwrite($fp,"QUIT\r\n"); @fclose($fp); return false; };

    if (!$ok($read(),                              ['220'])) return $fail();
    if (!$ok($cmd('EHLO '.SMTP_HOST),             ['250'])) return $fail();
    if (!$ok($cmd('AUTH LOGIN'),                   ['334'])) return $fail();
    if (!$ok($cmd(base64_encode(SMTP_USER)),       ['334'])) return $fail();
    if (!$ok($cmd(base64_encode(SMTP_PASS)),       ['235'])) return $fail();
    if (!$ok($cmd('MAIL FROM:<'.$fromEmail.'>'),   ['250'])) return $fail();
    if (!$ok($cmd('RCPT TO:<'.$toEmail.'>'),  ['250','251'])) return $fail();
    if (!$ok($cmd('DATA'),                         ['354'])) return $fail();
    if (!$ok($cmd($message."\r\n."),               ['250'])) return $fail();
    $cmd('QUIT'); fclose($fp);
    return true;
}

$success = false; $errorMsg = ''; $generatedCode = ''; $submittedData = [];

set_error_handler(function($errno) use (&$errorMsg) {
    if ($errno === E_ERROR || $errno === E_USER_ERROR) $errorMsg = 'Error interno. Inténtelo más tarde.';
    return true;
});

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $f = fn(string $k, int $filter = FILTER_SANITIZE_SPECIAL_CHARS) => filter_input(INPUT_POST, $k, $filter);
    $nombres      = $f('nombres');
    $doc_tipo     = $f('doc_tipo');
    $doc_nro      = $f('doc_nro');
    $email        = $f('email', FILTER_VALIDATE_EMAIL);
    $telefono     = $f('telefono');
    $direccion    = $f('direccion');
    $departamento = $f('departamento');
    $provincia    = $f('provincia');
    $distrito     = $f('distrito');
    $menor_edad   = isset($_POST['menor_edad']);
    $ap_nombres   = $f('apoderado_nombres');
    $ap_doc_tipo  = $f('apoderado_doc_tipo');
    $ap_doc_nro   = $f('apoderado_doc_nro');
    $bien_tipo    = $f('bien_tipo');
    $monto        = $f('monto', FILTER_SANITIZE_NUMBER_FLOAT | FILTER_FLAG_ALLOW_FRACTION);
    $bien_desc    = $f('bien_desc');
    $reclamo_tipo = $f('reclamo_tipo');
    $detalle      = $f('detalle');
    $pedido       = $f('pedido');

    if (!$nombres||!$doc_tipo||!$doc_nro||!$email||!$direccion||!$bien_tipo||!$reclamo_tipo||!$detalle||!$pedido||!empty($errorMsg)) {
        if (empty($errorMsg)) $errorMsg = 'Por favor, rellene todos los campos obligatorios.';
    } else {
        $fp = fopen($lockFile, 'w');
        if ($fp && flock($fp, LOCK_EX)) {
            $year    = date('Y');
            $records = file_exists($recordsFile) ? (json_decode(file_get_contents($recordsFile), true) ?: []) : [];
            $count   = count(array_filter($records, fn($r) => ($r['year'] ?? '') == $year));
            $generatedCode = sprintf('REC-%s-%04d', $year, $count + 1);
            $submittedData = [
                'codigo' => $generatedCode, 'year' => $year, 'fecha' => date('d/m/Y h:i A'),
                'nombres' => $nombres, 'doc_tipo' => $doc_tipo, 'doc_nro' => $doc_nro,
                'email' => $email, 'telefono' => $telefono, 'direccion' => $direccion,
                'departamento' => $departamento, 'provincia' => $provincia, 'distrito' => $distrito,
                'menor_edad' => $menor_edad,
                'apoderado_nombres'  => $menor_edad ? $ap_nombres  : '',
                'apoderado_doc_tipo' => $menor_edad ? $ap_doc_tipo : '',
                'apoderado_doc_nro'  => $menor_edad ? $ap_doc_nro  : '',
                'bien_tipo' => $bien_tipo,
                'monto'     => $monto ? number_format((float)$monto, 2, '.', '') : '0.00',
                'bien_desc' => $bien_desc, 'reclamo_tipo' => $reclamo_tipo,
                'detalle' => $detalle, 'pedido' => $pedido, 'estado' => 'Pendiente',
            ];
            $records[] = $submittedData;
            file_put_contents($recordsFile, json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            flock($fp, LOCK_UN);
            $success = true;
        } else {
            $errorMsg = 'Error al registrar en el servidor. Inténtelo nuevamente.';
        }
        if ($fp) fclose($fp);

        if ($success) {
            $pdfBytes = '';
            if ($fpdfAvailable) { try { $pdfBytes = generarPDFReclamo($submittedData); } catch(Exception $e) {} }
            $pdfB64  = $pdfBytes ? chunk_split(base64_encode($pdfBytes)) : '';
            $pdfName = 'Hoja_Reclamacion_' . $generatedCode . '.pdf';
            if ($pdfBytes) @file_put_contents($recordsDir . '/' . $pdfName, $pdfBytes);

            $emailBody = "
            <div style='font-family:Arial,sans-serif;color:#333;max-width:600px;margin:0 auto;border:1px solid #e0e0e0;border-radius:8px;overflow:hidden;'>
                <div style='background:#0D1B3E;padding:25px;text-align:center;'>
                    <h2 style='margin:0;font-size:22px;color:#4DA6D6;'>HOJA DE RECLAMACIÓN VIRTUAL</h2>
                    <p style='margin:5px 0 0;color:#fff;font-size:14px;font-weight:bold;'>Código: $generatedCode</p>
                </div>
                <div style='padding:25px;line-height:1.6;'>
                    <p>Estimado(a) <strong>$nombres</strong>,</p>
                    <p>Confirmamos la recepción de tu reclamación registrada el <strong>" . date('d/m/Y') . "</strong>. Adjunto encontrarás el cargo de tu Hoja de Reclamación Virtual.</p>
                    <p>Daremos respuesta en un plazo máximo de <strong>15 días hábiles</strong> (Ley N° 29571).</p>
                    <hr style='border:none;border-top:1px solid #eee;margin:20px 0;'>
                    <table style='width:100%;border-collapse:collapse;font-size:14px;'>
                        <tr><td style='padding:6px 0;font-weight:bold;width:150px;'>Consumidor:</td><td>$nombres ($doc_tipo $doc_nro)</td></tr>
                        <tr><td style='padding:6px 0;font-weight:bold;'>Bien:</td><td style='text-transform:capitalize;'>$bien_tipo — S/. " . ($monto ?: '0.00') . "</td></tr>
                        <tr><td style='padding:6px 0;font-weight:bold;'>Incidencia:</td><td style='font-weight:bold;color:" . ($reclamo_tipo=='reclamo'?'#dc2626':'#d97706') . ";text-transform:capitalize;'>$reclamo_tipo</td></tr>
                        <tr><td style='padding:6px 0;font-weight:bold;vertical-align:top;'>Detalle:</td><td style='background:#f9f9f9;padding:8px;border-radius:4px;'>$detalle</td></tr>
                        <tr><td style='padding:6px 0;font-weight:bold;vertical-align:top;'>Pedido:</td><td style='background:#f9f9f9;padding:8px;border-radius:4px;'>$pedido</td></tr>
                    </table>
                </div>
                <div style='background:#f5f5f5;padding:15px;text-align:center;font-size:12px;color:#888;border-top:1px solid #e0e0e0;'>Cargo automático — no responder a este mensaje.</div>
            </div>";

            $errC = ''; enviarCorreoSMTP($email, "Cargo de Hoja de Reclamación N° $generatedCode - YanaYacu Clean", $emailBody, $pdfB64, $pdfName, $errC);

            $emailEmpresa = "
            <div style='font-family:Arial,sans-serif;color:#333;max-width:600px;margin:0 auto;border:1px solid #e0e0e0;border-radius:8px;padding:25px;'>
                <h2 style='color:#dc2626;margin-top:0;'>Nuevo Reclamo/Queja — $generatedCode</h2>
                <p>Plazo máximo: <strong>15 días hábiles</strong>.</p>
                <p><strong>Reclamante:</strong> $nombres · $doc_tipo $doc_nro · $email · $telefono</p>
                <p><strong>Domicilio:</strong> $direccion, $distrito - $provincia ($departamento)</p>
                <p><strong>Bien:</strong> " . ucfirst($bien_tipo) . " · S/. " . ($monto ?: '0.00') . " · $bien_desc</p>
                <p><strong>Tipo:</strong> " . strtoupper($reclamo_tipo) . "</p>
                <div style='background:#f7f7f7;padding:12px;border-left:4px solid #dc2626;margin-bottom:10px;'><strong>Detalle:</strong><br>" . nl2br($detalle) . "</div>
                <div style='background:#f7f7f7;padding:12px;border-left:4px solid #4DA6D6;'><strong>Pedido:</strong><br>" . nl2br($pedido) . "</div>
            </div>";
            $errE = ''; enviarCorreoSMTP(EMPRESA_NOTIF_EMAIL, "NUEVA RECLAMACIÓN N° $generatedCode - $nombres", $emailEmpresa, $pdfB64, $pdfName, $errE);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Libro de Reclamaciones - YanaYacu Clean</title>
    <meta name="description" content="Libro de Reclamaciones Virtual de YanaYacu Clean. Conforme al Código de Protección al Consumidor.">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        navy:  { deep: '#0D1B3E', mid: '#1A2F5C', light: '#2A4A8A' },
                        brand: { DEFAULT: '#4DA6D6', light: '#B3DFF0', pale: '#EBF7FD' },
                    },
                    fontFamily: { sans: ['Poppins', 'sans-serif'] }
                }
            }
        }
    </script>
    <style>
        * { box-sizing: border-box; }
        body { background: #FFFFFF; color: #111827; font-family: 'Poppins', sans-serif; overflow-x: hidden; }
        .glass { background: #FFFFFF; border: 1px solid rgba(77,166,214,0.22); box-shadow: 0 2px 12px rgba(13,27,62,0.07); }
        .grad-text { background: linear-gradient(135deg,#0D1B3E 0%,#4DA6D6 100%); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }
        .form-input { width:100%; background:rgba(13,27,62,0.03); border:1px solid rgba(13,27,62,0.14); border-radius:12px; padding:10px 14px; color:#111827; font-size:.875rem; transition:border-color .2s,box-shadow .2s; outline:none; font-family:inherit; }
        .form-input::placeholder { color:rgba(13,27,62,0.3); }
        .form-input:focus { border-color:#4DA6D6; box-shadow:0 0 0 2px rgba(77,166,214,0.18); }
        select.form-input { background-color:#FFFFFF; color:#111827; }
        .btn-primary { background:linear-gradient(135deg,#0D1B3E,#4DA6D6); transition:transform .25s,box-shadow .25s; }
        .btn-primary:hover { transform:translateY(-2px); box-shadow:0 10px 28px rgba(13,27,62,0.28); }
        .btn-sec { border:1px solid rgba(13,27,62,0.35); color:#0D1B3E; background:transparent; transition:all .3s; }
        .btn-sec:hover { background:rgba(13,27,62,0.06); transform:translateY(-1px); }
        @media print {
            body { background:white!important; color:black!important; font-size:11px!important; }
            .no-print { display:none!important; }
            .print-card { background:white!important; border:1px solid #ddd!important; box-shadow:none!important; color:black!important; padding:15px!important; border-radius:0!important; }
            .print-field { border-bottom:1px solid #ccc!important; padding:4px 0!important; background:transparent!important; color:black!important; }
            .print-title { color:black!important; font-size:16px!important; }
            .print-label { color:#555!important; font-weight:bold!important; font-size:10px!important; }
        }
    </style>
</head>
<body class="min-h-screen flex flex-col">

<!-- HEADER -->
<header class="no-print w-full border-b border-navy-deep/10 bg-white shadow-sm sticky top-0 z-50">
    <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
        <a href="index.html" class="flex items-center gap-3">
            <div class="bg-white rounded-xl px-2 py-1 border border-navy-deep/10 shadow-sm">
                <img src="LOGO - YANA YACU CLEAN.jpg" alt="YanaYacu Clean" class="h-9 w-auto object-contain">
            </div>
        </a>
        <a href="index.html" class="text-navy-deep/50 hover:text-navy-deep text-xs flex items-center gap-1.5 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Volver a la web
        </a>
    </div>
</header>

<main class="flex-grow max-w-4xl w-full mx-auto px-4 py-10">

<?php if ($success): ?>
<!-- SUCCESS -->
<div class="print-card glass rounded-3xl p-8 sm:p-12 shadow-2xl relative border border-emerald-200">
    <div class="no-print absolute -right-20 -top-20 w-80 h-80 bg-emerald-50 rounded-full blur-3xl pointer-events-none"></div>
    <div class="no-print text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-600 text-3xl mb-4">✓</div>
        <h1 class="text-3xl font-black text-navy-deep mb-2">¡Reclamación Registrada!</h1>
        <p class="text-navy-deep/60 text-sm max-w-lg mx-auto">
            Tu reclamo/queja ha sido procesado correctamente. Se envió un cargo al correo <strong class="text-navy-deep"><?= htmlspecialchars($submittedData['email']) ?></strong>.
        </p>
    </div>

    <div class="bg-navy-deep/5 p-6 rounded-2xl border border-navy-deep/5 print-card">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center border-b border-navy-deep/10 pb-5 mb-6 print-field">
            <div>
                <h2 class="text-xl font-black text-navy-deep print-title">HOJA DE RECLAMACIÓN VIRTUAL</h2>
                <p class="text-xs text-navy-deep/50 mt-1">Conforme a la Ley N° 29571 / D.S. N° 011-2011-PCM</p>
            </div>
            <div class="mt-4 sm:mt-0 text-left sm:text-right">
                <div class="text-xs text-brand font-black uppercase tracking-wider print-label">CÓDIGO DE RECLAMACIÓN</div>
                <div class="text-xl font-black text-emerald-600 print-title mt-0.5"><?= htmlspecialchars($submittedData['codigo']) ?></div>
                <div class="text-[10px] text-navy-deep/40 mt-1">Fecha: <?= htmlspecialchars($submittedData['fecha']) ?></div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 text-xs text-navy-deep/60 border-b border-navy-deep/5 pb-4 print-field">
            <div><span class="block font-bold text-navy-deep/80 print-label">Proveedor:</span><?= EMPRESA_RAZON_SOCIAL ?></div>
            <div><span class="block font-bold text-navy-deep/80 print-label">RUC:</span><?= EMPRESA_RUC ?></div>
            <div class="md:col-span-2"><span class="block font-bold text-navy-deep/80 print-label">Domicilio Fiscal:</span><?= EMPRESA_DIRECCION ?></div>
        </div>

        <?php foreach ([
            ['1. Identificación del Consumidor Reclamante', [
                ['Nombre:', '<strong class="text-navy-deep print-title">' . htmlspecialchars($submittedData['nombres']) . '</strong>'],
                ['Documento:', htmlspecialchars($submittedData['doc_tipo']) . ' - ' . htmlspecialchars($submittedData['doc_nro'])],
                ['Domicilio:', htmlspecialchars($submittedData['direccion']) . ', ' . htmlspecialchars($submittedData['distrito']) . ' - ' . htmlspecialchars($submittedData['provincia']) . ' (' . htmlspecialchars($submittedData['departamento']) . ')'],
                ['Contacto:', 'Email: ' . htmlspecialchars($submittedData['email']) . ' | Tel: ' . htmlspecialchars($submittedData['telefono'] ?: '-')],
            ]],
            ['2. Identificación del Bien Contratado', [
                ['Tipo de Bien:', '<span class="capitalize">' . htmlspecialchars($submittedData['bien_tipo']) . '</span>'],
                ['Monto:', 'S/. ' . htmlspecialchars($submittedData['monto'])],
                ['Descripción:', htmlspecialchars($submittedData['bien_desc'] ?: 'No especificado')],
            ]],
        ] as [$title, $fields]): ?>
        <h3 class="text-sm font-bold uppercase tracking-wider text-brand mb-3 print-label"><?= $title ?></h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6 text-xs text-navy-deep/70 border-b border-navy-deep/5 pb-4 print-field">
            <?php foreach ($fields as [$label, $val]): ?>
            <div><span class="block text-navy-deep/40 print-label"><?= $label ?></span><span class="text-navy-deep"><?= $val ?></span></div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>

        <?php if ($submittedData['menor_edad']): ?>
        <div class="mb-6 bg-navy-deep/5 p-3 rounded-lg border border-navy-deep/5 text-xs print-field">
            <span class="block font-bold text-navy-deep/70 print-label">Apoderado:</span>
            <span class="text-navy-deep"><?= htmlspecialchars($submittedData['apoderado_nombres']) ?> (<?= htmlspecialchars($submittedData['apoderado_doc_tipo']) ?> <?= htmlspecialchars($submittedData['apoderado_doc_nro']) ?>)</span>
        </div>
        <?php endif; ?>

        <h3 class="text-sm font-bold uppercase tracking-wider text-brand mb-3 print-label">3. Detalle de la Reclamación y Pedido del Consumidor</h3>
        <div class="space-y-4 text-xs text-navy-deep/70">
            <div class="flex items-center gap-3">
                <span class="text-navy-deep/40 print-label">Tipo:</span>
                <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider <?= $submittedData['reclamo_tipo']==='reclamo'?'bg-red-50 text-red-600 border border-red-200':'bg-amber-50 text-amber-600 border border-amber-200' ?> print-title"><?= htmlspecialchars($submittedData['reclamo_tipo']) ?></span>
            </div>
            <div>
                <span class="block text-navy-deep/40 print-label">Detalle:</span>
                <div class="bg-navy-deep/5 p-3 rounded-lg border border-navy-deep/5 whitespace-pre-wrap mt-1 text-navy-deep print-field"><?= htmlspecialchars($submittedData['detalle']) ?></div>
            </div>
            <div>
                <span class="block text-navy-deep/40 print-label">Pedido:</span>
                <div class="bg-navy-deep/5 p-3 rounded-lg border border-navy-deep/5 whitespace-pre-wrap mt-1 text-navy-deep print-field"><?= htmlspecialchars($submittedData['pedido']) ?></div>
            </div>
        </div>
    </div>

    <div class="no-print mt-8 flex flex-col sm:flex-row justify-center gap-4">
        <button onclick="window.print()" class="btn-primary text-white font-bold px-6 py-3.5 rounded-xl text-sm flex items-center justify-center gap-2 cursor-pointer">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            Descargar / Imprimir Reclamo
        </button>
        <a href="index.html" class="btn-sec text-center font-semibold px-6 py-3.5 rounded-xl text-sm flex items-center justify-center gap-2">
            Volver a la Página de Inicio
        </a>
    </div>
</div>

<?php else: ?>
<!-- FORMULARIO -->
<div class="no-print text-center mb-10">
    <div class="inline-flex items-center gap-2 bg-brand-pale border border-brand/30 rounded-full px-4 py-1.5 mb-4">
        <span class="w-2 h-2 rounded-full bg-brand"></span>
        <span class="text-navy-deep text-[10px] font-semibold tracking-widest uppercase">Ley N° 29571</span>
    </div>
    <h1 class="text-4xl font-black text-navy-deep mb-2">Libro de Reclamaciones <span class="grad-text">Virtual</span></h1>
    <p class="text-navy-deep/60 text-sm max-w-lg mx-auto">Completa el formulario para registrar tu queja o reclamo. Responderemos en un plazo máximo de 15 días hábiles.</p>
</div>

<?php if (!empty($errorMsg)): ?>
<div class="no-print bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6 text-sm flex items-center gap-2">
    <span class="text-lg">⚠️</span> <?= $errorMsg ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div class="lg:col-span-2">
        <div class="glass rounded-3xl p-6 sm:p-8 shadow-xl">
            <form method="POST" action="libro-de-reclamaciones.php" class="space-y-6">

                <!-- Sección 1 -->
                <div class="border-b border-navy-deep/10 pb-5">
                    <h2 class="text-lg font-black text-navy-deep flex items-center gap-2 mb-4">
                        <span class="w-6 h-6 rounded-lg bg-navy-deep flex items-center justify-center text-xs text-white font-black">1</span>
                        Identificación del Consumidor
                    </h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-navy-deep/50 text-[10px] uppercase tracking-widest mb-1.5 font-bold">Nombres y Apellidos completos *</label>
                            <input type="text" name="nombres" required placeholder="Ingresa tus nombres completos" class="form-input" value="<?= isset($_POST['nombres'])?htmlspecialchars($_POST['nombres']):'' ?>">
                        </div>
                        <div>
                            <label class="block text-navy-deep/50 text-[10px] uppercase tracking-widest mb-1.5 font-bold">Tipo Documento *</label>
                            <select name="doc_tipo" required class="form-input">
                                <option value="DNI" <?= (($_POST['doc_tipo']??'')==='DNI')?'selected':'' ?>>DNI (Perú)</option>
                                <option value="CE" <?= (($_POST['doc_tipo']??'')==='CE')?'selected':'' ?>>Carnet de Extranjería</option>
                                <option value="PASAPORTE" <?= (($_POST['doc_tipo']??'')==='PASAPORTE')?'selected':'' ?>>Pasaporte</option>
                                <option value="RUC" <?= (($_POST['doc_tipo']??'')==='RUC')?'selected':'' ?>>RUC</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-navy-deep/50 text-[10px] uppercase tracking-widest mb-1.5 font-bold">Nro Documento *</label>
                            <input type="text" name="doc_nro" required placeholder="Número de documento" class="form-input" value="<?= isset($_POST['doc_nro'])?htmlspecialchars($_POST['doc_nro']):'' ?>">
                        </div>
                        <div>
                            <label class="block text-navy-deep/50 text-[10px] uppercase tracking-widest mb-1.5 font-bold">Correo Electrónico *</label>
                            <input type="email" name="email" required placeholder="nombre@correo.com" class="form-input" value="<?= isset($_POST['email'])?htmlspecialchars($_POST['email']):'' ?>">
                        </div>
                        <div>
                            <label class="block text-navy-deep/50 text-[10px] uppercase tracking-widest mb-1.5 font-bold">Teléfono / Celular</label>
                            <input type="tel" name="telefono" placeholder="Número de contacto" class="form-input" value="<?= isset($_POST['telefono'])?htmlspecialchars($_POST['telefono']):'' ?>">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-navy-deep/50 text-[10px] uppercase tracking-widest mb-1.5 font-bold">Dirección Completa *</label>
                            <input type="text" name="direccion" required placeholder="Av., Calle, Nro., Dpto., Urb." class="form-input" value="<?= isset($_POST['direccion'])?htmlspecialchars($_POST['direccion']):'' ?>">
                        </div>
                        <div>
                            <label class="block text-navy-deep/50 text-[10px] uppercase tracking-widest mb-1.5 font-bold">Departamento *</label>
                            <input type="text" name="departamento" required placeholder="Ej: Lima" class="form-input" value="<?= isset($_POST['departamento'])?htmlspecialchars($_POST['departamento']):'' ?>">
                        </div>
                        <div>
                            <label class="block text-navy-deep/50 text-[10px] uppercase tracking-widest mb-1.5 font-bold">Provincia *</label>
                            <input type="text" name="provincia" required placeholder="Ej: Chiclayo" class="form-input" value="<?= isset($_POST['provincia'])?htmlspecialchars($_POST['provincia']):'' ?>">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-navy-deep/50 text-[10px] uppercase tracking-widest mb-1.5 font-bold">Distrito *</label>
                            <input type="text" name="distrito" required placeholder="Ej: La Victoria" class="form-input" value="<?= isset($_POST['distrito'])?htmlspecialchars($_POST['distrito']):'' ?>">
                        </div>
                    </div>
                    <div class="mt-4 bg-navy-deep/5 p-4 rounded-xl border border-navy-deep/5">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" id="menor_edad" name="menor_edad" class="w-4 h-4 rounded accent-brand flex-shrink-0 cursor-pointer" onclick="toggleApoderado()" <?= isset($_POST['menor_edad'])?'checked':'' ?>>
                            <span class="text-navy-deep/70 text-xs">Soy menor de edad (se requiere ingresar los datos de un tutor/apoderado).</span>
                        </label>
                        <div id="apoderado_fields" class="mt-4 pt-4 border-t border-navy-deep/10 grid grid-cols-1 sm:grid-cols-2 gap-4 hidden">
                            <div class="sm:col-span-2">
                                <label class="block text-navy-deep/50 text-[10px] uppercase tracking-widest mb-1.5 font-bold">Nombres del Apoderado *</label>
                                <input type="text" id="apoderado_nombres" name="apoderado_nombres" placeholder="Nombres del padre, madre o apoderado" class="form-input" value="<?= isset($_POST['apoderado_nombres'])?htmlspecialchars($_POST['apoderado_nombres']):'' ?>">
                            </div>
                            <div>
                                <label class="block text-navy-deep/50 text-[10px] uppercase tracking-widest mb-1.5 font-bold">Tipo Doc. Apoderado *</label>
                                <select id="apoderado_doc_tipo" name="apoderado_doc_tipo" class="form-input">
                                    <option value="DNI">DNI</option>
                                    <option value="CE">Carnet de Extranjería</option>
                                    <option value="PASAPORTE">Pasaporte</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-navy-deep/50 text-[10px] uppercase tracking-widest mb-1.5 font-bold">Nro Doc. Apoderado *</label>
                                <input type="text" id="apoderado_doc_nro" name="apoderado_doc_nro" placeholder="Nro de documento" class="form-input" value="<?= isset($_POST['apoderado_doc_nro'])?htmlspecialchars($_POST['apoderado_doc_nro']):'' ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sección 2 -->
                <div class="border-b border-navy-deep/10 pb-5">
                    <h2 class="text-lg font-black text-navy-deep flex items-center gap-2 mb-4">
                        <span class="w-6 h-6 rounded-lg bg-navy-deep flex items-center justify-center text-xs text-white font-black">2</span>
                        Identificación del Bien Contratado
                    </h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-navy-deep/50 text-[10px] uppercase tracking-widest mb-1.5 font-bold">Tipo de bien *</label>
                            <div class="flex items-center gap-6 mt-2">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="bien_tipo" value="producto" required class="w-4 h-4 accent-brand" <?= (!isset($_POST['bien_tipo'])||$_POST['bien_tipo']==='producto')?'checked':'' ?>>
                                    <span class="text-navy-deep/80 text-sm">Producto</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="bien_tipo" value="servicio" class="w-4 h-4 accent-brand" <?= (($_POST['bien_tipo']??'')==='servicio')?'checked':'' ?>>
                                    <span class="text-navy-deep/80 text-sm">Servicio</span>
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="block text-navy-deep/50 text-[10px] uppercase tracking-widest mb-1.5 font-bold">Monto Reclamado (S/. opcional)</label>
                            <input type="number" step="0.01" min="0" name="monto" placeholder="S/. 0.00" class="form-input" value="<?= isset($_POST['monto'])?htmlspecialchars($_POST['monto']):'' ?>">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-navy-deep/50 text-[10px] uppercase tracking-widest mb-1.5 font-bold">Descripción del bien contratado</label>
                            <textarea rows="2" name="bien_desc" placeholder="Describe brevemente el producto o servicio contratado" class="form-input resize-none"><?= isset($_POST['bien_desc'])?htmlspecialchars($_POST['bien_desc']):'' ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Sección 3 -->
                <div>
                    <h2 class="text-lg font-black text-navy-deep flex items-center gap-2 mb-4">
                        <span class="w-6 h-6 rounded-lg bg-navy-deep flex items-center justify-center text-xs text-white font-black">3</span>
                        Detalle del Reclamo y Pedido
                    </h2>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-navy-deep/50 text-[10px] uppercase tracking-widest mb-1.5 font-bold">Tipo de Reclamación *</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-2">
                                <label class="flex items-start gap-2.5 p-3 rounded-xl bg-navy-deep/5 border border-navy-deep/5 cursor-pointer hover:bg-brand-pale hover:border-brand/20 transition-colors">
                                    <input type="radio" name="reclamo_tipo" value="reclamo" required class="w-4 h-4 mt-0.5 accent-brand" <?= (!isset($_POST['reclamo_tipo'])||$_POST['reclamo_tipo']==='reclamo')?'checked':'' ?>>
                                    <div>
                                        <span class="block text-navy-deep text-xs font-bold uppercase tracking-wider">Reclamo</span>
                                        <span class="text-[10px] text-navy-deep/50 leading-tight block mt-0.5">Disconformidad relacionada a los productos o servicios contratados.</span>
                                    </div>
                                </label>
                                <label class="flex items-start gap-2.5 p-3 rounded-xl bg-navy-deep/5 border border-navy-deep/5 cursor-pointer hover:bg-brand-pale hover:border-brand/20 transition-colors">
                                    <input type="radio" name="reclamo_tipo" value="queja" class="w-4 h-4 mt-0.5 accent-brand" <?= (($_POST['reclamo_tipo']??'')==='queja')?'checked':'' ?>>
                                    <div>
                                        <span class="block text-navy-deep text-xs font-bold uppercase tracking-wider">Queja</span>
                                        <span class="text-[10px] text-navy-deep/50 leading-tight block mt-0.5">Disconformidad no relacionada a los productos. Malestar respecto a la atención.</span>
                                    </div>
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="block text-navy-deep/50 text-[10px] uppercase tracking-widest mb-1.5 font-bold">Detalle de tu Queja o Reclamo *</label>
                            <textarea rows="4" name="detalle" required placeholder="Describe de forma detallada y ordenada lo ocurrido..." class="form-input resize-none"><?= isset($_POST['detalle'])?htmlspecialchars($_POST['detalle']):'' ?></textarea>
                        </div>
                        <div>
                            <label class="block text-navy-deep/50 text-[10px] uppercase tracking-widest mb-1.5 font-bold">Pedido concreto (¿Qué solicitas?) *</label>
                            <textarea rows="3" name="pedido" required placeholder="Indica tu solicitud (cambio, devolución, compensación, etc.)" class="form-input resize-none"><?= isset($_POST['pedido'])?htmlspecialchars($_POST['pedido']):'' ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Declaraciones -->
                <div class="pt-4 border-t border-navy-deep/10 space-y-3">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" required class="w-4 h-4 mt-0.5 rounded accent-brand flex-shrink-0 cursor-pointer">
                        <span class="text-navy-deep/40 text-[10px] leading-relaxed">Declaro ser el usuario titular y que los datos consignados son reales y verdaderos.</span>
                    </label>
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" required class="w-4 h-4 mt-0.5 rounded accent-brand flex-shrink-0 cursor-pointer">
                        <span class="text-navy-deep/40 text-[10px] leading-relaxed">Acepto el tratamiento de mis datos personales conforme a la <strong class="text-navy-deep/60">Ley N° 29733</strong> (Protección de Datos Personales en el Perú).</span>
                    </label>
                </div>

                <button type="submit" class="btn-primary w-full py-4 rounded-2xl text-white font-black text-sm tracking-wider cursor-pointer">
                    PRESENTAR RECLAMACIÓN
                </button>
            </form>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="space-y-6">
        <div class="glass rounded-3xl p-6 text-xs space-y-3 shadow-md">
            <h3 class="text-sm font-black text-navy-deep uppercase tracking-wider mb-2">Datos del Proveedor</h3>
            <div>
                <span class="block text-navy-deep/40 uppercase tracking-widest text-[9px] mb-0.5">Razón Social</span>
                <strong class="text-navy-deep text-sm"><?= EMPRESA_RAZON_SOCIAL ?></strong>
            </div>
            <div>
                <span class="block text-navy-deep/40 uppercase tracking-widest text-[9px] mb-0.5">RUC</span>
                <strong class="text-navy-deep text-sm"><?= EMPRESA_RUC ?></strong>
            </div>
            <div>
                <span class="block text-navy-deep/40 uppercase tracking-widest text-[9px] mb-0.5">Dirección</span>
                <span class="text-navy-deep/80"><?= EMPRESA_DIRECCION ?></span>
            </div>
        </div>

        <div class="glass rounded-3xl p-6 shadow-md">
            <div class="flex items-center gap-3 mb-4">
                <span class="text-2xl">📋</span>
                <h3 class="text-sm font-black text-navy-deep uppercase tracking-wider">Aviso Virtual</h3>
            </div>
            <p class="text-navy-deep/60 text-xs leading-relaxed mb-4">
                Conforme al Código de Protección y Defensa del Consumidor, contamos con un Libro de Reclamaciones Virtual:
            </p>
            <div class="relative rounded-2xl overflow-hidden border border-navy-deep/10 bg-navy-deep/5 group">
                <img src="Libro-reclamaciones/AvisoVirtual_page1.png" alt="Aviso Virtual INDECOPI" class="w-full h-auto object-cover opacity-80 group-hover:opacity-100 transition-opacity">
                <div class="absolute inset-0 bg-navy-deep/30 flex items-center justify-center group-hover:bg-navy-deep/10 transition-all">
                    <button onclick="openNoticeModal()" class="bg-brand text-white font-bold px-4 py-2 rounded-xl text-xs shadow-lg hover:scale-105 transition-transform cursor-pointer">
                        Ver a Pantalla Completa
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

</main>

<footer class="no-print border-t border-navy-deep/10 bg-white py-6 mt-12 text-center text-xs text-navy-deep/40">
    <div class="max-w-6xl mx-auto px-4">
        © <?= date('Y') ?> <?= EMPRESA_RAZON_SOCIAL ?> · RUC <?= EMPRESA_RUC ?> · Todos los derechos reservados.<br>
        <span class="text-[10px] text-navy-deep/20 mt-1 block">Regulado por INDECOPI y en conformidad con la Ley de Protección de Datos Personales N° 29733.</span>
    </div>
</footer>

<!-- Modal Aviso Virtual -->
<div id="notice-modal" class="no-print fixed inset-0 z-[100] hidden items-center justify-center p-4 bg-black/90 backdrop-blur-sm">
    <div class="absolute inset-0 cursor-pointer" onclick="closeNoticeModal()"></div>
    <div class="relative z-10 max-w-lg w-full bg-white rounded-3xl p-4 shadow-2xl flex flex-col items-center">
        <button onclick="closeNoticeModal()" class="absolute -top-10 right-0 sm:-right-8 text-white hover:text-brand text-3xl font-light cursor-pointer">✕</button>
        <div class="w-full overflow-y-auto max-h-[80vh] border border-gray-200 rounded-2xl">
            <img src="Libro-reclamaciones/AvisoVirtual_page1.png" alt="Aviso Virtual Oficial INDECOPI" class="w-full h-auto">
        </div>
        <p class="text-gray-500 text-[10px] mt-3 text-center">Aviso oficial de disponibilidad de Libro de Reclamaciones - INDECOPI.</p>
    </div>
</div>

<script>
function toggleApoderado() {
    const cb = document.getElementById('menor_edad');
    const f  = document.getElementById('apoderado_fields');
    const n  = document.getElementById('apoderado_nombres');
    const d  = document.getElementById('apoderado_doc_nro');
    if (cb.checked) { f.classList.remove('hidden'); n.required=true; d.required=true; }
    else            { f.classList.add('hidden');    n.required=false; d.required=false; n.value=''; d.value=''; }
}
window.addEventListener('DOMContentLoaded', () => { if(document.getElementById('menor_edad')) toggleApoderado(); });
function openNoticeModal()  { const m=document.getElementById('notice-modal'); m.classList.remove('hidden'); m.classList.add('flex'); }
function closeNoticeModal() { const m=document.getElementById('notice-modal'); m.classList.add('hidden'); m.classList.remove('flex'); }
document.addEventListener('keydown', e => { if(e.key==='Escape') closeNoticeModal(); });
</script>
</body>
</html>
