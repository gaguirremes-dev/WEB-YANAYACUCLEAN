<?php
/**
 * Data directory resolution for records (claims book, distributor leads state).
 * Blocked from HTTP by .htaccess (lib/). Functions only; no side effects on include.
 *
 * Order of preference:
 *   1. RECLAMOS_DATA_DIR (config.smtp.php), when set and usable.
 *   2. <parent of the docroot>/reclamaciones-data, when the parent is writable
 *      (outside the web root: nothing in it can ever be requested over HTTP).
 *   3. <docroot>/reclamaciones (legacy; protected by its own .htaccess).
 */

function reclamosDirectorioUsable(string $dir): bool {
    if ($dir === '') return false;
    if (@is_dir($dir)) return @is_writable($dir);
    $parent = dirname($dir);
    return @is_dir($parent) && @is_writable($parent) && (@mkdir($dir, 0750, true) || @is_dir($dir));
}

function reclamosResolverDirectorioDatos(string $docroot): string {
    $docroot = rtrim(str_replace('\\', '/', $docroot), '/');
    $legacy  = $docroot . '/reclamaciones';
    $cfg     = defined('RECLAMOS_DATA_DIR') ? trim((string)RECLAMOS_DATA_DIR) : '';
    $wanted  = $cfg !== '' ? rtrim(str_replace('\\', '/', $cfg), '/') : dirname($docroot) . '/reclamaciones-data';
    if ($wanted !== $legacy && reclamosDirectorioUsable($wanted)) return $wanted;
    return $legacy;
}

/**
 * One-time COPY of the legacy docroot data into the new directory. Never moves or
 * deletes anything. install.key is copied first so IDs, HMACs and IP hashes stay valid.
 * Runs only when the new directory has no records yet and no completed-migration marker.
 * Returns the number of files copied (0 when nothing to do).
 */
function reclamosMigrarDatosLegacy(string $legacy, string $new): int {
    if ($legacy === $new || !@is_dir($legacy) || !@is_dir($new)) return 0;
    $marker = $new . '/.migrated-from-docroot';
    if (is_file($marker) || is_file($new . '/records.json')) return 0;
    $hasData = is_file($legacy . '/records.json') || is_file($legacy . '/install.key');
    if (!$hasData) return 0;

    $lock = @fopen($new . '/.migrate.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) { if ($lock) fclose($lock); return 0; }
    $copied = 0; $ok = true;
    if (is_file($marker) || is_file($new . '/records.json')) { // another request finished first
        flock($lock, LOCK_UN); fclose($lock); return 0;
    }
    $files = [];
    foreach ((array)@scandir($legacy) as $f) {
        if ($f === '.' || $f === '..' || $f === '.htaccess' || $f === 'index.html') continue;
        if (is_file($legacy . '/' . $f)) $files[] = $f;
    }
    usort($files, function ($a, $b) { return ($a === 'install.key' ? 0 : 1) <=> ($b === 'install.key' ? 0 : 1); });
    foreach ($files as $f) {
        if (@copy($legacy . '/' . $f, $new . '/' . $f)) {
            $copied++;
            if ($f === 'install.key') @chmod($new . '/' . $f, 0600);
        } else {
            $ok = false;
        }
    }
    if ($ok) @file_put_contents($marker, date('c') . " copied from legacy docroot directory\n");
    flock($lock, LOCK_UN); fclose($lock);
    return $copied;
}
