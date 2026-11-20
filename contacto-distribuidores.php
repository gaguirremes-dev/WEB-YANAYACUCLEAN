<?php
/**
 * First-party endpoint for the B2B distributor request form (home #distribuidores).
 *
 *   GET  ?token=1   -> JSON {csrf, ts, sitekey} (starts the session; used by assets/js/distributor-form.js)
 *   POST            -> validates and e-mails the request to the commercial mailbox
 *                      JSON when the client sends "Accept: application/json", HTML page otherwise
 *   GET  (no query) -> standalone no-JS form (fallback)
 *
 * Protections: session + CSRF token, HMAC time trap, honeypot, hCaptcha (fails closed),
 * per-IP rate limit on a salted IP hash (the raw IP is never stored), strict validation,
 * header-injection protection, generic Spanish responses. Never indexed.
 * Mirrors the claims book (libro-de-reclamaciones.php) stack, which stays untouched.
 */
date_default_timezone_set('America/Lima');

define('DOMINIO_PERMITIDO', 'yanayacuclean.com');
if (is_file(__DIR__ . '/config.smtp.php')) require __DIR__ . '/config.smtp.php';
if (!defined('HCAPTCHA_SITE_KEY'))   define('HCAPTCHA_SITE_KEY',   '');
if (!defined('HCAPTCHA_SECRET_KEY')) define('HCAPTCHA_SECRET_KEY', '');
if (!defined('RECLAMOS_DATA_DIR'))   define('RECLAMOS_DATA_DIR',   '');
if (!defined('IP_HASH_SALT'))        define('IP_HASH_SALT',        '');
// Optional overrides (config.smtp.php): destination mailbox and limits.
foreach ([
    'DIST_NOTIF_EMAIL'    => 'comercial@yanayacuclean.com',
    'DIST_RATE_LIMIT_MAX' => 3,    // submissions per IP inside DIST_RATE_LIMIT_WINDOW
    'DIST_RATE_LIMIT_WINDOW' => 3600,
    'DIST_SITE_HOUR_MAX'  => 30,   // site-wide per hour
    'DIST_SITE_DAY_MAX'   => 100,  // site-wide per 24 h
] as $k => $v) { if (!defined($k)) define($k, $v); }
const DIST_MAX_BODY = 16384;
const DIST_MIN_AGE  = 4;
const DIST_MAX_AGE  = 7200;
const DIST_WHATSAPP_FALLBACK = 'https://wa.me/51974667498';

require_once __DIR__ . '/lib/storage.php';
require_once __DIR__ . '/lib/mailer.php';

// ── Helpers ───────────────────────────────────────────────────────────────────
function distEsc(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }

function distLimpiar(?string $s, int $max, bool $multiline = false): string {
    if ($s === null || $s === '' || !mb_check_encoding($s, 'UTF-8')) return '';
    $s = strip_tags($s);
    $s = str_replace(["\r\n", "\r"], "\n", $s);
    $s = $multiline ? (string)preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/', '', $s)
                    : (string)preg_replace('/[\x00-\x1F\x7F]+/', ' ', $s);
    $s = (string)preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u', '', $s);
    if ($multiline) $s = (string)preg_replace("/\n{3,}/", "\n\n", $s);
    return mb_substr(trim($s), 0, $max, 'UTF-8');
}

