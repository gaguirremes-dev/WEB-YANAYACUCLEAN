<?php
/**
 * Plantilla de configuración SMTP.
 * Copia este archivo a config.smtp.php y rellena tus credenciales reales.
 * config.smtp.php NO debe subirse al repositorio (.gitignore).
 */
define('SMTP_HOST',     'mail.yanayacuclean.com');
define('SMTP_PORT',     465);
define('SMTP_USER',     'reclamaciones@yanayacuclean.com');
define('SMTP_PASS',     'TU_CONTRASEÑA_AQUI');
define('SMTP_FROM_NAME','YanaYacu Clean');
define('EMPRESA_NOTIF_EMAIL', 'reclamaciones@yanayacuclean.com');

// hCaptcha (https://dashboard.hcaptcha.com)
define('HCAPTCHA_SITE_KEY',   'TU_SITE_KEY_AQUI');
define('HCAPTCHA_SECRET_KEY', 'TU_SECRET_KEY_AQUI');