function distHttps(): bool {
    return (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443
        || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https' && distEsCloudflare((string)($_SERVER['REMOTE_ADDR'] ?? '')));
}

// Cloudflare ranges: copied from https://www.cloudflare.com/ips-v4 and /ips-v6 (retrieved 2026-11-20).
// Same static list as libro-de-reclamaciones.php; review every 6 months (see README).
const DIST_CF_RANGES = [
    '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
    '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
    '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
    '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
    '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
    '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
];
function distNormalizarIp(string $ip): string {
    $ip = trim($ip);
    if (preg_match('/^::ffff:(\d{1,3}(?:\.\d{1,3}){3})$/i', $ip, $m)) return $m[1];
    return $ip;
}
function distIpEnCidr(string $ip, string $cidr): bool {
    $parts = explode('/', $cidr, 2);
    $ipBin = @inet_pton($ip); $netBin = @inet_pton($parts[0]);
    if ($ipBin === false || $netBin === false || strlen($ipBin) !== strlen($netBin)) return false;
    $bits = isset($parts[1]) ? (int)$parts[1] : strlen($ipBin) * 8;
    $bytes = intdiv($bits, 8); $rem = $bits % 8;
    if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($netBin, 0, $bytes)) return false;
    if ($rem === 0) return true;
    $mask = (0xFF << (8 - $rem)) & 0xFF;
    return (ord($ipBin[$bytes]) & $mask) === (ord($netBin[$bytes]) & $mask);
}
function distEsCloudflare(string $ip): bool {
    $ip = distNormalizarIp($ip);
    if (filter_var($ip, FILTER_VALIDATE_IP) === false) return false;
    foreach (DIST_CF_RANGES as $cidr) { if (distIpEnCidr($ip, $cidr)) return true; }
    return false;
}
function distClientIp(): string {
    $remote = distNormalizarIp((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    if (filter_var($remote, FILTER_VALIDATE_IP) === false) return '0.0.0.0';
    if (distEsCloudflare($remote)) {
        $cf = distNormalizarIp((string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
        if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP) !== false) return $cf;
    }
    return $remote;
}

function distPrepararDirectorio(string $dir): bool {
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) return false;
    $ht  = $dir . '/.htaccess';
    $cur = is_file($ht) ? (string)@file_get_contents($ht) : '';
    if (strpos($cur, 'Require all denied') === false || strpos($cur, 'Options -Indexes') === false) {
        @file_put_contents($ht,
            "# Protected records directory: never served over HTTP.\n" .
            "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n" .
            "<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n" .
            "Options -Indexes\n");
    }
    if (!is_file($dir . '/index.html')) @file_put_contents($dir . '/index.html', '');
    return is_writable($dir);
}
// Same install.key as the claims book (domain-separated below), so one secret per installation.
function distClave(string $dir): string {
    $path = $dir . '/install.key';
    $key  = is_file($path) ? trim((string)@file_get_contents($path)) : '';
    if (strlen($key) >= 32) return $key;
    $fp = @fopen($path, 'c+');
    $key = '';
    if ($fp && flock($fp, LOCK_EX)) {
        $existing = trim((string)stream_get_contents($fp));
        if (strlen($existing) >= 32) { $key = $existing; }
        else {
            $new = bin2hex(random_bytes(32));
            ftruncate($fp, 0); rewind($fp); fwrite($fp, $new); fflush($fp);
            @chmod($path, 0600);
            $key = $new;
        }
        flock($fp, LOCK_UN);
    }
    if ($fp) fclose($fp);
    return $key;
}
function distHashIp(string $ip, string $dir): string {
    $salt = (string)IP_HASH_SALT;
    if ($salt === '') $salt = distClave($dir);
    return $salt === '' ? '' : hash('sha256', $salt . '|' . $ip);
}
function distLog(string $dir, string $event, array $ctx = []): void {
    $file = $dir . '/security.log';
    if (@is_file($file) && @filesize($file) > 2097152) @rename($file, $file . '.1');
    $line = date('Y-m-d H:i:s') . ' | ' . $event . ' | ' . json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) . PHP_EOL;
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}
function distTokenTiempo(string $dir, string $csrf): string {
    $key = distClave($dir);
    if ($key === '' || $csrf === '') return '';
    $ts = (string)time();
    return $ts . '.' . hash_hmac('sha256', 'dist-ts|' . $ts . '|' . $csrf, $key);
}
function distVerificarTiempo(string $dir, string $csrf, string $token): string {
    $key = distClave($dir);
    if ($key === '' || $csrf === '') return 'TT_NO_KEY';
    if (!preg_match('/^(\d{9,11})\.([a-f0-9]{64})$/', $token, $m)) return 'TT_MALFORMED';
    if (!hash_equals(hash_hmac('sha256', 'dist-ts|' . $m[1] . '|' . $csrf, $key), $m[2])) return 'TT_BAD_SIGNATURE';
    $age = time() - (int)$m[1];
    if ($age < DIST_MIN_AGE) return 'TT_TOO_FAST';
    if ($age > DIST_MAX_AGE) return 'TT_EXPIRED';
    return '';
}
function distHostPermitido(string $host): bool {
    $host = rtrim(strtolower(trim($host)), '.');
    return $host !== '' && ($host === DOMINIO_PERMITIDO || $host === 'www.' . DOMINIO_PERMITIDO);
}
function distOrigenValido(): bool {
    $origin  = trim((string)($_SERVER['HTTP_ORIGIN']  ?? ''));
    $referer = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
    if ($origin !== '')  return distHostPermitido((string)parse_url($origin, PHP_URL_HOST));
    if ($referer !== '') return distHostPermitido((string)parse_url($referer, PHP_URL_HOST));
    return true; // CSRF + captcha + time trap still apply
}
// hCaptcha: fails closed (no keys / no answer / bad hostname => false).
function distVerificarHCaptcha(string $token): bool {
    if ($token === '' || strlen($token) > 4096 || HCAPTCHA_SECRET_KEY === '') return false;
    $post = ['secret' => HCAPTCHA_SECRET_KEY, 'response' => $token];
    if (HCAPTCHA_SITE_KEY !== '') $post['sitekey'] = HCAPTCHA_SITE_KEY;
    $ctx = stream_context_create(['http' => [
        'method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded',
        'content' => http_build_query($post), 'timeout' => 10,
    ]]);
    $result = @file_get_contents('https://hcaptcha.com/siteverify', false, $ctx);
    if ($result === false) return false;
    $data = json_decode($result, true);
    if (!is_array($data) || ($data['success'] ?? false) !== true) return false;
    if (isset($data['hostname']) && is_string($data['hostname']) && $data['hostname'] !== '' && !distHostPermitido($data['hostname'])) return false;
    return true;
}
// Atomic per-IP-hash + site-wide rate limit. Returns '' (slot reserved) or an error code.
function distReservar(string $dir, string $ipHash): string {
    $stateFile = $dir . '/dist_rate_state.json';
    $fp = @fopen($stateFile . '.lock', 'c');
    if (!$fp || !flock($fp, LOCK_EX)) { if ($fp) fclose($fp); return 'RATE_LOCK_FAILED'; }
    $now = time();
    $data = is_file($stateFile) ? (json_decode((string)@file_get_contents($stateFile), true) ?: []) : [];
    $ips  = is_array($data['ip'] ?? null) ? $data['ip'] : [];
    $site = is_array($data['site'] ?? null) ? $data['site'] : [];
    $fresh = function ($t) use ($now): bool { return is_int($t) && ($now - $t) < 86400; };
    $site = array_values(array_filter($site, $fresh));
    foreach ($ips as $h => $list) {
        $ips[$h] = is_array($list) ? array_values(array_filter($list, $fresh)) : [];
        if (!$ips[$h]) unset($ips[$h]);
    }
    $mine     = $ips[$ipHash] ?? [];
    $inWindow = count(array_filter($mine, function ($t) use ($now) { return ($now - $t) < DIST_RATE_LIMIT_WINDOW; }));
    $siteHour = count(array_filter($site, function ($t) use ($now) { return ($now - $t) < 3600; }));
    $err = '';
    if ($inWindow >= DIST_RATE_LIMIT_MAX) $err = 'RATE_LIMIT_IP';
    elseif ($siteHour >= DIST_SITE_HOUR_MAX || count($site) >= DIST_SITE_DAY_MAX) $err = 'RATE_LIMIT_SITE';
    if ($err === '') {
        $mine[] = $now; $ips[$ipHash] = $mine; $site[] = $now;
        if (@file_put_contents($stateFile, json_encode(['ip' => $ips, 'site' => $site])) === false) $err = 'RATE_WRITE_FAILED';
    }
    flock($fp, LOCK_UN); fclose($fp);
    return $err;
}

// ── Responses ─────────────────────────────────────────────────────────────────
function distJson(int $status, array $payload): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Bootstrap ─────────────────────────────────────────────────────────────────
@ini_set('display_errors', '0');
@ini_set('html_errors', '0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header_remove('X-Powered-By');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'HEAD', 'POST'], true)) {
    http_response_code(405); header('Allow: GET, HEAD, POST'); exit;
}
$wantsJson = stripos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false;
$tokenReq  = $method === 'GET' && isset($_GET['token']);

$dataRoot = reclamosResolverDirectorioDatos(__DIR__);
$dir      = $dataRoot; // shares the claims-book directory (state files are prefixed dist_)
$dirOk    = distPrepararDirectorio($dir);
if ($dirOk) reclamosMigrarDatosLegacy(__DIR__ . '/reclamaciones', $dir);
$configured = $dirOk && HCAPTCHA_SITE_KEY !== '' && HCAPTCHA_SECRET_KEY !== '' && defined('SMTP_HOST');

@ini_set('session.use_strict_mode', '1');
@ini_set('session.use_only_cookies', '1');
session_cache_limiter('');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => distHttps(), 'httponly' => true, 'samesite' => 'Lax']);
session_start();
if (empty($_SESSION['dist_csrf'])) $_SESSION['dist_csrf'] = bin2hex(random_bytes(32));

$GENERIC_ERR = 'No pudimos enviar tu solicitud en este momento. Inténtalo más tarde o escríbenos por WhatsApp.';
$OK_MSG      = 'Solicitud enviada. Nos pondremos en contacto contigo en menos de 24 horas.';

if ($tokenReq) {
    if (!$configured) distJson(503, ['ok' => false, 'message' => $GENERIC_ERR]);
    distJson(200, ['ok' => true, 'csrf' => $_SESSION['dist_csrf'], 'ts' => distTokenTiempo($dir, $_SESSION['dist_csrf']), 'sitekey' => HCAPTCHA_SITE_KEY]);
}

$tipos   = ['Bodega', 'Distribuidora', 'Empresa de servicios de limpieza', 'Restaurante u Hotel', 'Otro'];
$error   = '';
$success = false;
$old     = ['nombre' => '', 'tipo_negocio' => '', 'ciudad' => '', 'whatsapp' => '', 'mensaje' => ''];

if ($method === 'POST') {
    $ipHash = $dirOk ? distHashIp(distClientIp(), $dir) : '';
    $status = 400;
    $fail = function (string $code, string $msg, int $http = 400) use (&$error, &$status, $dir, $ipHash, $dirOk) {
        if ($dirOk) distLog($dir, 'DIST_' . $code, ['ip_hash' => $ipHash]);
        $error = $msg; $status = $http;
    };
    if (!$configured || $ipHash === '') {
        $fail('NOT_CONFIGURED', $GENERIC_ERR, 503);
    } elseif ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > DIST_MAX_BODY) {
        $fail('PAYLOAD_TOO_LARGE', 'La solicitud es demasiado grande. Reduce el texto e inténtalo de nuevo.', 413);
    } elseif (!empty($_POST['hp_website'] ?? '')) {
        // Honeypot: answer as if it worked, do nothing.
        distLog($dir, 'DIST_HONEYPOT', ['ip_hash' => $ipHash]);
        if ($wantsJson) distJson(200, ['ok' => true, 'message' => $OK_MSG]);
        $success = true;
    } else {
        $bad = false;
        foreach ($_POST as $v) { if (!is_string($v) || !mb_check_encoding($v, 'UTF-8')) { $bad = true; break; } }
        $csrfPost = (string)($_POST['csrf_token'] ?? '');
        $csrfSess = (string)($_SESSION['dist_csrf'] ?? '');
        if ($bad) {
            $fail('BAD_INPUT_ENCODING', 'El formulario contiene datos no válidos. Recarga la página e inténtalo de nuevo.');
        } elseif ($csrfPost === '' || $csrfSess === '' || !hash_equals($csrfSess, $csrfPost)) {
            $fail('CSRF_FAILED', 'Token de seguridad inválido. Recarga la página e inténtalo de nuevo.');
        } elseif (($tt = distVerificarTiempo($dir, $csrfSess, (string)($_POST['form_ts'] ?? ''))) !== '') {
            $fail('TIME_TRAP_' . $tt, $tt === 'TT_TOO_FAST'
                ? 'Enviaste el formulario demasiado rápido. Espera unos segundos e inténtalo de nuevo.'
                : 'El formulario expiró o no es válido. Recarga la página e inténtalo de nuevo.');
        } elseif (!distOrigenValido()) {
            $fail('ORIGIN_REJECTED', 'Solicitud rechazada. Recarga la página e inténtalo de nuevo.');
        } elseif (!distVerificarHCaptcha((string)($_POST['h-captcha-response'] ?? ''))) {
            $fail('CAPTCHA_FAILED', 'Por favor, completa el desafío de seguridad (captcha) antes de enviar.');
        } else {
            $old['nombre']       = distLimpiar($_POST['nombre']   ?? '', 120);
            $old['tipo_negocio'] = (string)($_POST['tipo_negocio'] ?? '');
            $old['ciudad']       = distLimpiar($_POST['ciudad']   ?? '', 80);
            $old['whatsapp']     = distLimpiar($_POST['whatsapp'] ?? '', 20);
            $old['mensaje']      = distLimpiar($_POST['mensaje']  ?? '', 1000, true);
            $digits = preg_replace('/\D/', '', $old['whatsapp']);
            $links  = preg_match_all('~(?:https?://|ftp://|www\.)\S+~i', $old['mensaje']);
            if ($old['nombre'] === '' || $old['ciudad'] === '' || !in_array($old['tipo_negocio'], $tipos, true)
                || !preg_match('/^[0-9+()\s.-]{7,20}$/', $old['whatsapp']) || strlen($digits) < 7 || strlen($digits) > 15
                || empty($_POST['acepta_politica'])) {
                $fail('VALIDATION', 'Revisa los datos: completa los campos obligatorios y acepta la Política de Privacidad.');
            } elseif ($links > 1 || preg_match('/<\s*a[\s>]/i', (string)($_POST['mensaje'] ?? '')) || preg_match('/\[\s*(?:url|link)\b/i', $old['mensaje'])) {
                $fail('SPAM_CONTENT', 'Tu mensaje contiene enlaces o formato no permitido. Quita los enlaces e inténtalo de nuevo.');
            } else {
                $rate = distReservar($dir, $ipHash);
                if ($rate === 'RATE_LIMIT_IP' || $rate === 'RATE_LIMIT_SITE') {
                    $fail($rate, 'Has alcanzado el límite de envíos por ahora. Inténtalo más tarde o escríbenos por WhatsApp.', 429);
                } elseif ($rate !== '') {
                    $fail($rate, $GENERIC_ERR, 503);
                } else {
                    $row = function (string $k, string $v, bool $nl = false): string {
                        return "<tr><td style='padding:6px 12px 6px 0;font-weight:bold;vertical-align:top;'>" . distEsc($k) . "</td><td style='padding:6px 0;'>" . ($nl ? nl2br(distEsc($v)) : distEsc($v)) . "</td></tr>";
                    };
                    $html = "<div style='font-family:Arial,sans-serif;color:#333;max-width:600px;'>"
                          . "<h2 style='color:#0D1B3E;'>Nueva solicitud de distribuidor</h2><table style='font-size:14px;border-collapse:collapse;'>"
                          . $row('Nombre', $old['nombre']) . $row('Tipo de negocio', $old['tipo_negocio']) . $row('Ciudad', $old['ciudad'])
                          . $row('WhatsApp', $old['whatsapp']) . $row('Mensaje', $old['mensaje'] !== '' ? $old['mensaje'] : '(sin mensaje)', true)
                          . $row('Política de privacidad', 'Aceptada') . $row('Fecha', date('d/m/Y h:i A'))
                          . "</table></div>";
                    $errMail = '';
                    if (yySmtpSend(DIST_NOTIF_EMAIL, '[B2B] Solicitud Distribuidor — ' . $old['nombre'], $html, $errMail)) {
                        $success = true;
                        distLog($dir, 'DIST_OK', ['ip_hash' => $ipHash]);
                        $_SESSION['dist_csrf'] = bin2hex(random_bytes(32));
                        if ($wantsJson) distJson(200, ['ok' => true, 'message' => $OK_MSG]);
                    } else {
                        $fail('SMTP_FAILED', $GENERIC_ERR, 502);
                        distLog($dir, 'DIST_SMTP_DETAIL', ['detail' => substr($errMail, 0, 160)]);
                    }
                }
            }
        }
    }
    if ($wantsJson && !$success) distJson($status, ['ok' => false, 'message' => $error]);
    if (!$success) http_response_code($status);
}

// ── HTML (no-JS fallback form / result page) ─────────────────────────────────
$siteKey = HCAPTCHA_SITE_KEY;
$csrf    = (string)$_SESSION['dist_csrf'];
$ts      = $dirOk ? distTokenTiempo($dir, $csrf) : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Solicitud de distribuidor - YanaYacu Clean</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="icon" type="image/svg+xml" href="favicon.svg">
  <link rel="stylesheet" href="assets/css/fonts.css?v=07774ca3">
  <link rel="stylesheet" href="assets/css/tailwind.css?v=30056da6">
<?php if (!$success && $configured): ?>
  <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
<?php endif; ?>
</head>
<body class="min-h-screen bg-gray-50 font-sans text-gray-800">
<main class="max-w-xl mx-auto px-4 py-10">
  <a href="/" class="text-brand-dark underline text-sm">&larr; Volver al inicio</a>
  <h1 class="text-2xl font-bold text-navy mt-4 mb-6">Solicitar catálogo y condiciones mayoristas</h1>
<?php if ($success): ?>
  <div class="rounded-xl bg-white border border-gray-200 p-6">
    <p class="font-semibold text-navy mb-2"><?= distEsc($OK_MSG) ?></p>
    <a class="text-brand-dark underline" href="<?= distEsc(DIST_WHATSAPP_FALLBACK) ?>" rel="noopener">También escríbenos por WhatsApp</a>
  </div>
<?php else: ?>
<?php if ($error !== ''): ?>
  <p role="alert" class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm"><?= distEsc($error) ?></p>
<?php endif; ?>
<?php if (!$configured): ?>
  <p class="text-sm text-gray-600">El formulario no está disponible temporalmente. Escríbenos por <a class="text-brand-dark underline" href="<?= distEsc(DIST_WHATSAPP_FALLBACK) ?>" rel="noopener">WhatsApp</a>.</p>
<?php else: ?>
  <form method="post" action="contacto-distribuidores.php" class="rounded-xl bg-white border border-gray-200 p-6 space-y-4">
    <input type="hidden" name="csrf_token" value="<?= distEsc($csrf) ?>">
    <input type="hidden" name="form_ts" value="<?= distEsc($ts) ?>">
    <div class="absolute -left-[9999px] w-px h-px overflow-hidden" aria-hidden="true">
      <label>No completar <input type="text" name="hp_website" tabindex="-1" autocomplete="off"></label>
    </div>
    <div>
      <label for="f-nombre" class="block text-sm font-medium mb-1">Nombre completo *</label>
      <input id="f-nombre" name="nombre" required maxlength="120" value="<?= distEsc($old['nombre']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2">
    </div>
    <div>
      <label for="f-tipo" class="block text-sm font-medium mb-1">Tipo de negocio *</label>
      <select id="f-tipo" name="tipo_negocio" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
        <option value="" disabled<?= $old['tipo_negocio'] === '' ? ' selected' : '' ?>>Selecciona...</option>
<?php foreach ($tipos as $t): ?>
        <option value="<?= distEsc($t) ?>"<?= $old['tipo_negocio'] === $t ? ' selected' : '' ?>><?= distEsc($t) ?></option>
<?php endforeach; ?>
      </select>
    </div>
    <div>
      <label for="f-ciudad" class="block text-sm font-medium mb-1">Ciudad *</label>
      <input id="f-ciudad" name="ciudad" required maxlength="80" value="<?= distEsc($old['ciudad']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2">
    </div>
    <div>
      <label for="f-wa" class="block text-sm font-medium mb-1">WhatsApp de contacto *</label>
      <input id="f-wa" name="whatsapp" type="tel" required maxlength="20" value="<?= distEsc($old['whatsapp']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2">
    </div>
    <div>
      <label for="f-msg" class="block text-sm font-medium mb-1">Mensaje (opcional)</label>
      <textarea id="f-msg" name="mensaje" rows="3" maxlength="1000" class="w-full border border-gray-300 rounded-lg px-3 py-2"><?= distEsc($old['mensaje']) ?></textarea>
    </div>
    <label class="flex items-start gap-3 text-sm">
      <input type="checkbox" name="acepta_politica" value="1" required class="mt-1">
      <span>He leído y acepto la <a href="privacidad.html" target="_blank" class="text-brand-dark underline">Política de Privacidad</a> y autorizo el tratamiento de mis datos personales conforme a la Ley 29733.</span>
    </label>
    <div class="h-captcha" data-sitekey="<?= distEsc($siteKey) ?>"></div>
    <button type="submit" class="w-full bg-brand-dark text-white font-bold py-3 rounded-xl">Solicitar catálogo mayorista</button>
  </form>
<?php endif; ?>
<?php endif; ?>
</main>
</body>
</html>
